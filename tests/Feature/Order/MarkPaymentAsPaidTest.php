<?php

namespace Tests\Feature\Order;

use App\Actions\Payments\MarkPaymentAsPaid;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * "Marcar como pago" (EF-4): records the payment only — payment state is
 * independent from the order status.
 */
class MarkPaymentAsPaidTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithPendingPayment(OrderStatus $status = OrderStatus::Confirmed): Order
    {
        $order = Order::factory()->create(['status' => $status]);

        Payment::create([
            'order_id' => $order->id,
            'method'   => PaymentMethod::Cash,
            'status'   => PaymentStatus::Pending,
            'amount'   => $order->total,
        ]);

        return $order;
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager]);
    }

    public function test_order_page_shows_the_mark_as_paid_button_for_a_pending_payment(): void
    {
        $order = $this->orderWithPendingPayment();

        $this->actingAs($this->manager())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Dinheiro')
            ->assertSee('Marcar como pago')
            ->assertDontSee('Undefined');
    }

    public function test_manager_marks_the_payment_as_paid_without_touching_the_order_status(): void
    {
        $order = $this->orderWithPendingPayment(OrderStatus::InPreparation);
        $historyBefore = $order->statusHistory()->count();

        $this->actingAs($this->manager());

        Volt::test('orders.show', ['order' => $order])
            ->call('markAsPaid')
            ->assertHasNoErrors()
            ->assertSee('Pagamento registrado.')
            ->assertDontSee('Marcar como pago');

        $payment = $order->payment()->first();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);

        $order->refresh();
        $this->assertSame(OrderStatus::InPreparation, $order->status);
        $this->assertSame($historyBefore, $order->statusHistory()->count());
    }

    public function test_kitchen_user_cannot_mark_a_payment_as_paid(): void
    {
        $order = $this->orderWithPendingPayment();

        $this->actingAs(User::factory()->create(['role' => UserRole::Kitchen]));

        Volt::test('orders.show', ['order' => $order])
            ->call('markAsPaid')
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $order->payment()->first()->status);
    }

    public function test_an_already_paid_payment_is_not_paid_again(): void
    {
        $order = $this->orderWithPendingPayment();
        $payment = $order->payment()->first();

        $action = app(MarkPaymentAsPaid::class);
        $action->execute($payment);
        $firstPaidAt = $payment->fresh()->paid_at;

        $this->expectException(\RuntimeException::class);

        try {
            $action->execute($payment->fresh());
        } finally {
            $this->assertEquals($firstPaidAt, $payment->fresh()->paid_at);
        }
    }
}
