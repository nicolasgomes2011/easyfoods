<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\ExpireUnconfirmedOrders;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Scopes\RestaurantScope;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Multi-tenant 2/4 (EF-82): restaurant-owned models are fenced to the current
 * restaurant by a global scope (BelongsToRestaurant), and a new row without
 * restaurant_id gets the current restaurant's id.
 */
class RestaurantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurantA;

    private Restaurant $restaurantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurantA = Restaurant::factory()->create(); // lowest id: the default
        $this->restaurantB = Restaurant::factory()->create();
    }

    private function catalogFor(Restaurant $restaurant, int $products): void
    {
        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => "Lanches {$restaurant->id}",
            'slug'          => "lanches-{$restaurant->id}",
        ]);

        for ($i = 1; $i <= $products; $i++) {
            Product::create([
                'restaurant_id' => $restaurant->id,
                'category_id'   => $category->id,
                'name'          => "Produto {$restaurant->id}-{$i}",
                'price'         => 10,
            ]);
        }
    }

    public function test_a_user_of_restaurant_b_only_counts_the_rows_of_b(): void
    {
        Order::factory()->for($this->restaurantA)->count(3)->create();
        Order::factory()->for($this->restaurantB)->count(2)->create();
        $this->catalogFor($this->restaurantA, 4);
        $this->catalogFor($this->restaurantB, 1);
        Ingredient::factory()->for($this->restaurantA)->count(2)->create();
        Ingredient::factory()->for($this->restaurantB)->create();

        $this->actingAs(User::factory()->create(['restaurant_id' => $this->restaurantB->id]));

        $this->assertSame(2, Order::count());
        $this->assertSame(1, Product::count());
        $this->assertSame(1, Category::count());
        $this->assertSame(1, Ingredient::count());
        $this->assertTrue(Order::all()->every(fn (Order $order) => $order->restaurant_id === $this->restaurantB->id));

        // Every row is still there, only fenced off.
        $this->assertSame(5, Order::withoutGlobalScope(RestaurantScope::class)->count());
        $this->assertSame(5, Product::withoutGlobalScope(RestaurantScope::class)->count());
    }

    public function test_a_row_of_another_restaurant_cannot_be_found_by_id(): void
    {
        $orderOfA = Order::factory()->for($this->restaurantA)->create();

        $this->actingAs(User::factory()->create(['restaurant_id' => $this->restaurantB->id]));

        $this->assertNull(Order::find($orderOfA->id));
        $this->assertNull(Order::where('number', $orderOfA->number)->first());
    }

    public function test_the_catalog_screens_of_restaurant_b_show_only_b_on_the_real_routes(): void
    {
        $this->catalogFor($this->restaurantA, 1);
        $this->catalogFor($this->restaurantB, 1);
        $productOfA = Product::withoutGlobalScope(RestaurantScope::class)->where('restaurant_id', $this->restaurantA->id)->firstOrFail();
        $productOfB = Product::withoutGlobalScope(RestaurantScope::class)->where('restaurant_id', $this->restaurantB->id)->firstOrFail();

        $this->actingAs(User::factory()->create([
            'role'          => UserRole::Admin,
            'restaurant_id' => $this->restaurantB->id,
        ]));

        $this->get(route('admin.catalog.products'))
            ->assertOk()
            ->assertSee($productOfB->name)
            ->assertDontSee($productOfA->name)
            ->assertDontSee('Undefined');

        $this->get(route('admin.catalog.products.edit', $productOfB))->assertOk();
        $this->get(route('admin.catalog.products.edit', $productOfA->id))->assertNotFound();
    }

    public function test_guests_see_the_default_restaurant(): void
    {
        $this->catalogFor($this->restaurantA, 2);
        $this->catalogFor($this->restaurantB, 3);

        $this->assertSame(2, Product::count());
    }

    public function test_a_row_created_without_restaurant_id_gets_the_current_restaurant(): void
    {
        $this->actingAs(User::factory()->create(['restaurant_id' => $this->restaurantB->id]));

        $category = Category::create(['name' => 'Bebidas', 'slug' => 'bebidas']);
        $entry = WaitlistEntry::factory()->create(['restaurant_id' => null]);

        $this->assertSame($this->restaurantB->id, $category->restaurant_id);
        $this->assertSame($this->restaurantB->id, $entry->restaurant_id);
    }

    public function test_an_explicit_restaurant_id_is_kept(): void
    {
        $this->actingAs(User::factory()->create(['restaurant_id' => $this->restaurantB->id]));

        $category = Category::create([
            'restaurant_id' => $this->restaurantA->id,
            'name'          => 'Sobremesas',
            'slug'          => 'sobremesas',
        ]);

        $this->assertSame($this->restaurantA->id, $category->fresh()->restaurant_id);
    }

    public function test_the_expiry_job_cancels_late_orders_of_every_restaurant(): void
    {
        $this->restaurantA->update(['confirmation_timeout_minutes' => 10]);
        $this->restaurantB->update(['confirmation_timeout_minutes' => 10]);

        $late = fn (Restaurant $restaurant) => Order::factory()->for($restaurant)->create([
            'status'     => OrderStatus::PendingConfirmation,
            'created_at' => now()->subMinutes(30),
        ]);
        $lateOfA = $late($this->restaurantA);
        $lateOfB = $late($this->restaurantB);

        app()->call([new ExpireUnconfirmedOrders, 'handle']);

        $this->assertSame(OrderStatus::Canceled, $lateOfA->fresh()->status);
        $this->assertSame(OrderStatus::Canceled, $lateOfB->fresh()->status);

        // The history row of the non-default restaurant is written too.
        $history = OrderStatusHistory::where('order_id', $lateOfB->id)->latest('id')->first();
        $this->assertSame(OrderStatus::Canceled, $history->to_status);
        $this->assertSame(ExpireUnconfirmedOrders::REASON, $history->notes);
    }

    public function test_run_as_switches_the_restaurant_and_restores_it_even_on_error(): void
    {
        $this->assertSame($this->restaurantA->id, CurrentRestaurant::id());

        $seen = CurrentRestaurant::runAs($this->restaurantB->id, fn () => CurrentRestaurant::id());
        $this->assertSame($this->restaurantB->id, $seen);
        $this->assertSame($this->restaurantA->id, CurrentRestaurant::id());

        try {
            CurrentRestaurant::runAs($this->restaurantB->id, function () {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($this->restaurantA->id, CurrentRestaurant::id());
    }
}
