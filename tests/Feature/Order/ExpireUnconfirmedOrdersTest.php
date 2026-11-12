<?php

namespace Tests\Feature\Order;

use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\ExpireUnconfirmedOrders;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * An order nobody confirms is canceled by the scheduler once the restaurant's
 * confirmation window (restaurants.confirmation_timeout_minutes) is over, through
 * TransitionOrderStatus with a system actor (changed_by = null) and a reason the
 * customer sees on the tracking page.
 */
class ExpireUnconfirmedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-11-12 20:00:00');
        Carbon::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function restaurant(int $timeout = 10): Restaurant
    {
        return Restaurant::factory()->create(['confirmation_timeout_minutes' => $timeout]);
    }

    /** Order placed $minutesAgo minutes ago, with the history row PlaceOrder writes. */
    private function placed(Restaurant $restaurant, int $minutesAgo, OrderStatus $status = OrderStatus::PendingConfirmation): Order
    {
        $placedAt = $this->now->copy()->subMinutes($minutesAgo);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'status'        => $status,
            'confirmed_at'  => $status === OrderStatus::PendingConfirmation ? null : $placedAt,
            'canceled_at'   => null,
            'created_at'    => $placedAt,
        ]);

        OrderStatusHistory::create([
            'order_id'    => $order->id,
            'from_status' => null,
            'to_status'   => OrderStatus::PendingConfirmation->value,
            'changed_by'  => null,
            'changed_at'  => $placedAt,
        ]);

        return $order;
    }

    private function runJob(): void
    {
        app()->call([new ExpireUnconfirmedOrders, 'handle']);
    }

    public function test_order_pending_for_longer_than_the_window_is_canceled_with_history_and_reason(): void
    {
        $order = $this->placed($this->restaurant(10), minutesAgo: 11);

        $this->runJob();

        $order->refresh();
        $this->assertSame(OrderStatus::Canceled, $order->status);
        $this->assertTrue($order->canceled_at->equalTo($this->now));

        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();
        $this->assertSame(OrderStatus::PendingConfirmation, $history->from_status);
        $this->assertSame(OrderStatus::Canceled, $history->to_status);
        $this->assertNull($history->changed_by);
        $this->assertSame(ExpireUnconfirmedOrders::REASON, $history->notes);
        $this->assertSame('Não confirmado a tempo', ExpireUnconfirmedOrders::REASON);
    }

    public function test_orders_inside_the_window_or_already_confirmed_are_not_touched(): void
    {
        $restaurant = $this->restaurant(10);
        $recent = $this->placed($restaurant, minutesAgo: 9);
        $confirmed = $this->placed($restaurant, minutesAgo: 30, status: OrderStatus::Confirmed);
        $preparing = $this->placed($restaurant, minutesAgo: 30, status: OrderStatus::InPreparation);

        $this->runJob();

        $this->assertSame(OrderStatus::PendingConfirmation, $recent->fresh()->status);
        $this->assertSame(OrderStatus::Confirmed, $confirmed->fresh()->status);
        $this->assertSame(OrderStatus::InPreparation, $preparing->fresh()->status);
        $this->assertSame(0, OrderStatusHistory::where('to_status', OrderStatus::Canceled->value)->count());
    }

    public function test_each_restaurant_uses_its_own_window_and_zero_turns_it_off(): void
    {
        $short = $this->placed($this->restaurant(10), minutesAgo: 15);
        $long = $this->placed($this->restaurant(30), minutesAgo: 15);
        $off = $this->placed($this->restaurant(0), minutesAgo: 600);

        $this->runJob();

        $this->assertSame(OrderStatus::Canceled, $short->fresh()->status);
        $this->assertSame(OrderStatus::PendingConfirmation, $long->fresh()->status);
        $this->assertSame(OrderStatus::PendingConfirmation, $off->fresh()->status);
    }

    public function test_running_twice_cancels_once(): void
    {
        $order = $this->placed($this->restaurant(10), minutesAgo: 20);

        $this->runJob();
        $this->runJob();

        $this->assertSame(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)
            ->where('to_status', OrderStatus::Canceled->value)->count());
    }

    public function test_customer_tracking_page_shows_the_cancellation_and_its_reason(): void
    {
        $order = $this->placed($this->restaurant(10), minutesAgo: 11);

        $this->runJob();

        $this->get(route('store.order.tracking', $order->fresh()->token))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Pedido cancelado às 20:00')
            ->assertSee('Não confirmado a tempo');
    }

    public function test_transition_accepts_a_system_actor(): void
    {
        $order = $this->placed($this->restaurant(10), minutesAgo: 1);

        app(TransitionOrderStatus::class)->execute($order, OrderStatus::Canceled, null, 'Motivo do sistema');

        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();
        $this->assertSame(OrderStatus::Canceled, $history->to_status);
        $this->assertNull($history->changed_by);
    }

    public function test_the_job_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->description === ExpireUnconfirmedOrders::class);

        $this->assertNotNull($event, 'ExpireUnconfirmedOrders is not in the schedule.');
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_new_restaurants_default_to_a_ten_minute_window(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->assertSame(10, $restaurant->fresh()->confirmation_timeout_minutes);
    }

    public function test_store_settings_save_and_validate_the_window(): void
    {
        $restaurant = $this->restaurant(10);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get(route('admin.settings.store'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Cancelar pedido não confirmado após (min)');

        Volt::test('settings.store')
            ->assertSet('confirmationTimeoutMinutes', 10)
            ->set('confirmationTimeoutMinutes', 241)
            ->call('save')
            ->assertHasErrors('confirmationTimeoutMinutes')
            ->set('confirmationTimeoutMinutes', -1)
            ->call('save')
            ->assertHasErrors('confirmationTimeoutMinutes')
            ->set('confirmationTimeoutMinutes', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, $restaurant->fresh()->confirmation_timeout_minutes);
    }
}
