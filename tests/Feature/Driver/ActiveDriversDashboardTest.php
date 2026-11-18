<?php

namespace Tests\Feature\Driver;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Livewire\Dashboard;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dashboard "Entregadores ativos" (EF-38): drivers with an active account,
 * available or on pause, and the deliveries each one holds right now in the
 * panel restaurant (accepted and waiting at the counter, or en route).
 */
class ActiveDriversDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager]);
    }

    private function driver(string $name, bool $available = true, bool $active = true): User
    {
        return User::factory()->create([
            'name'         => $name,
            'role'         => UserRole::Delivery,
            'is_available' => $available,
            'is_active'    => $active,
        ]);
    }

    private function delivery(User $driver, OrderStatus $status, ?Restaurant $restaurant = null): Order
    {
        return Order::factory()->for($restaurant ?? $this->restaurant)->create([
            'status'    => $status,
            'driver_id' => $driver->id,
            'ready_at'  => now(),
        ]);
    }

    /** @return array<string, array{state: string, orders: list<int>}> keyed by driver name */
    private function activeDrivers(): array
    {
        $this->actingAs($this->manager());

        return Livewire::test(Dashboard::class)->instance()->activeDrivers
            ->mapWithKeys(fn (array $driver) => [$driver['name'] => [
                'state'  => $driver['state'],
                'orders' => $driver['deliveries']->pluck('id')->all(),
            ]])
            ->all();
    }

    public function test_dashboard_shows_available_paused_and_busy_drivers_with_their_orders(): void
    {
        $ana = $this->driver('Ana Livre');
        $bruno = $this->driver('Bruno Pausado', available: false);
        $carla = $this->driver('Carla Rota');
        $diego = $this->driver('Diego Balcão');
        $enRoute = $this->delivery($carla, OrderStatus::OutForDelivery);
        $pickup = $this->delivery($diego, OrderStatus::ReadyForPickup);

        $this->actingAs($this->manager())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Entregadores ativos')
            ->assertSee('Ana Livre')
            ->assertSee('Bruno Pausado')
            ->assertSeeHtml('data-driver="'.$ana->id.'" data-driver-state="available"')
            ->assertSeeHtml('data-driver="'.$bruno->id.'" data-driver-state="paused"')
            ->assertSeeHtml('data-driver="'.$carla->id.'" data-driver-state="en_route"')
            ->assertSeeHtml('data-driver="'.$diego->id.'" data-driver-state="pickup"')
            ->assertSeeHtml('data-driver-order="'.$enRoute->id.'"')
            ->assertSee('#'.$enRoute->number.' · em rota')
            ->assertSee('#'.$pickup->number.' · a retirar')
            ->assertSee('2 com pedido · 1 disponível · 1 em pausa');
    }

    public function test_each_driver_comes_with_the_orders_they_hold_busy_first(): void
    {
        $this->driver('Ana Livre');
        $this->driver('Bruno Pausado', available: false);
        $carla = $this->driver('Carla Rota');
        $first = $this->delivery($carla, OrderStatus::OutForDelivery);
        $second = $this->delivery($carla, OrderStatus::ReadyForPickup);

        // Paused but still finishing a delivery: shown as busy, not as paused.
        $edu = $this->driver('Edu Terminando', available: false);
        $finishing = $this->delivery($edu, OrderStatus::OutForDelivery);

        // Delivered orders are not held any more.
        $this->delivery($this->driver('Fábio Entregou'), OrderStatus::Delivered);

        $this->assertSame([
            'Carla Rota'     => ['state' => 'en_route', 'orders' => [$first->id, $second->id]],
            'Edu Terminando' => ['state' => 'en_route', 'orders' => [$finishing->id]],
            'Ana Livre'      => ['state' => 'available', 'orders' => []],
            'Fábio Entregou' => ['state' => 'available', 'orders' => []],
            'Bruno Pausado'  => ['state' => 'paused', 'orders' => []],
        ], $this->activeDrivers());
    }

    public function test_deactivated_accounts_and_other_roles_are_not_listed(): void
    {
        $this->driver('Ana Livre');
        $this->driver('Gil Desativado', active: false);
        User::factory()->create(['name' => 'Helena Cozinha', 'role' => UserRole::Kitchen]);

        $this->assertSame(['Ana Livre'], array_keys($this->activeDrivers()));
    }

    public function test_orders_of_another_restaurant_are_not_shown(): void
    {
        $foreign = Restaurant::factory()->create();
        $carla = $this->driver('Carla Rota');
        $foreignOrder = $this->delivery($carla, OrderStatus::OutForDelivery, $foreign);

        $this->assertSame(['Carla Rota' => ['state' => 'available', 'orders' => []]], $this->activeDrivers());

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSeeHtml('data-driver-order="'.$foreignOrder->id.'"');
    }

    public function test_no_drivers_shows_the_empty_message(): void
    {
        $this->actingAs($this->manager())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Entregadores ativos')
            ->assertSee('Nenhum entregador cadastrado.');
    }

    public function test_drivers_and_their_orders_are_not_loaded_once_per_driver(): void
    {
        $manager = $this->manager();
        $this->delivery($this->driver('Carla Rota'), OrderStatus::OutForDelivery);

        $queriesFor = function () use ($manager): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($manager)->get('/dashboard')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        // Warm-up: the first request also resolves (and caches) the current restaurant id.
        $queriesFor();

        $withOneDriver = $queriesFor();

        $this->delivery($this->driver('Diego Balcão'), OrderStatus::ReadyForPickup);
        $this->driver('Ana Livre');
        $this->driver('Bruno Pausado', available: false);

        $this->assertSame($withOneDriver, $queriesFor());
    }
}
