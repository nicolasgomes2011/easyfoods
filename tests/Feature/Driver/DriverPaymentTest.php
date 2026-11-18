<?php

namespace Tests\Feature\Driver;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Payment on the kitchen and driver panels (EF-60): both cards show how the
 * customer pays (and the change to bring for cash), and the driver records the
 * payment at the door. Payment state stays independent from the order status.
 */
class DriverPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panels scope to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function order(array $attributes = []): Order
    {
        return Order::factory()->for($this->restaurant)->create(array_merge([
            'status'   => OrderStatus::OutForDelivery,
            'ready_at' => now(),
            'total'    => 45.00,
        ], $attributes));
    }

    private function pay(Order $order, PaymentMethod $method, ?float $tendered = null): Payment
    {
        return Payment::create([
            'order_id'        => $order->id,
            'method'          => $method,
            'status'          => PaymentStatus::Pending,
            'amount'          => $order->total,
            'amount_tendered' => $tendered,
            'change_due'      => $tendered !== null ? round($tendered - (float) $order->total, 2) : null,
        ]);
    }

    public function test_kitchen_card_shows_the_payment_method(): void
    {
        $order = $this->order(['status' => OrderStatus::Confirmed, 'ready_at' => null]);
        $this->pay($order, PaymentMethod::Pix);

        $this->actingAs($this->user(UserRole::Kitchen))
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSeeHtml('data-payment-summary')
            ->assertSee('Pix');
    }

    public function test_driver_card_shows_the_method_and_the_change_to_bring(): void
    {
        $driver = $this->user(UserRole::Delivery);
        $order = $this->order(['driver_id' => $driver->id]);
        $this->pay($order, PaymentMethod::Cash, 50.00);

        $this->actingAs($driver)
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Dinheiro')
            ->assertSee('Levar de troco: R$ 5,00')
            ->assertSee('Marcar como pago');
    }

    public function test_available_delivery_shows_the_method_without_the_pay_button(): void
    {
        $order = $this->order(['status' => OrderStatus::ReadyForPickup]);
        $this->pay($order, PaymentMethod::CreditCard);

        $this->actingAs($this->user(UserRole::Delivery))
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertSee('Cartão de crédito')
            ->assertDontSee('Marcar como pago');
    }

    public function test_driver_marks_the_payment_as_paid_without_touching_the_order_status(): void
    {
        $driver = $this->user(UserRole::Delivery);
        $order = $this->order(['driver_id' => $driver->id]);
        $payment = $this->pay($order, PaymentMethod::Cash, 50.00);
        $historyBefore = $order->statusHistory()->count();

        $this->actingAs($driver);

        Volt::test('driver.index')
            ->call('markPaid', $order->id)
            ->assertSet('error', null)
            ->assertDontSee('Marcar como pago');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);

        $order->refresh();
        $this->assertSame(OrderStatus::OutForDelivery, $order->status);
        $this->assertSame($historyBefore, $order->statusHistory()->count());
    }

    public function test_driver_cannot_mark_another_drivers_delivery_as_paid(): void
    {
        $other = $this->user(UserRole::Delivery);
        $order = $this->order(['driver_id' => $other->id]);
        $payment = $this->pay($order, PaymentMethod::Cash);

        $this->actingAs($this->user(UserRole::Delivery));

        Volt::test('driver.index')
            ->call('markPaid', $order->id)
            ->assertSet('error', 'Esta entrega não é sua. Aceite o pedido em "Disponíveis" antes.');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_driver_cannot_mark_an_already_paid_payment_again(): void
    {
        $driver = $this->user(UserRole::Delivery);
        $order = $this->order(['driver_id' => $driver->id]);
        $payment = $this->pay($order, PaymentMethod::Pix);
        $payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()->subMinute()]);
        $paidAt = $payment->fresh()->paid_at;

        $this->actingAs($driver);

        Volt::test('driver.index')
            ->call('markPaid', $order->id)
            ->assertSet('error', 'Este pedido não tem pagamento pendente.');

        $this->assertEquals($paidAt, $payment->fresh()->paid_at);
    }

    public function test_policy_lets_the_driver_mark_paid_only_on_their_own_delivery(): void
    {
        $driver = $this->user(UserRole::Delivery);

        $own = $this->order(['driver_id' => $driver->id]);
        $others = $this->order(['driver_id' => $this->user(UserRole::Delivery)->id]);
        $unassigned = $this->order(['status' => OrderStatus::ReadyForPickup]);
        $pickup = $this->order([
            'driver_id'     => $driver->id,
            'delivery_type' => DeliveryType::Pickup,
            'status'        => OrderStatus::ReadyForPickup,
        ]);
        $otherRestaurant = Order::factory()->for(Restaurant::factory())->create([
            'status'    => OrderStatus::OutForDelivery,
            'driver_id' => $driver->id,
        ]);

        $this->assertTrue($driver->can('markPaid', $own));
        $this->assertFalse($driver->can('markPaid', $others));
        $this->assertFalse($driver->can('markPaid', $unassigned));
        $this->assertFalse($driver->can('markPaid', $pickup));
        $this->assertFalse($driver->can('markPaid', $otherRestaurant));

        // Front of house keeps it; the kitchen still cannot.
        $this->assertTrue($this->user(UserRole::Attendant)->can('markPaid', $own));
        $this->assertFalse($this->user(UserRole::Kitchen)->can('markPaid', $own));
    }
}
