<?php

namespace Tests\Feature\Driver;

use App\Actions\Delivery\AcceptDelivery;
use App\Actions\Delivery\DeclineDelivery;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Driver availability (EF-37): a driver toggles Disponível / Em pausa on the driver
 * panel (users.is_available). On pause there are no offers — "Disponíveis" is empty
 * and accepting/declining is refused on the server — but the deliveries already
 * accepted ("Minhas entregas") stay visible and can be finished.
 */
class DriverAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
    }

    private function driver(bool $available = true): User
    {
        return User::factory()->create(['role' => UserRole::Delivery, 'is_available' => $available]);
    }

    private function ready(array $attributes = []): Order
    {
        return Order::factory()->for($this->restaurant)->create(array_merge([
            'status'   => OrderStatus::ReadyForPickup,
            'ready_at' => now(),
        ], $attributes));
    }

    public function test_new_users_are_available_by_default(): void
    {
        $user = User::factory()->create(['role' => UserRole::Delivery]);

        $this->assertTrue($user->is_available);
        $this->assertTrue($user->fresh()->is_available);
    }

    public function test_driver_on_pause_sees_no_available_orders(): void
    {
        $this->ready(['customer_name' => 'Maria Disponível']);
        $this->actingAs($this->driver(available: false));

        $panel = Volt::test('driver.index')
            ->assertSee('Em pausa')
            ->assertSee('Você está em pausa e não recebe novas entregas.')
            ->assertDontSee('Maria Disponível')
            ->assertDontSee('Aceitar');

        $this->assertSame([], $panel->instance()->available->pluck('id')->all());
    }

    public function test_driver_on_pause_cannot_accept_nor_decline(): void
    {
        $driver = $this->driver(available: false);
        $order = $this->ready();
        $this->actingAs($driver);

        Volt::test('driver.index')
            ->call('accept', $order->id)
            ->assertSet('error', AcceptDelivery::ON_PAUSE_MESSAGE)
            ->call('decline', $order->id)
            ->assertSet('error', AcceptDelivery::ON_PAUSE_MESSAGE);

        $this->assertNull($order->fresh()->driver_id);
        $this->assertDatabaseCount('delivery_declines', 0);

        // The rule lives in the actions, not only in the screen.
        $this->expectException(ValidationException::class);
        app(DeclineDelivery::class)->execute($this->restaurant->id, $order->id, $driver);
    }

    public function test_accept_action_refuses_a_driver_on_pause(): void
    {
        $order = $this->ready();

        try {
            app(AcceptDelivery::class)->execute($this->restaurant->id, $order->id, $this->driver(available: false));
            $this->fail('A driver on pause accepted a delivery.');
        } catch (ValidationException $e) {
            $this->assertSame(AcceptDelivery::ON_PAUSE_MESSAGE, $e->errors()['order'][0]);
        }

        $this->assertNull($order->fresh()->driver_id);
    }

    public function test_driver_on_pause_keeps_and_finishes_own_deliveries(): void
    {
        $driver = $this->driver(available: false);
        $order = $this->ready(['driver_id' => $driver->id, 'customer_name' => 'João Meu']);
        $this->actingAs($driver);

        Volt::test('driver.index')
            ->assertSee('João Meu')
            ->assertSee('Retirar pedido')
            ->call('pickUp', $order->id)
            ->assertSet('error', null)
            ->call('markDelivered', $order->id)
            ->assertSet('error', null);

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_toggling_persists_and_reflects_on_the_screen(): void
    {
        $driver = $this->driver();
        $order = $this->ready(['customer_name' => 'Maria Disponível']);
        $this->actingAs($driver);

        $panel = Volt::test('driver.index')
            ->assertSee('Disponível')
            ->assertSee('Maria Disponível')
            ->call('toggleAvailability')
            ->assertSee('Em pausa')
            ->assertSee('Você está em pausa e não recebe novas entregas.')
            ->assertDontSee('Maria Disponível');

        $this->assertFalse($driver->fresh()->is_available);
        $this->assertSame([], $panel->instance()->available->pluck('id')->all());

        $panel->call('toggleAvailability')
            ->assertSee('Maria Disponível')
            ->assertDontSee('Você está em pausa e não recebe novas entregas.');

        $this->assertTrue($driver->fresh()->is_available);
        $this->assertSame([$order->id], $panel->instance()->available->pluck('id')->all());
    }

    public function test_driver_route_renders_the_availability_toggle(): void
    {
        $this->actingAs($this->driver(available: false))
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Em pausa')
            ->assertSee('Você está em pausa e não recebe novas entregas.');

        $this->actingAs($this->driver())
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertSee('Disponível')
            ->assertDontSee('Você está em pausa e não recebe novas entregas.');
    }
}
