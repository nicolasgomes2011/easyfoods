<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Livewire\Dashboard;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Tempo médio de preparo" is kitchen time: start of preparation → ready.
 * The wait for confirmation before the kitchen starts must not count.
 */
class DashboardPrepTimeTest extends TestCase
{
    use RefreshDatabase;

    private function readyOrder(Restaurant $restaurant, int $createdAgo, ?int $prepStartedAgo, int $readyAgo): Order
    {
        return Order::factory()->for($restaurant)->create([
            'status'                 => OrderStatus::ReadyForPickup,
            'created_at'             => now()->subMinutes($createdAgo),
            'preparation_started_at' => $prepStartedAgo === null ? null : now()->subMinutes($prepStartedAgo),
            'ready_at'               => now()->subMinutes($readyAgo),
        ]);
    }

    public function test_average_prep_time_counts_only_kitchen_time(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(14));
        $restaurant = Restaurant::factory()->create();

        // Waited 40 min for confirmation, then 15 min in the kitchen.
        $this->readyOrder($restaurant, createdAgo: 60, prepStartedAgo: 20, readyAgo: 5);
        // Confirmed fast, 9 min in the kitchen.
        $this->readyOrder($restaurant, createdAgo: 30, prepStartedAgo: 25, readyAgo: 16);
        // Legacy order without a recorded start of preparation: left out of the average.
        $this->readyOrder($restaurant, createdAgo: 90, prepStartedAgo: null, readyAgo: 10);

        $this->actingAs(User::factory()->create());

        // (15 + 9) / 2 = 12 — the old created_at → ready_at formula gave 51.
        $this->assertSame(12, Livewire::test(Dashboard::class)->instance()->avgPrepMinutes);
    }

    public function test_average_prep_time_is_empty_without_kitchen_data(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(14));
        $restaurant = Restaurant::factory()->create();
        $this->readyOrder($restaurant, createdAgo: 90, prepStartedAgo: null, readyAgo: 10);

        $this->actingAs(User::factory()->create());

        $this->assertNull(Livewire::test(Dashboard::class)->instance()->avgPrepMinutes);
        $this->get('/dashboard')->assertOk()->assertDontSee('Undefined');
    }
}
