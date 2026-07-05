<?php

namespace Tests\Feature\Driver;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DriverPanelTest extends TestCase
{
    use RefreshDatabase;

    private function driver(): User
    {
        return User::factory()->create(['role' => UserRole::Delivery]);
    }

    /** Driver panel scopes to the single restaurant via Restaurant::query()->value('id'). */
    private function restaurant(): Restaurant
    {
        return Restaurant::factory()->create();
    }

    public function test_driver_route_renders_ready_delivery_order(): void
    {
        $restaurant = $this->restaurant();
        Order::factory()->for($restaurant)->create([
            'status'        => OrderStatus::ReadyForPickup,
            'customer_name' => 'Maria Cliente',
            'ready_at'      => now(),
        ]);

        $this->actingAs($this->driver())
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertSee('Maria Cliente')
            ->assertSee('aguardando retirada')
            ->assertDontSee('Undefined');
    }

    public function test_driver_can_pick_up_a_ready_delivery_order(): void
    {
        $restaurant = $this->restaurant();
        $order = Order::factory()->for($restaurant)->create([
            'status'   => OrderStatus::ReadyForPickup,
            'ready_at' => now(),
        ]);

        $this->actingAs($this->driver());

        Volt::test('driver.index')
            ->call('pickUp', $order->id)
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::OutForDelivery, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id'    => $order->id,
            'from_status' => OrderStatus::ReadyForPickup->value,
            'to_status'   => OrderStatus::OutForDelivery->value,
        ]);
    }

    public function test_driver_can_mark_delivered_and_sets_delivered_at(): void
    {
        $restaurant = $this->restaurant();
        $order = Order::factory()->for($restaurant)->create([
            'status'       => OrderStatus::OutForDelivery,
            'delivered_at' => null,
        ]);

        $this->actingAs($this->driver());

        Volt::test('driver.index')
            ->call('markDelivered', $order->id)
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id'  => $order->id,
            'to_status' => OrderStatus::Delivered->value,
        ]);
    }

    public function test_driver_cannot_act_on_order_from_another_restaurant(): void
    {
        $restaurantA = $this->restaurant();
        $restaurantB = $this->restaurant();

        // rid() = Restaurant::query()->value('id') has no ORDER BY, so SQLite may
        // serve rows in index (e.g. slug) order — "first created" is NOT guaranteed
        // to be the panel's restaurant. Resolve what the panel will actually scope
        // to and place the foreign order in the other restaurant.
        $panelRestaurantId = Restaurant::query()->value('id');
        $foreignRestaurant = $panelRestaurantId === $restaurantA->id ? $restaurantB : $restaurantA;

        $foreignOrder = Order::factory()->for($foreignRestaurant)->create([
            'status'   => OrderStatus::ReadyForPickup,
            'ready_at' => now(),
        ]);

        $this->actingAs($this->driver());

        // pickUp scopes findOrFail by rid() (first restaurant), so a foreign
        // order is not found; the component swallows it into its `error` string
        // rather than a validation error — the security guarantee is that the
        // foreign order is never transitioned.
        $component = Volt::test('driver.index')
            ->call('pickUp', $foreignOrder->id);

        $this->assertNotNull($component->get('error'));
        $this->assertSame(OrderStatus::ReadyForPickup, $foreignOrder->fresh()->status);
    }
}
