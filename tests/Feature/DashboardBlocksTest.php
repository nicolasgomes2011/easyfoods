<?php

namespace Tests\Feature;

use App\Enums\DiningTableStatus;
use App\Enums\OrderStatus;
use App\Livewire\Dashboard;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2 close-out dashboard blocks: tables snapshot, deliveries en route,
 * quick actions (incl. the tables link that didn't exist) and today-vs-baseline
 * comparison badges.
 */
class DashboardBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function table(Restaurant $restaurant, DiningTableStatus $status, int $number): DiningTable
    {
        return DiningTable::create([
            'restaurant_id' => $restaurant->id,
            'number'        => $number,
            'status'        => $status,
        ]);
    }

    public function test_dashboard_renders_the_new_blocks_on_the_real_route(): void
    {
        Restaurant::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Salão agora')
            ->assertSee('Entregas')
            ->assertSee('Ações rápidas')
            ->assertSee(route('admin.dining.tables'))
            ->assertSee(route('admin.dining.queue'))
            ->assertDontSee('Undefined');
    }

    public function test_tables_snapshot_counts_by_status(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->table($restaurant, DiningTableStatus::Free, 1);
        $this->table($restaurant, DiningTableStatus::Free, 2);
        $this->table($restaurant, DiningTableStatus::Occupied, 3);
        $this->table($restaurant, DiningTableStatus::Reserved, 4);

        $this->actingAs(User::factory()->create());

        $snapshot = Livewire::test(Dashboard::class)->instance()->tablesSnapshot;

        $this->assertSame(['free' => 2, 'occupied' => 1, 'reserved' => 1, 'total' => 4], $snapshot);
    }

    public function test_out_for_delivery_count_reflects_orders_en_route(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->for($restaurant)->count(2)->create(['status' => OrderStatus::OutForDelivery]);
        Order::factory()->for($restaurant)->create(['status' => OrderStatus::InPreparation]);

        $this->actingAs(User::factory()->create());

        $this->assertSame(2, Livewire::test(Dashboard::class)->instance()->outForDeliveryCount);
    }

    public function test_comparison_deltas_use_yesterday_as_baseline(): void
    {
        $restaurant = Restaurant::factory()->create();

        // Yesterday: 2 completed orders totalling R$100.
        Order::factory()->for($restaurant)->count(2)->create([
            'status'     => OrderStatus::Completed,
            'total'      => 50.00,
            'created_at' => today()->subDay()->addHours(12),
        ]);

        // Today: 3 completed orders totalling R$300 → orders +50%, revenue +200%.
        Order::factory()->for($restaurant)->count(3)->create([
            'status'     => OrderStatus::Completed,
            'total'      => 100.00,
            'created_at' => today()->addHours(10),
        ]);

        $this->actingAs(User::factory()->create());

        $comparisons = Livewire::test(Dashboard::class)->instance()->comparisons;

        $this->assertSame(50, $comparisons['orders']['yesterday']);
        $this->assertSame(200, $comparisons['revenue']['yesterday']);
    }

    public function test_comparison_badges_hide_when_there_is_no_baseline(): void
    {
        $restaurant = Restaurant::factory()->create();
        Order::factory()->for($restaurant)->create([
            'status'     => OrderStatus::Completed,
            'total'      => 80.00,
            'created_at' => today()->addHours(9),
        ]);

        $this->actingAs(User::factory()->create());

        $comparisons = Livewire::test(Dashboard::class)->instance()->comparisons;
        $this->assertNull($comparisons['orders']['yesterday']);
        $this->assertNull($comparisons['revenue']['yesterday']);

        // Division-by-zero guard renders no badge at all.
        $this->get('/dashboard')->assertOk()->assertDontSee('vs ontem');
    }

    public function test_dashboard_blocks_are_scoped_to_the_panel_restaurant(): void
    {
        // Resolve which restaurant the panel scopes to (value('id') is
        // planner-order-dependent, not first-created — BUG-007).
        $x = Restaurant::factory()->create();
        $y = Restaurant::factory()->create();
        $panelId = Restaurant::query()->value('id');
        [$panel, $foreign] = $panelId === $x->id ? [$x, $y] : [$y, $x];

        $this->table($panel, DiningTableStatus::Free, 1);
        $this->table($foreign, DiningTableStatus::Occupied, 1);
        $this->table($foreign, DiningTableStatus::Occupied, 2);

        Order::factory()->for($foreign)->create(['status' => OrderStatus::OutForDelivery]);

        $this->actingAs(User::factory()->create());

        $component = Livewire::test(Dashboard::class)->instance();

        $this->assertSame(['free' => 1, 'occupied' => 0, 'reserved' => 0, 'total' => 1], $component->tablesSnapshot);
        $this->assertSame(0, $component->outForDeliveryCount);
    }
}
