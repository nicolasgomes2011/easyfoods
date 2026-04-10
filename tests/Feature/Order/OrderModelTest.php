<?php

namespace Tests\Feature\Order;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderModelTest extends TestCase
{
    use RefreshDatabase;

    // --- isCancelable ---

    public function test_pending_confirmation_order_is_cancelable(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingConfirmation]);

        $this->assertTrue($order->isCancelable());
    }

    public function test_confirmed_order_is_cancelable(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Confirmed]);

        $this->assertTrue($order->isCancelable());
    }

    public function test_in_preparation_order_is_cancelable(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::InPreparation]);

        $this->assertTrue($order->isCancelable());
    }

    public function test_completed_order_is_not_cancelable(): void
    {
        $order = Order::factory()->completed()->create();

        $this->assertFalse($order->isCancelable());
    }

    public function test_canceled_order_is_not_cancelable_again(): void
    {
        $order = Order::factory()->canceled()->create();

        $this->assertFalse($order->isCancelable());
    }

    public function test_delivered_order_is_not_cancelable(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->assertFalse($order->isCancelable());
    }

    // --- isInFinalState ---

    public function test_completed_order_is_in_final_state(): void
    {
        $order = Order::factory()->completed()->create();

        $this->assertTrue($order->isInFinalState());
    }

    public function test_canceled_order_is_in_final_state(): void
    {
        $order = Order::factory()->canceled()->create();

        $this->assertTrue($order->isInFinalState());
    }

    public function test_active_order_is_not_in_final_state(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::InPreparation]);

        $this->assertFalse($order->isInFinalState());
    }

    // --- isDelivery ---

    public function test_delivery_order_is_detected(): void
    {
        $order = Order::factory()->create(['delivery_type' => DeliveryType::Delivery]);

        $this->assertTrue($order->isDelivery());
    }

    public function test_pickup_order_is_not_delivery(): void
    {
        $order = Order::factory()->pickup()->create();

        $this->assertFalse($order->isDelivery());
    }

    // --- scopeByStatus ---

    public function test_scope_by_status_filters_correctly(): void
    {
        Order::factory()->create(['status' => OrderStatus::PendingConfirmation]);
        Order::factory()->create(['status' => OrderStatus::PendingConfirmation]);
        Order::factory()->confirmed()->create();

        $pending = Order::byStatus(OrderStatus::PendingConfirmation)->get();

        $this->assertCount(2, $pending);
    }

    // --- scopeActive ---

    public function test_scope_active_excludes_draft_and_final(): void
    {
        Order::factory()->create(['status' => OrderStatus::Draft]);
        Order::factory()->create(['status' => OrderStatus::PendingConfirmation]);
        Order::factory()->create(['status' => OrderStatus::InPreparation]);
        Order::factory()->completed()->create();
        Order::factory()->canceled()->create();

        $active = Order::active()->get();

        $this->assertCount(2, $active);
    }

    // --- total calculation integrity ---

    public function test_total_equals_subtotal_plus_delivery_fee_minus_discount(): void
    {
        $order = Order::factory()->create([
            'subtotal'     => 50.00,
            'delivery_fee' => 7.50,
            'discount'     => 5.00,
            'total'        => 52.50,
        ]);

        $expected = $order->subtotal + $order->delivery_fee - $order->discount;

        $this->assertEquals($expected, (float) $order->total);
    }
}
