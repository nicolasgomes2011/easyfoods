<?php

namespace Tests\Feature\Order;

use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductAvailabilityStatus;
use App\Enums\TimeBand;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrepSample;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\LearnedPrepTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;
use Tests\TestCase;

/**
 * Learned ETA (EF-26): every order that becomes ready writes the real prep time
 * of its products (append-only), and the estimate shown on confirmation is the
 * moving average of that history (same time band, last 30 days, recent first)
 * × the kitchen load factor; with little history it is the base prep time.
 */
class LearnedPrepTimeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Category $category;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday, lunch band.
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00'));

        // Lowest id: the restaurant the panel scopes to.
        $this->restaurant = Restaurant::factory()->create();
        $this->category = $this->categoryFor($this->restaurant);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function categoryFor(Restaurant $restaurant): Category
    {
        return Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches-'.$restaurant->id,
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
    }

    private function product(string $name, int $prepMinutes): Product
    {
        return Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $this->category->id,
            'name'                => $name,
            'price'               => 30.00,
            'prep_minutes'        => $prepMinutes,
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    /** A pending order; each argument is a product or [product, quantity]. */
    private function order(Product|array ...$lines): Order
    {
        $order = Order::factory()->for($this->restaurant)->create([
            'status' => OrderStatus::PendingConfirmation,
        ]);

        foreach ($lines as $line) {
            [$product, $quantity] = is_array($line) ? $line : [$line, 1];

            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'unit_price'   => 30,
                'quantity'     => $quantity,
                'subtotal'     => 30 * $quantity,
            ]);
        }

        return $order;
    }

    private function transition(Order $order, OrderStatus $to): Order
    {
        return app(TransitionOrderStatus::class)->execute($order, $to, $this->admin)->fresh();
    }

    /** Runs an order through the kitchen, taking that many minutes from "em preparo" to "pronto". */
    private function prepare(Order $order, int $minutes): Order
    {
        $order = $this->transition($order, OrderStatus::Confirmed);
        $order = $this->transition($order, OrderStatus::InPreparation);
        $this->travel($minutes)->minutes();

        return $this->transition($order, OrderStatus::ReadyForPickup);
    }

    /** A history row as if an order had been prepared $daysAgo days ago at this time of day. */
    private function sample(Product $product, int $minutes, int $daysAgo = 1, int $load = 0, ?TimeBand $band = null, ?Restaurant $restaurant = null): void
    {
        $restaurant ??= $this->restaurant;
        $readyAt = now()->subDays($daysAgo);
        $order = Order::factory()->for($restaurant)->create(['status' => OrderStatus::Completed]);

        PrepSample::create([
            'restaurant_id' => $restaurant->id,
            'product_id'    => $product->id,
            'order_id'      => $order->id,
            'quantity'      => 1,
            'prep_seconds'  => $minutes * 60,
            'kitchen_load'  => $load,
            'time_band'     => $band ?? TimeBand::at($readyAt),
            'day_of_week'   => $readyAt->dayOfWeek,
            'started_at'    => $readyAt->copy()->subMinutes($minutes),
            'ready_at'      => $readyAt,
        ]);
    }

    private function samples(Product $product, int $count, int $minutes, int $daysAgo = 1, int $load = 0, ?TimeBand $band = null, ?Restaurant $restaurant = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->sample($product, $minutes, $daysAgo, $load, $band, $restaurant);
        }
    }

    /** Minutes between the confirmation (now) and the estimate it set. */
    private function estimatedMinutes(Order $order): int
    {
        $confirmed = $this->transition($order, OrderStatus::Confirmed);

        return (int) $confirmed->confirmed_at->diffInMinutes($confirmed->estimated_ready_at);
    }

    /** Puts $count other orders of the restaurant in preparation, started a few minutes ago. */
    private function busyKitchen(int $count): void
    {
        Order::factory()->for($this->restaurant)->count($count)->create([
            'status'                 => OrderStatus::InPreparation,
            'confirmed_at'           => now()->subMinutes(15),
            'preparation_started_at' => now()->subMinutes(10),
        ]);
    }

    // ── History capture ─────────────────────────────────────────────────────

    public function test_ready_order_records_one_sample_per_product_with_the_real_duration(): void
    {
        $pizza = $this->product('Pizza', 25);
        $suco = $this->product('Suco', 5);

        $order = $this->prepare($this->order([$pizza, 2], $suco, $pizza), 18);

        $samples = PrepSample::orderBy('product_id')->get();
        $this->assertCount(2, $samples);

        [$pizzaSample, $sucoSample] = [$samples->firstWhere('product_id', $pizza->id), $samples->firstWhere('product_id', $suco->id)];
        $this->assertSame(3, $pizzaSample->quantity);
        $this->assertSame(1, $sucoSample->quantity);

        foreach ($samples as $sample) {
            $this->assertSame($this->restaurant->id, $sample->restaurant_id);
            $this->assertSame($order->id, $sample->order_id);
            $this->assertSame(18 * 60, $sample->prep_seconds);
            $this->assertSame(0, $sample->kitchen_load);
            $this->assertSame(TimeBand::Lunch, $sample->time_band);
            $this->assertSame(3, $sample->day_of_week); // Wednesday
            $this->assertTrue($sample->started_at->equalTo($order->preparation_started_at));
            $this->assertTrue($sample->ready_at->equalTo($order->ready_at));
        }
    }

    public function test_sample_keeps_the_kitchen_load_of_when_the_order_started(): void
    {
        $this->busyKitchen(4);

        $this->prepare($this->order($this->product('Pizza', 25)), 20);

        $this->assertSame(4, PrepSample::sole()->kitchen_load);
    }

    public function test_items_without_a_product_and_forgotten_orders_write_no_sample(): void
    {
        $order = $this->order();
        OrderItem::create([
            'order_id'     => $order->id,
            'product_name' => 'Item avulso',
            'unit_price'   => 10,
            'quantity'     => 1,
            'subtotal'     => 10,
        ]);
        $this->prepare($order, 15);

        // Ready more than 4 hours after it started: left on screen, not real prep time.
        $this->prepare($this->order($this->product('Pizza', 25)), 5 * 60);

        $this->assertSame(0, PrepSample::count());
    }

    public function test_samples_are_append_only(): void
    {
        $this->prepare($this->order($this->product('Pizza', 25)), 20);
        $sample = PrepSample::sole();

        try {
            $sample->update(['prep_seconds' => 1]);
            $this->fail('A sample must not be updated.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $sample->delete();
    }

    // ── Estimate ────────────────────────────────────────────────────────────

    public function test_without_history_the_estimate_is_the_base_prep_time(): void
    {
        $order = $this->order($this->product('Suco', 5), $this->product('Pizza', 25));

        $this->assertSame(25, $this->estimatedMinutes($order));
    }

    public function test_with_fewer_than_the_minimum_samples_the_base_time_still_applies(): void
    {
        $pizza = $this->product('Pizza', 25);
        $this->samples($pizza, LearnedPrepTime::MIN_SAMPLES - 1, 40);

        $this->assertSame(25, $this->estimatedMinutes($this->order($pizza)));
    }

    public function test_with_simulated_history_the_estimate_converges_to_the_real_average(): void
    {
        $pizza = $this->product('Pizza', 25);

        // The kitchen really takes 32 minutes, order after order (one a day, same time).
        for ($day = LearnedPrepTime::MIN_SAMPLES; $day >= 1; $day--) {
            Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00')->subDays($day));
            $this->prepare($this->order($pizza), 32);
        }
        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00'));

        $this->assertSame(LearnedPrepTime::MIN_SAMPLES, PrepSample::count());
        $this->assertSame(32, $this->estimatedMinutes($this->order($pizza)));
    }

    public function test_varied_history_averages_to_the_real_mean(): void
    {
        $pizza = $this->product('Pizza', 25);
        foreach ([36, 44, 38, 42, 40, 39, 41, 40] as $minutes) {
            $this->sample($pizza, $minutes, daysAgo: 2);
        }

        $this->assertSame(40, $this->estimatedMinutes($this->order($pizza)));
    }

    public function test_recent_samples_weigh_more_than_old_ones(): void
    {
        $pizza = $this->product('Pizza', 25);
        $this->samples($pizza, 7, 20, daysAgo: 21);
        $this->samples($pizza, 7, 40, daysAgo: 0);

        $minutes = $this->estimatedMinutes($this->order($pizza));

        // A plain average would be 30.
        $this->assertGreaterThan(35, $minutes);
        $this->assertLessThan(40, $minutes);
    }

    public function test_old_samples_other_time_bands_and_other_restaurants_are_ignored(): void
    {
        $pizza = $this->product('Pizza', 25);

        $this->samples($pizza, 10, 60, daysAgo: LearnedPrepTime::WINDOW_DAYS + 1);
        $this->samples($pizza, 10, 60, band: TimeBand::Dinner);

        $other = Restaurant::factory()->create();
        $this->samples($pizza, 10, 60, restaurant: $other);

        $this->assertSame(25, $this->estimatedMinutes($this->order($pizza)));
    }

    public function test_the_slowest_product_sets_the_estimate(): void
    {
        $pizza = $this->product('Pizza', 25);
        $suco = $this->product('Suco', 5);
        $this->samples($suco, 7, 30); // the juice is really slow

        $this->assertSame(30, $this->estimatedMinutes($this->order($pizza, $suco)));
    }

    // ── Kitchen load ────────────────────────────────────────────────────────

    public function test_load_factor_follows_the_scope_table(): void
    {
        $this->assertSame(1.0, LearnedPrepTime::loadFactor(0));
        $this->assertSame(1.0, LearnedPrepTime::loadFactor(2));
        $this->assertSame(1.2, LearnedPrepTime::loadFactor(3));
        $this->assertSame(1.2, LearnedPrepTime::loadFactor(5));
        $this->assertSame(1.5, LearnedPrepTime::loadFactor(6));
        $this->assertSame(1.5, LearnedPrepTime::loadFactor(9));
        $this->assertSame(1.8, LearnedPrepTime::loadFactor(10));
    }

    public function test_a_busy_kitchen_raises_the_estimate(): void
    {
        $this->busyKitchen(3);

        $this->assertSame(30, $this->estimatedMinutes($this->order($this->product('Pizza', 25)))); // 25 × 1.2
    }

    public function test_kitchen_load_ignores_ready_canceled_forgotten_and_other_restaurant_orders(): void
    {
        $this->busyKitchen(2);

        Order::factory()->for($this->restaurant)->create([
            'status' => OrderStatus::ReadyForPickup, 'preparation_started_at' => now()->subMinutes(20), 'ready_at' => now()->subMinute(),
        ]);
        Order::factory()->for($this->restaurant)->create([
            'status' => OrderStatus::Canceled, 'preparation_started_at' => now()->subMinutes(20), 'canceled_at' => now()->subMinute(),
        ]);
        Order::factory()->for($this->restaurant)->create([
            'status' => OrderStatus::InPreparation, 'preparation_started_at' => now()->subHours(LearnedPrepTime::LOAD_LOOKBACK_HOURS + 1),
        ]);
        Order::factory()->for(Restaurant::factory())->count(3)->create([
            'status' => OrderStatus::InPreparation, 'preparation_started_at' => now()->subMinutes(5),
        ]);

        $this->assertSame(2, LearnedPrepTime::kitchenLoad($this->restaurant->id, now()));
        $this->assertSame(25, $this->estimatedMinutes($this->order($this->product('Pizza', 25))));
    }

    public function test_samples_are_normalized_by_the_load_they_were_made_under(): void
    {
        $pizza = $this->product('Pizza', 25);
        $this->samples($pizza, 7, 36, load: 3); // 36 min with a ×1.2 kitchen = 30 at normal load

        $this->assertSame(30, $this->estimatedMinutes($this->order($pizza)));
    }

    // ── Customer screen ─────────────────────────────────────────────────────

    public function test_tracking_shows_the_learned_ready_time(): void
    {
        $pizza = $this->product('Pizza', 25);
        $this->samples($pizza, 7, 32);

        $order = $this->transition($this->order($pizza), OrderStatus::Confirmed);

        $this->get(route('store.order.tracking', $order->token))
            ->assertOk()
            ->assertSee('Pronto às 12:32')
            ->assertDontSee('Undefined');
    }

    public function test_time_bands_cover_the_whole_day(): void
    {
        $at = fn (string $time) => TimeBand::at(Carbon::parse('2026-11-11 '.$time));

        $this->assertSame(TimeBand::LateNight, $at('04:59'));
        $this->assertSame(TimeBand::Breakfast, $at('05:00'));
        $this->assertSame(TimeBand::Lunch, $at('11:00'));
        $this->assertSame(TimeBand::Lunch, $at('14:59'));
        $this->assertSame(TimeBand::Afternoon, $at('15:00'));
        $this->assertSame(TimeBand::Dinner, $at('18:00'));
        $this->assertSame(TimeBand::LateNight, $at('23:00'));
    }
}
