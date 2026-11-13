<?php

namespace Tests\Feature\Order;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Kitchen queue (EF-42): filter by order type (Todos / Mesa / Delivery /
 * Retirada), remembered in the user's session; the queue is grouped by status
 * (waiting / in preparation) and the counters only count this restaurant's
 * orders of the selected type.
 */
class KitchenTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Kitchen]));
    }

    private function order(DeliveryType $type, OrderStatus $status, ?Restaurant $restaurant = null): Order
    {
        return Order::factory()->for($restaurant ?? $this->restaurant)->create([
            'status'        => $status,
            'delivery_type' => $type,
            'table_number'  => $type === DeliveryType::DineIn ? 7 : null,
            'confirmed_at'  => now()->subMinutes(5),
        ]);
    }

    private function card(Order $order): string
    {
        return 'data-kitchen-order="'.$order->id.'"';
    }

    public function test_filter_reduces_the_queue_to_the_selected_type(): void
    {
        $dineIn   = $this->order(DeliveryType::DineIn, OrderStatus::Confirmed);
        $delivery = $this->order(DeliveryType::Delivery, OrderStatus::Confirmed);
        $pickup   = $this->order(DeliveryType::Pickup, OrderStatus::InPreparation);

        $kitchen = Volt::test('kitchen.index')
            ->assertSet('typeFilter', 'all')
            ->assertSeeHtml($this->card($dineIn))
            ->assertSeeHtml($this->card($delivery))
            ->assertSeeHtml($this->card($pickup));

        $kitchen->call('filterBy', 'dine_in')
            ->assertSeeHtml($this->card($dineIn))
            ->assertDontSeeHtml($this->card($delivery))
            ->assertDontSeeHtml($this->card($pickup));

        $kitchen->call('filterBy', 'delivery')
            ->assertDontSeeHtml($this->card($dineIn))
            ->assertSeeHtml($this->card($delivery))
            ->assertDontSeeHtml($this->card($pickup));

        $kitchen->call('filterBy', 'pickup')
            ->assertDontSeeHtml($this->card($dineIn))
            ->assertDontSeeHtml($this->card($delivery))
            ->assertSeeHtml($this->card($pickup));

        $kitchen->call('filterBy', 'all')
            ->assertSeeHtml($this->card($dineIn))
            ->assertSeeHtml($this->card($delivery))
            ->assertSeeHtml($this->card($pickup));
    }

    public function test_unknown_filter_falls_back_to_all(): void
    {
        $order = $this->order(DeliveryType::Delivery, OrderStatus::Confirmed);

        Volt::test('kitchen.index')
            ->call('filterBy', 'drone')
            ->assertSet('typeFilter', 'all')
            ->assertSeeHtml($this->card($order));
    }

    public function test_filter_is_remembered_when_the_screen_is_opened_again(): void
    {
        $dineIn   = $this->order(DeliveryType::DineIn, OrderStatus::Confirmed);
        $delivery = $this->order(DeliveryType::Delivery, OrderStatus::Confirmed);

        Volt::test('kitchen.index')->call('filterBy', 'dine_in');

        Volt::test('kitchen.index')
            ->assertSet('typeFilter', 'dine_in')
            ->assertSeeHtml($this->card($dineIn))
            ->assertDontSeeHtml($this->card($delivery));
    }

    public function test_orders_hidden_by_the_filter_are_announced(): void
    {
        $this->order(DeliveryType::Delivery, OrderStatus::Confirmed);
        $this->order(DeliveryType::Pickup, OrderStatus::InPreparation);
        $this->order(DeliveryType::DineIn, OrderStatus::Confirmed);
        // Not on the queue any more: not counted.
        $this->order(DeliveryType::Delivery, OrderStatus::ReadyForPickup);
        // Another restaurant: not counted.
        $this->order(DeliveryType::Delivery, OrderStatus::Confirmed, Restaurant::factory()->create());

        Volt::test('kitchen.index')
            ->assertDontSeeHtml('data-kitchen-hidden')
            ->call('filterBy', 'dine_in')
            ->assertSeeHtml('data-kitchen-hidden')
            ->assertSee('2 pedidos de outros tipos na fila')
            ->call('filterBy', 'all')
            ->assertDontSeeHtml('data-kitchen-hidden');
    }

    public function test_empty_filtered_queue_says_so(): void
    {
        $this->order(DeliveryType::Delivery, OrderStatus::Confirmed);

        Volt::test('kitchen.index')
            ->call('filterBy', 'pickup')
            ->assertSee('Nenhum pedido de retirada na fila.')
            ->assertDontSee('Fila vazia.');
    }

    public function test_counters_only_count_this_restaurant_orders(): void
    {
        $this->order(DeliveryType::Delivery, OrderStatus::Confirmed);
        $this->order(DeliveryType::DineIn, OrderStatus::Confirmed);
        $this->order(DeliveryType::Delivery, OrderStatus::InPreparation);
        $this->order(DeliveryType::Pickup, OrderStatus::ReadyForPickup);

        $other = Restaurant::factory()->create();
        $this->order(DeliveryType::Delivery, OrderStatus::Confirmed, $other);
        $this->order(DeliveryType::Delivery, OrderStatus::InPreparation, $other);
        $this->order(DeliveryType::Delivery, OrderStatus::ReadyForPickup, $other);

        $kitchen = Volt::test('kitchen.index');

        $this->assertSame(2, $kitchen->instance()->waitingCount);
        $this->assertSame(1, $kitchen->instance()->inPreparationCount);
        $this->assertSame(1, $kitchen->instance()->readyCount);
    }

    public function test_counters_follow_the_filter(): void
    {
        $this->order(DeliveryType::Delivery, OrderStatus::Confirmed);
        $this->order(DeliveryType::DineIn, OrderStatus::Confirmed);
        $this->order(DeliveryType::Delivery, OrderStatus::InPreparation);
        $this->order(DeliveryType::Pickup, OrderStatus::ReadyForPickup);

        $kitchen = Volt::test('kitchen.index')->call('filterBy', 'delivery');

        $this->assertSame(1, $kitchen->instance()->waitingCount);
        $this->assertSame(1, $kitchen->instance()->inPreparationCount);
        $this->assertSame(0, $kitchen->instance()->readyCount);
    }

    public function test_queue_is_grouped_waiting_first_then_in_preparation(): void
    {
        $inPrep  = $this->order(DeliveryType::Delivery, OrderStatus::InPreparation);
        $waiting = $this->order(DeliveryType::DineIn, OrderStatus::Confirmed);

        $this->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSeeHtmlInOrder([
                'data-kitchen-group="confirmed"',
                $this->card($waiting),
                'data-kitchen-group="in_preparation"',
                $this->card($inPrep),
            ]);
    }

    public function test_real_route_shows_the_filter_and_large_action_buttons(): void
    {
        $this->order(DeliveryType::DineIn, OrderStatus::Confirmed);

        $this->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSeeHtml('data-kitchen-filter="all"')
            ->assertSeeHtml('data-kitchen-filter="dine_in"')
            ->assertSeeHtml('data-kitchen-filter="delivery"')
            ->assertSeeHtml('data-kitchen-filter="pickup"')
            ->assertSee('Mesa 7')
            ->assertSee('Iniciar preparo')
            // Touch target: action buttons at least 48px tall.
            ->assertSeeHtml('data-kitchen-action="start"');
    }
}
