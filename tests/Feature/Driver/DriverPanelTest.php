<?php

namespace Tests\Feature\Driver;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\DeliveryDecline;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Driver panel (EF-36): a delivery order ready to leave is offered under
 * "Disponíveis" until a driver accepts it (orders.driver_id). From then on it is
 * that driver's ("Minhas entregas"): only the owner picks it up and marks it
 * delivered. Declining is recorded and only hides the order from who declined.
 */
class DriverPanelTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
    }

    private function driver(): User
    {
        return User::factory()->create(['role' => UserRole::Delivery]);
    }

    private function ready(array $attributes = []): Order
    {
        return Order::factory()->for($this->restaurant)->create(array_merge([
            'status'   => OrderStatus::ReadyForPickup,
            'ready_at' => now(),
        ], $attributes));
    }

    public function test_driver_route_renders_available_and_own_deliveries(): void
    {
        $driver = $this->driver();
        $this->ready(['customer_name' => 'Maria Disponível']);
        $this->ready(['customer_name' => 'João Meu', 'driver_id' => $driver->id]);

        $this->actingAs($driver)
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Minhas entregas')
            ->assertSee('Disponíveis')
            ->assertSee('Maria Disponível')
            ->assertSee('João Meu')
            ->assertSee('Aceitar')
            ->assertSee('Recusar')
            ->assertSee('Retirar pedido');
    }

    public function test_accepting_sets_the_driver_and_moves_the_order_to_my_deliveries(): void
    {
        $driver = $this->driver();
        $order = $this->ready();
        $this->actingAs($driver);

        $panel = Volt::test('driver.index')
            ->call('accept', $order->id)
            ->assertSet('error', null);

        $this->assertSame($driver->id, $order->fresh()->driver_id);
        $this->assertSame(OrderStatus::ReadyForPickup, $order->fresh()->status);
        $this->assertSame([$order->id], $panel->instance()->myPickups->pluck('id')->all());
        $this->assertSame([], $panel->instance()->available->pluck('id')->all());
    }

    public function test_declining_records_it_without_owner_and_keeps_the_order_available_to_others(): void
    {
        $declined = $this->driver();
        $other = $this->driver();
        $order = $this->ready();

        $this->actingAs($declined);
        $panel = Volt::test('driver.index')
            ->call('decline', $order->id)
            ->assertSet('error', null);

        $this->assertNull($order->fresh()->driver_id);
        $this->assertDatabaseHas('delivery_declines', [
            'restaurant_id' => $this->restaurant->id,
            'order_id'      => $order->id,
            'user_id'       => $declined->id,
        ]);
        $this->assertSame([], $panel->instance()->available->pluck('id')->all());

        $this->actingAs($other);
        $this->assertSame([$order->id], Volt::test('driver.index')->instance()->available->pluck('id')->all());
    }

    public function test_declining_twice_records_once(): void
    {
        $order = $this->ready();
        $this->actingAs($this->driver());

        Volt::test('driver.index')->call('decline', $order->id)->call('decline', $order->id);

        $this->assertSame(1, DeliveryDecline::where('order_id', $order->id)->count());
    }

    public function test_an_order_already_taken_cannot_be_accepted_by_another_driver(): void
    {
        $first = $this->driver();
        $order = $this->ready(['driver_id' => $first->id]);

        $this->actingAs($this->driver());
        $panel = Volt::test('driver.index')->call('accept', $order->id);

        $this->assertNotNull($panel->get('error'));
        $this->assertSame($first->id, $order->fresh()->driver_id);
    }

    public function test_only_delivery_orders_ready_to_leave_are_available(): void
    {
        $available = $this->ready();
        $this->ready(['delivery_type' => DeliveryType::Pickup]);
        Order::factory()->for($this->restaurant)->create(['status' => OrderStatus::InPreparation]);
        $this->actingAs($this->driver());

        $panel = Volt::test('driver.index');

        $this->assertSame([$available->id], $panel->instance()->available->pluck('id')->all());

        $pickupOrder = Order::where('delivery_type', DeliveryType::Pickup->value)->first();
        $panel->call('accept', $pickupOrder->id);
        $this->assertNotNull($panel->get('error'));
        $this->assertNull($pickupOrder->fresh()->driver_id);
    }

    public function test_owner_picks_up_and_marks_delivered(): void
    {
        $driver = $this->driver();
        $order = $this->ready(['driver_id' => $driver->id, 'delivered_at' => null]);
        $this->actingAs($driver);

        Volt::test('driver.index')
            ->call('pickUp', $order->id)
            ->assertSet('error', null)
            ->call('markDelivered', $order->id)
            ->assertSet('error', null);

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id'    => $order->id,
            'from_status' => OrderStatus::ReadyForPickup->value,
            'to_status'   => OrderStatus::OutForDelivery->value,
            'changed_by'  => $driver->id,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id'  => $order->id,
            'to_status' => OrderStatus::Delivered->value,
        ]);
    }

    public function test_driver_a_cannot_mark_delivered_an_order_of_driver_b(): void
    {
        $driverB = $this->driver();
        $order = Order::factory()->for($this->restaurant)->create([
            'status'    => OrderStatus::OutForDelivery,
            'driver_id' => $driverB->id,
        ]);

        $this->actingAs($this->driver());
        $panel = Volt::test('driver.index')->call('markDelivered', $order->id);

        $this->assertNotNull($panel->get('error'));
        $this->assertSame(OrderStatus::OutForDelivery, $order->fresh()->status);
        $this->assertNull($order->fresh()->delivered_at);
    }

    public function test_driver_a_cannot_pick_up_an_order_of_driver_b_nor_an_unaccepted_one(): void
    {
        $theirs = $this->ready(['driver_id' => $this->driver()->id]);
        $unaccepted = $this->ready();

        $this->actingAs($this->driver());
        $panel = Volt::test('driver.index')->call('pickUp', $theirs->id);
        $this->assertNotNull($panel->get('error'));

        $panel->call('pickUp', $unaccepted->id);
        $this->assertNotNull($panel->get('error'));

        $this->assertSame(OrderStatus::ReadyForPickup, $theirs->fresh()->status);
        $this->assertSame(OrderStatus::ReadyForPickup, $unaccepted->fresh()->status);
    }

    public function test_an_order_en_route_without_driver_from_before_can_be_taken_over_and_delivered(): void
    {
        $order = Order::factory()->for($this->restaurant)->create([
            'status'    => OrderStatus::OutForDelivery,
            'driver_id' => null,
        ]);
        $driver = $this->driver();
        $this->actingAs($driver);

        $panel = Volt::test('driver.index');
        $this->assertSame([$order->id], $panel->instance()->available->pluck('id')->all());

        $panel->call('accept', $order->id)
            ->call('markDelivered', $order->id)
            ->assertSet('error', null);

        $this->assertSame($driver->id, $order->fresh()->driver_id);
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_my_en_route_orders_are_listed_by_departure_time_not_last_update(): void
    {
        $driver = $this->driver();
        // Left first, but touched recently (e.g. a note edit) — must still come first.
        $leftFirst = Order::factory()->for($this->restaurant)->create([
            'status'              => OrderStatus::OutForDelivery,
            'driver_id'           => $driver->id,
            'out_for_delivery_at' => now()->subMinutes(30),
            'updated_at'          => now(),
        ]);
        $leftLater = Order::factory()->for($this->restaurant)->create([
            'status'              => OrderStatus::OutForDelivery,
            'driver_id'           => $driver->id,
            'out_for_delivery_at' => now()->subMinutes(10),
            'updated_at'          => now()->subHour(),
        ]);
        // Another driver's order is not mine.
        Order::factory()->for($this->restaurant)->create([
            'status'    => OrderStatus::OutForDelivery,
            'driver_id' => $this->driver()->id,
        ]);

        $this->actingAs($driver);

        $ids = Volt::test('driver.index')->instance()->myEnRoute->pluck('id')->all();

        $this->assertSame([$leftFirst->id, $leftLater->id], $ids);
    }

    public function test_driver_cannot_act_on_order_from_another_restaurant(): void
    {
        $other = Restaurant::factory()->create();
        $this->assertSame($this->restaurant->id, CurrentRestaurant::id());

        $driver = $this->driver();
        $foreignOrder = Order::factory()->for($other)->create([
            'status'   => OrderStatus::ReadyForPickup,
            'ready_at' => now(),
        ]);
        $foreignOwned = Order::factory()->for($other)->create([
            'status'    => OrderStatus::OutForDelivery,
            'driver_id' => $driver->id,
        ]);

        $this->actingAs($driver);
        $panel = Volt::test('driver.index');

        $this->assertSame([], $panel->instance()->available->pluck('id')->all());
        $this->assertSame([], $panel->instance()->myEnRoute->pluck('id')->all());

        $panel->call('accept', $foreignOrder->id);
        $this->assertNotNull($panel->get('error'));
        $panel->call('decline', $foreignOrder->id);
        $this->assertNotNull($panel->get('error'));
        $panel->call('markDelivered', $foreignOwned->id);
        $this->assertNotNull($panel->get('error'));

        $this->assertNull($foreignOrder->fresh()->driver_id);
        $this->assertSame(0, DeliveryDecline::count());
        $this->assertSame(OrderStatus::OutForDelivery, $foreignOwned->fresh()->status);
    }
}
