<?php

namespace Tests\Feature;

use App\Enums\DeliveryType;
use App\Enums\DiningTableStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Enums\WaiterCallStatus;
use App\Livewire\Dashboard;
use App\Models\AddonGroup;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\TableSession;
use App\Models\User;
use App\Models\WaiterCall;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Multi-tenant 4/4 (EF-84): with two restaurants, a staff member of A never
 * sees nor acts on B's rows, screen by screen.
 *
 * Covered elsewhere (not repeated here):
 * - order by id, kitchen actions, waiter call acknowledge, delivery zones and
 *   team screen: TenantIsolationByIdTest (EF-83);
 * - model counts, product list/edit and the expiry job: RestaurantScopeTest (EF-82);
 * - waste entries and the waste report: WasteEntriesTest / WasteReportTest.
 */
class TenantLeakTest extends TestCase
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

    private function staffOf(Restaurant $restaurant, UserRole $role, array $attributes = []): User
    {
        return User::factory()->create(['role' => $role, 'restaurant_id' => $restaurant->id] + $attributes);
    }

    private function orderOf(Restaurant $restaurant, array $attributes = []): Order
    {
        return Order::factory()->for($restaurant)->create($attributes);
    }

    private function tableOf(Restaurant $restaurant, int $number): DiningTable
    {
        return DiningTable::create([
            'restaurant_id' => $restaurant->id,
            'number'        => $number,
            'status'        => DiningTableStatus::Occupied,
        ]);
    }

    // ── Pedidos ──

    public function test_order_lists_show_only_the_own_restaurant(): void
    {
        $this->orderOf($this->restaurantA, ['customer_name' => 'Cliente Alfa Ativo', 'status' => OrderStatus::InPreparation]);
        $this->orderOf($this->restaurantB, ['customer_name' => 'Cliente Beta Ativo', 'status' => OrderStatus::InPreparation]);
        $this->orderOf($this->restaurantA, ['customer_name' => 'Cliente Alfa Entregue', 'status' => OrderStatus::Delivered]);
        $this->orderOf($this->restaurantB, ['customer_name' => 'Cliente Beta Entregue', 'status' => OrderStatus::Delivered]);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Manager));

        $this->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('Cliente Alfa Ativo')
            ->assertDontSee('Cliente Beta Ativo')
            ->assertDontSee('Undefined');

        $this->get(route('admin.orders.in-progress'))
            ->assertOk()
            ->assertSee('Cliente Alfa Ativo')
            ->assertDontSee('Cliente Beta Ativo');

        $this->get(route('admin.orders.history'))
            ->assertOk()
            ->assertSee('Cliente Alfa Entregue')
            ->assertDontSee('Cliente Beta Entregue');
    }

    // ── Cozinha e chamados de garçom ──

    public function test_kitchen_queue_and_waiter_calls_show_only_the_own_restaurant(): void
    {
        $orderOfA = $this->orderOf($this->restaurantA, ['status' => OrderStatus::InPreparation]);
        $orderOfB = $this->orderOf($this->restaurantB, ['status' => OrderStatus::InPreparation]);

        $callOfA = WaiterCall::create([
            'restaurant_id'   => $this->restaurantA->id,
            'dining_table_id' => $this->tableOf($this->restaurantA, 3)->id,
            'status'          => WaiterCallStatus::Pending,
            'called_at'       => now(),
        ]);
        $callOfB = WaiterCall::create([
            'restaurant_id'   => $this->restaurantB->id,
            'dining_table_id' => $this->tableOf($this->restaurantB, 4)->id,
            'status'          => WaiterCallStatus::Pending,
            'called_at'       => now(),
        ]);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Kitchen));

        $kitchen = Volt::test('kitchen.index')->instance();

        $this->assertSame([$orderOfA->id], $kitchen->queueOrders->modelKeys());
        $this->assertSame([$callOfA->id], $kitchen->pendingWaiterCalls->modelKeys());
        $this->assertNotContains($callOfB->id, $kitchen->pendingWaiterCalls->modelKeys());
        $this->assertNotContains($orderOfB->id, $kitchen->queueOrders->modelKeys());
    }

    // ── Salão ──

    public function test_dining_tables_queue_and_tab_show_only_the_own_restaurant(): void
    {
        $tableOfA = $this->tableOf($this->restaurantA, 1);
        $tableOfB = $this->tableOf($this->restaurantB, 2);

        $waitingA = WaitlistEntry::factory()->for($this->restaurantA)->create(['party_name' => 'Família Alfa']);
        $waitingB = WaitlistEntry::factory()->for($this->restaurantB)->create(['party_name' => 'Família Beta']);

        $sessionOfB = TableSession::factory()->for($this->restaurantB)->create(['dining_table_id' => $tableOfB->id]);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Attendant));

        $this->assertSame([$tableOfA->id], Volt::test('dining.tables')->instance()->tables->modelKeys());

        $waiting = Volt::test('dining.queue')->instance()->waiting->modelKeys();
        $this->assertSame([$waitingA->id], $waiting);
        $this->assertNotContains($waitingB->id, $waiting);

        $this->get(route('admin.dining.queue'))
            ->assertOk()
            ->assertSee('Família Alfa')
            ->assertDontSee('Família Beta');

        // The tab (comanda) of B's table session does not exist for A.
        $this->get(route('admin.dining.tab', $sessionOfB->id))->assertNotFound();
    }

    // ── Cardápio ──

    public function test_catalog_lists_show_only_the_own_restaurant(): void
    {
        Category::create(['restaurant_id' => $this->restaurantA->id, 'name' => 'Categoria Alfa', 'slug' => 'categoria-alfa']);
        Category::create(['restaurant_id' => $this->restaurantB->id, 'name' => 'Categoria Beta', 'slug' => 'categoria-beta']);

        Ingredient::factory()->for($this->restaurantA)->create(['name' => 'Insumo Alfa']);
        Ingredient::factory()->for($this->restaurantB)->create(['name' => 'Insumo Beta']);

        $productOfB = Product::withoutGlobalScopes()->create([
            'restaurant_id' => $this->restaurantB->id,
            'category_id'   => Category::withoutGlobalScopes()->where('name', 'Categoria Beta')->value('id'),
            'name'          => 'Produto Beta',
            'price'         => 10,
        ]);
        $groupOfB = AddonGroup::create([
            'product_id'  => $productOfB->id,
            'name'        => 'Adicionais Beta',
            'min_choices' => 0,
            'max_choices' => 1,
        ]);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Manager));

        $this->get(route('admin.catalog.categories'))
            ->assertOk()
            ->assertSee('Categoria Alfa')
            ->assertDontSee('Categoria Beta');

        $this->get(route('admin.catalog.ingredients'))
            ->assertOk()
            ->assertSee('Insumo Alfa')
            ->assertDontSee('Insumo Beta');

        $this->get(route('admin.catalog.addons'))
            ->assertOk()
            ->assertDontSee('Adicionais Beta');

        // B's addon group cannot be opened nor deleted from A.
        Volt::test('catalog.addons')->call('openEditGroup', $groupOfB->id)->assertNotFound();
        Volt::test('catalog.addons')->call('deleteGroup', $groupOfB->id)->assertNotFound();
        $this->assertDatabaseHas('addon_groups', ['id' => $groupOfB->id]);
    }

    // ── Clientes ──

    public function test_customers_list_shows_only_customers_of_the_own_restaurant(): void
    {
        $customerOfA = Customer::factory()->create(['name' => 'Cliente Só Alfa']);
        $customerOfB = Customer::factory()->create(['name' => 'Cliente Só Beta']);
        $customerOfBoth = Customer::factory()->create(['name' => 'Cliente Dos Dois']);

        $this->orderOf($this->restaurantA, ['customer_id' => $customerOfA->id]);
        $this->orderOf($this->restaurantB, ['customer_id' => $customerOfB->id]);
        $this->orderOf($this->restaurantA, ['customer_id' => $customerOfBoth->id, 'total' => 30]);
        $this->orderOf($this->restaurantB, ['customer_id' => $customerOfBoth->id, 'total' => 50]);
        $this->orderOf($this->restaurantB, ['customer_id' => $customerOfBoth->id, 'total' => 70]);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Manager));

        $this->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Cliente Só Alfa')
            ->assertSee('Cliente Dos Dois')
            ->assertDontSee('Cliente Só Beta')
            ->assertDontSee('Undefined');

        $customers = Volt::test('customers.index')->instance()->customers->getCollection()->keyBy('id');

        $this->assertEqualsCanonicalizing([$customerOfA->id, $customerOfBoth->id], $customers->keys()->all());

        // Counts and totals only add up A's orders.
        $both = $customers->get($customerOfBoth->id);
        $this->assertSame(1, (int) $both->order_count);
        $this->assertEquals(30, (float) $both->total_spent);
    }

    // ── Dashboard e entregador ──

    public function test_dashboard_lists_only_drivers_of_the_own_restaurant(): void
    {
        $this->staffOf($this->restaurantA, UserRole::Delivery, ['name' => 'Entregador Alfa']);
        $this->staffOf($this->restaurantB, UserRole::Delivery, ['name' => 'Entregador Beta']);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Manager));

        $names = Livewire::test(Dashboard::class)->instance()->activeDrivers->pluck('name')->all();

        $this->assertSame(['Entregador Alfa'], $names);
    }

    public function test_driver_panel_offers_and_accepts_only_deliveries_of_the_own_restaurant(): void
    {
        $ready = ['status' => OrderStatus::ReadyForPickup, 'delivery_type' => DeliveryType::Delivery, 'ready_at' => now()];
        $orderOfA = $this->orderOf($this->restaurantA, $ready);
        $orderOfB = $this->orderOf($this->restaurantB, $ready);

        $this->actingAs($this->staffOf($this->restaurantA, UserRole::Delivery));

        $panel = Volt::test('driver.index');

        $this->assertSame([$orderOfA->id], $panel->instance()->available->modelKeys());

        $panel->call('accept', $orderOfB->id);

        $this->assertNull($orderOfB->fresh()->driver_id);
    }
}
