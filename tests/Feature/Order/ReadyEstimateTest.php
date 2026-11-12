<?php

namespace Tests\Feature\Order;

use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductAvailabilityStatus;
use App\Enums\UserRole;
use App\Livewire\Catalog\ProductForm;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\ReadyEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Static ready-time estimate: the slowest item's base prep time, set when the
 * order is confirmed. The customer sees a clock time ("Pronto às HH:MM") that
 * never goes backward and moves forward at most twice; the kitchen sees a
 * running timer that turns red past the estimate.
 */
class ReadyEstimateTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Category $category;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-11 12:00:00'));

        $this->restaurant = Restaurant::factory()->create();
        $this->category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    /** A pending order with one line per product. */
    private function order(Product ...$products): Order
    {
        $order = Order::factory()->for($this->restaurant)->create([
            'status' => OrderStatus::PendingConfirmation,
        ]);

        foreach ($products as $product) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'unit_price'   => 30,
                'quantity'     => 1,
                'subtotal'     => 30,
            ]);
        }

        return $order;
    }

    private function transition(Order $order, OrderStatus $to): Order
    {
        return app(TransitionOrderStatus::class)->execute($order, $to, $this->admin)->fresh();
    }

    public function test_product_form_saves_and_edits_prep_minutes(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'Pizza Calabresa')
            ->set('price', '45.00')
            ->set('category_id', (string) $this->category->id)
            ->set('prep_minutes', 25)
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Pizza Calabresa')->firstOrFail();
        $this->assertSame(25, $product->prep_minutes);

        Livewire::test(ProductForm::class, ['product' => $product])
            ->assertSet('prep_minutes', 25)
            ->set('prep_minutes', 30)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(30, $product->fresh()->prep_minutes);
    }

    public function test_product_screens_render_the_prep_time_field(): void
    {
        $this->actingAs($this->admin);
        $product = $this->product('Pizza', 25);

        $this->get(route('admin.catalog.products.create'))
            ->assertOk()
            ->assertSee('Tempo de preparo (min)')
            ->assertDontSee('Undefined');

        $this->get(route('admin.catalog.products.edit', $product))
            ->assertOk()
            ->assertSee('Tempo de preparo (min)')
            ->assertDontSee('Undefined');
    }

    public function test_prep_minutes_must_be_a_positive_whole_number(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ProductForm::class)
            ->set('name', 'Pizza Calabresa')
            ->set('price', '45.00')
            ->set('category_id', (string) $this->category->id)
            ->set('prep_minutes', 0)
            ->call('save')
            ->assertHasErrors(['prep_minutes' => 'min']);
    }

    public function test_new_products_default_to_fifteen_minutes(): void
    {
        $product = Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $this->category->id,
            'name'                => 'Suco',
            'price'               => 8.00,
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);

        $this->assertSame(15, $product->fresh()->prep_minutes);
    }

    public function test_confirming_sets_the_estimate_from_the_slowest_item(): void
    {
        $order = $this->order($this->product('Suco', 5), $this->product('Pizza', 25));

        $order = $this->transition($order, OrderStatus::Confirmed);

        $this->assertSame('2026-11-11 12:25', $order->estimated_ready_at->format('Y-m-d H:i'));
        $this->assertSame(0, $order->eta_adjustments);
    }

    public function test_tracking_shows_the_ready_clock_time(): void
    {
        $order = $this->transition($this->order($this->product('Pizza', 25)), OrderStatus::Confirmed);

        $this->get(route('store.order.tracking', $order->token))
            ->assertOk()
            ->assertSee('Pronto às 12:25')
            ->assertDontSee('Está demorando')
            ->assertDontSee('Undefined');
    }

    public function test_tracking_has_no_estimate_before_confirmation_or_once_ready(): void
    {
        $order = $this->order($this->product('Pizza', 25));

        $this->get(route('store.order.tracking', $order->token))->assertOk()->assertDontSee('Pronto às');

        $order = $this->transition($order, OrderStatus::Confirmed);
        $order = $this->transition($order, OrderStatus::InPreparation);
        $order = $this->transition($order, OrderStatus::ReadyForPickup);

        $this->get(route('store.order.tracking', $order->token))->assertOk()->assertDontSee('Pronto às');
    }

    public function test_overdue_estimate_moves_forward_at_most_twice_with_a_delay_message(): void
    {
        $order = $this->transition($this->order($this->product('Pizza', 25)), OrderStatus::Confirmed);
        $order = $this->transition($order, OrderStatus::InPreparation);

        // 12:30 — 5 min past 12:25: pushed to 12:35, with the delay message.
        Carbon::setTestNow('2026-11-11 12:30:00');
        $this->get(route('store.order.tracking', $order->token))
            ->assertOk()
            ->assertSee('Está demorando um pouquinho mais, novo horário: 12:35');
        $this->assertSame(1, $order->fresh()->eta_adjustments);

        // 12:40 — overdue again: pushed to 12:45 (second and last adjustment).
        Carbon::setTestNow('2026-11-11 12:40:00');
        $this->get(route('store.order.tracking', $order->token))->assertSee('novo horário: 12:45');

        // 12:50 — overdue, but no more adjustments: the time shown stays 12:45.
        Carbon::setTestNow('2026-11-11 12:50:00');
        $this->get(route('store.order.tracking', $order->token))->assertSee('novo horário: 12:45');

        $order->refresh();
        $this->assertSame(2, $order->eta_adjustments);
        $this->assertSame('12:45', $order->estimated_ready_at->format('H:i'));
    }

    public function test_estimate_never_decreases_after_it_was_shown(): void
    {
        $pizza = $this->product('Pizza', 25);
        $order = $this->transition($this->order($pizza), OrderStatus::Confirmed);
        $shown = $order->estimated_ready_at->copy();

        // A faster product, a repeated confirmation and an early check never pull it back.
        $pizza->update(['prep_minutes' => 5]);
        ReadyEstimate::start($order);
        $this->assertFalse(ReadyEstimate::adjust($order));
        $order = $this->transition($order, OrderStatus::InPreparation);

        Carbon::setTestNow('2026-11-11 12:26:00');
        $this->assertTrue(ReadyEstimate::adjust($order));

        $this->assertTrue($order->fresh()->estimated_ready_at->greaterThanOrEqualTo($shown));
        $this->assertSame('12:31', $order->fresh()->estimated_ready_at->format('H:i'));
    }

    public function test_kitchen_shows_a_prep_timer_and_the_expected_time(): void
    {
        $order = $this->transition($this->order($this->product('Pizza', 25)), OrderStatus::Confirmed);
        Carbon::setTestNow('2026-11-11 12:03:00');
        $this->transition($order, OrderStatus::InPreparation);

        Carbon::setTestNow('2026-11-11 12:10:30');
        $this->actingAs(User::factory()->create(['role' => UserRole::Kitchen]))
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('data-prep-timer', false)
            ->assertSee('07:30')
            ->assertSee('Previsto 12:25')
            ->assertDontSee('Undefined');
    }
}
