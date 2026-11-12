<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * New-order alert on the counter (orders in progress: pending confirmation)
 * and in the kitchen (confirmed). Each poll dispatches `order-alerts` with the
 * number of orders waiting and how many arrived since the last alert, so the
 * browser rings once per new order and never again on the next poll.
 */
class NewOrderAlertTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function order(OrderStatus $status = OrderStatus::PendingConfirmation, ?Restaurant $restaurant = null): Order
    {
        return Order::factory()->withStatus($status)->create([
            'restaurant_id' => ($restaurant ?? $this->restaurant)->id,
        ]);
    }

    public function test_counter_rings_once_for_a_new_pending_order(): void
    {
        $this->order();

        $counter = Volt::test('orders.in-progress');

        $counter->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 1, fresh: 0);

        $this->order();

        $counter->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 2, fresh: 1);

        // Next poll with nothing new: count stays, no second ring.
        $counter->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 2, fresh: 0)
            ->assertNotDispatched('order-alerts', count: 2, fresh: 1);
    }

    public function test_kitchen_rings_for_confirmed_orders_only(): void
    {
        $kitchen = Volt::test('kitchen.index');

        $this->order(OrderStatus::PendingConfirmation);
        $this->order(OrderStatus::InPreparation);

        $kitchen->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 0, fresh: 0);

        $this->order(OrderStatus::Confirmed);

        $kitchen->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 1, fresh: 1);
    }

    public function test_order_of_another_restaurant_does_not_ring(): void
    {
        $counter = Volt::test('orders.in-progress');

        $this->order(restaurant: Restaurant::factory()->create());

        $counter->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 0, fresh: 0);
    }

    public function test_coming_back_to_the_screen_rings_only_for_orders_that_arrived_meanwhile(): void
    {
        $this->order();
        Volt::test('orders.in-progress')->call('checkNewOrders');

        // Leaving and opening the screen again: the order already alerted stays quiet.
        Volt::test('orders.in-progress')
            ->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 1, fresh: 0);

        $this->order();

        Volt::test('orders.in-progress')
            ->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 2, fresh: 1);
    }

    public function test_first_visit_does_not_ring_for_orders_already_waiting(): void
    {
        $this->order(OrderStatus::Confirmed);
        $this->order(OrderStatus::Confirmed);

        Volt::test('kitchen.index')
            ->call('checkNewOrders')
            ->assertDispatched('order-alerts', count: 2, fresh: 0);
    }

    public function test_starting_preparation_updates_the_kitchen_count(): void
    {
        $order = $this->order(OrderStatus::Confirmed);

        Volt::test('kitchen.index')
            ->call('startPreparing', $order->id)
            ->assertDispatched('order-alerts', count: 0, fresh: 0);
    }

    public function test_waiting_orders_are_highlighted_as_new(): void
    {
        $pending = $this->order(OrderStatus::PendingConfirmation);
        $confirmed = $this->order(OrderStatus::Confirmed);

        Volt::test('orders.in-progress')
            ->assertSeeHtml('data-new-order="'.$pending->id.'"')
            ->assertDontSeeHtml('data-new-order="'.$confirmed->id.'"');

        Volt::test('kitchen.index')
            ->assertSeeHtml('data-new-order="'.$confirmed->id.'"')
            ->assertDontSeeHtml('data-new-order="'.$pending->id.'"');
    }

    public function test_panels_poll_the_alert_check_and_offer_the_sound_toggle(): void
    {
        foreach (['admin.orders.in-progress', 'admin.kitchen.index'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('wire:poll.30s.keep-alive="checkNewOrders"', false)
                ->assertSee('data-order-alert', false)
                ->assertSee('@order-alerts.window', false)
                ->assertSee('Ativar som')
                ->assertDontSee('Undefined');
        }
    }
}
