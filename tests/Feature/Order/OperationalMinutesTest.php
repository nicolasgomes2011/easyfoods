<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Carbon 3 returns diffInMinutes() as a signed float. Operational screens must
 * still show whole, positive minutes ("12 min", never "12.58 min" or "-45 min").
 */
class OperationalMinutesTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-09 12:00:00'));

        $this->restaurant = Restaurant::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function order(OrderStatus $status, int $secondsAgo): Order
    {
        $order = Order::factory()->for($this->restaurant)->create([
            'status'     => $status,
            'created_at' => now()->subSeconds($secondsAgo),
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'product_name' => 'X-Burger',
            'unit_price'   => 25,
            'quantity'     => 1,
            'subtotal'     => 25,
        ]);

        return $order;
    }

    public function test_kitchen_card_shows_whole_minutes(): void
    {
        $this->order(OrderStatus::Confirmed, 12 * 60 + 35);

        $this->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('12 min')
            ->assertDontSee('12.583')
            ->assertDontSee('Undefined');
    }

    public function test_orders_in_progress_shows_whole_minutes(): void
    {
        $this->order(OrderStatus::Confirmed, 12 * 60 + 35);

        $this->get(route('admin.orders.in-progress'))
            ->assertOk()
            ->assertSee('há 12 min')
            ->assertDontSee('12.583');
    }

    public function test_dashboard_kitchen_queue_and_delay_alert_show_whole_positive_minutes(): void
    {
        $order = $this->order(OrderStatus::InPreparation, 50 * 60 + 35);

        OrderStatusHistory::create([
            'order_id'    => $order->id,
            'from_status' => OrderStatus::Confirmed->value,
            'to_status'   => OrderStatus::InPreparation->value,
            'changed_by'  => null,
            'changed_at'  => now()->subMinutes(45),
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('50 min')          // kitchen queue: oldest order of the item
            ->assertDontSee('50.583')
            ->assertSee('Há 45 min em preparo.')
            ->assertDontSee('Há -45 min');
    }
}
