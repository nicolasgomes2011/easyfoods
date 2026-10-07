<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Operational screens must refresh themselves. Polling is declared with
 * `wire:poll` on the component root — Livewire has no class-level poll
 * attribute, so a `#[Poll]` would be silently ignored.
 */
class LivePollingTest extends TestCase
{
    use RefreshDatabase;

    public static function panelRoutes(): array
    {
        return [
            'kitchen'            => ['admin.kitchen.index'],
            'orders in progress' => ['admin.orders.in-progress'],
            'waitlist queue'     => ['admin.dining.queue'],
            'driver queue'       => ['admin.driver.index'],
        ];
    }

    #[DataProvider('panelRoutes')]
    public function test_operational_panel_polls_every_30_seconds(string $route): void
    {
        Restaurant::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get(route($route))
            ->assertOk()
            ->assertSee('wire:poll.30s', false);
    }

    public function test_order_tracking_polls_while_the_order_is_active(): void
    {
        $order = Order::factory()->withStatus(OrderStatus::InPreparation)->create();

        $this->get(route('store.order.tracking', $order->token))
            ->assertOk()
            ->assertSee('wire:poll.10s', false);
    }

    public function test_order_tracking_stops_polling_once_the_order_is_final(): void
    {
        $order = Order::factory()->withStatus(OrderStatus::Completed)->create();

        $this->get(route('store.order.tracking', $order->token))
            ->assertOk()
            ->assertDontSee('wire:poll', false);
    }
}
