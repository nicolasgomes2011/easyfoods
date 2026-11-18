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
 * Every panel screen names the order's delivery type from the enum, and a
 * dine-in order shows its table ("Mesa N") so staff know where to take it —
 * never the old "Balcão" fallback for anything that wasn't a delivery.
 */
class DeliveryTypeLabelTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function order(OrderStatus $status, DeliveryType $type = DeliveryType::DineIn, ?int $table = 3): Order
    {
        return Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status'        => $status,
            'delivery_type' => $type,
            'table_number'  => $type === DeliveryType::DineIn ? $table : null,
        ]);
    }

    public function test_kitchen_card_shows_the_table_of_a_dine_in_order(): void
    {
        $this->order(OrderStatus::Confirmed);

        $this->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Mesa 3')
            ->assertDontSee('Balcão')
            ->assertDontSee('Undefined');

        Volt::test('kitchen.index')->assertSee('Mesa 3')->assertDontSee('Balcão');
    }

    public function test_order_detail_shows_the_table_of_a_dine_in_order(): void
    {
        $order = $this->order(OrderStatus::PendingConfirmation);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Mesa 3')
            ->assertDontSee('balcão')
            ->assertDontSee('Undefined');

        Volt::test('orders.show', ['order' => $order])->assertSee('Mesa 3')->assertDontSee('balcão');
    }

    public function test_order_lists_and_dashboard_show_the_table_of_a_dine_in_order(): void
    {
        $this->order(OrderStatus::InPreparation);

        foreach (['admin.orders.index', 'admin.orders.in-progress', 'admin.dashboard'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('Mesa 3')
                ->assertDontSee('Balcão')
                ->assertDontSee('Undefined');
        }
    }

    public function test_history_shows_the_table_of_a_dine_in_order(): void
    {
        $this->order(OrderStatus::Completed);

        $this->get(route('admin.orders.history'))
            ->assertOk()
            ->assertSee('Mesa 3')
            ->assertDontSee('Balcão')
            ->assertDontSee('Undefined');
    }

    public function test_label_follows_the_delivery_type(): void
    {
        $label = fn (Order $order) => trim(strip_tags((string) $this->blade(
            '<x-orders.delivery-type :order="$order" />',
            ['order' => $order],
        )));

        $this->assertSame('Retirada', $label($this->order(OrderStatus::Confirmed, DeliveryType::Pickup)));
        $this->assertSame('Entrega', $label($this->order(OrderStatus::Confirmed, DeliveryType::Delivery)));
        $this->assertSame('Mesa 7', $label($this->order(OrderStatus::Confirmed, DeliveryType::DineIn, 7)));
        $this->assertSame('Mesa', $label($this->order(OrderStatus::Confirmed, DeliveryType::DineIn, null)));
    }
}
