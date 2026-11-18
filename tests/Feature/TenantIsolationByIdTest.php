<?php

namespace Tests\Feature;

use App\Enums\DiningTableStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WaiterCallStatus;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WaiterCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Multi-tenant 3/4 (EF-83): screens that load a row by id only find rows of the
 * user's restaurant (404 otherwise), and the policies refuse another
 * restaurant's rows.
 */
class TenantIsolationByIdTest extends TestCase
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

    private function staffOf(Restaurant $restaurant, UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'restaurant_id' => $restaurant->id]);
    }

    private function waiterCallOf(Restaurant $restaurant): WaiterCall
    {
        $table = DiningTable::create([
            'restaurant_id' => $restaurant->id,
            'number'        => 7,
            'status'        => DiningTableStatus::Occupied,
        ]);

        return WaiterCall::create([
            'restaurant_id'   => $restaurant->id,
            'dining_table_id' => $table->id,
            'status'          => WaiterCallStatus::Pending,
            'called_at'       => now(),
        ]);
    }

    private function zoneOf(Restaurant $restaurant, string $name): DeliveryZone
    {
        return DeliveryZone::create([
            'restaurant_id'     => $restaurant->id,
            'name'              => $name,
            'city'              => 'São Paulo',
            'fee'               => 5,
            'estimated_minutes' => 30,
            'is_active'         => true,
        ]);
    }

    // ── Pedidos ──

    public function test_a_user_of_a_opening_an_order_of_b_gets_404(): void
    {
        $orderOfA = Order::factory()->for($this->restaurantA)->create();
        $orderOfB = Order::factory()->for($this->restaurantB)->create();

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Manager));

        $this->get(route('admin.orders.show', $orderOfB))->assertNotFound();
        $this->get(route('admin.orders.show', $orderOfA))->assertOk()->assertDontSee('Undefined');
    }

    public function test_order_and_product_policies_refuse_another_restaurant(): void
    {
        $orderOfB = Order::factory()->for($this->restaurantB)->create();
        $managerOfA = $this->staffOf($this->restaurantA, UserRole::Manager);
        $managerOfB = $this->staffOf($this->restaurantB, UserRole::Manager);

        foreach (['view', 'updateStatus', 'cancel', 'transitionInKitchen'] as $ability) {
            $this->assertFalse(Gate::forUser($managerOfA)->allows($ability, $orderOfB), $ability);
            $this->assertTrue(Gate::forUser($managerOfB)->allows($ability, $orderOfB), $ability);
        }
    }

    public function test_staff_without_a_restaurant_yet_still_work_in_the_default_one(): void
    {
        $orderOfA = Order::factory()->for($this->restaurantA)->create();
        $orderOfB = Order::factory()->for($this->restaurantB)->create();
        $unassigned = User::factory()->create(['role' => UserRole::Manager]); // restaurant_id null

        $this->assertTrue(Gate::forUser($unassigned)->allows('view', $orderOfA));
        $this->assertFalse(Gate::forUser($unassigned)->allows('view', $orderOfB));
    }

    // ── Cozinha ──

    public function test_the_kitchen_of_a_cannot_change_the_status_of_an_order_of_b(): void
    {
        $orderOfB = Order::factory()->for($this->restaurantB)->confirmed()->create();

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Kitchen));

        Volt::test('kitchen.index')->call('startPreparing', $orderOfB->id)->assertStatus(404);

        $this->assertSame(OrderStatus::Confirmed, $orderOfB->fresh()->status);
    }

    public function test_the_kitchen_of_a_cannot_acknowledge_a_waiter_call_of_b(): void
    {
        $callOfB = $this->waiterCallOf($this->restaurantB);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Kitchen));

        Volt::test('kitchen.index')->call('acknowledgeWaiterCall', $callOfB->id)->assertStatus(404);

        $this->assertSame(WaiterCallStatus::Pending, $callOfB->fresh()->status);
        $this->assertNull($callOfB->fresh()->acknowledged_at);
    }

    public function test_the_kitchen_still_acknowledges_its_own_waiter_call(): void
    {
        $callOfA = $this->waiterCallOf($this->restaurantA);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Kitchen));

        Volt::test('kitchen.index')->call('acknowledgeWaiterCall', $callOfA->id)->assertHasNoErrors();

        $this->assertSame(WaiterCallStatus::Acknowledged, $callOfA->fresh()->status);
        $this->assertNotNull($callOfA->fresh()->acknowledged_at);
    }

    // ── Equipe ──

    public function test_the_team_screen_lists_only_the_own_restaurant(): void
    {
        $adminOfA = $this->staffOf($this->restaurantA, UserRole::Admin);
        User::factory()->create(['name' => 'Ana Do A', 'restaurant_id' => $this->restaurantA->id]);
        User::factory()->create(['name' => 'Bruno Do B', 'restaurant_id' => $this->restaurantB->id]);

        $this->actingAs($adminOfA);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Ana Do A')
            ->assertDontSee('Bruno Do B')
            ->assertDontSee('Undefined');
    }

    public function test_an_admin_of_a_cannot_change_a_member_of_b(): void
    {
        $memberOfB = $this->staffOf($this->restaurantB, UserRole::Kitchen);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Admin));

        Volt::test('users.index')->call('toggleActive', $memberOfB->id)->assertStatus(404);
        Volt::test('users.index')->call('updateRole', $memberOfB->id, UserRole::Admin->value)->assertStatus(404);

        $memberOfB->refresh();
        $this->assertTrue($memberOfB->is_active);
        $this->assertSame(UserRole::Kitchen, $memberOfB->role);
        $this->assertFalse(Gate::forUser($this->staffOf($this->restaurantA, UserRole::Admin))->allows('update', $memberOfB));
    }

    public function test_an_admin_still_manages_the_own_team(): void
    {
        $memberOfA = $this->staffOf($this->restaurantA, UserRole::Kitchen);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Admin));

        Volt::test('users.index')->call('updateRole', $memberOfA->id, UserRole::Attendant->value);

        $this->assertSame(UserRole::Attendant, $memberOfA->fresh()->role);
    }

    // ── Zonas de entrega ──

    public function test_delivery_zones_show_only_the_own_restaurant(): void
    {
        $this->zoneOf($this->restaurantA, 'Centro do A');
        $this->zoneOf($this->restaurantB, 'Bairro do B');

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Manager));

        $this->get(route('admin.settings.delivery'))
            ->assertOk()
            ->assertSee('Centro do A')
            ->assertDontSee('Bairro do B')
            ->assertDontSee('Undefined');
    }

    public function test_a_zone_of_b_cannot_be_edited_toggled_or_deleted_from_a(): void
    {
        $zoneOfB = $this->zoneOf($this->restaurantB, 'Bairro do B');

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Manager));

        Volt::test('settings.delivery')->call('openEdit', $zoneOfB->id)->assertStatus(404);
        Volt::test('settings.delivery')->call('toggleActive', $zoneOfB->id)->assertStatus(404);
        Volt::test('settings.delivery')->call('delete', $zoneOfB->id)->assertStatus(404);

        Volt::test('settings.delivery')
            ->set('editingId', (string) $zoneOfB->id)
            ->set('name', 'Sequestrada')
            ->set('city', 'São Paulo')
            ->set('fee', '1')
            ->set('estimatedMinutes', '10')
            ->call('save')
            ->assertStatus(404);

        $zoneOfB->refresh();
        $this->assertSame('Bairro do B', $zoneOfB->name);
        $this->assertTrue($zoneOfB->is_active);
        $this->assertSame($this->restaurantB->id, $zoneOfB->restaurant_id);
    }
}
