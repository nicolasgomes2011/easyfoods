<?php

namespace App\Actions\Orders\Concerns;

use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotEditable;
use App\Models\Order;

/**
 * Shared rules of AddOrderItem / RemoveOrderItem: lock the order row and re-check
 * it can still be edited, then recompute the totals on the server and keep the
 * not-yet-paid payment in step. The order status is never touched here.
 */
trait EditsOpenOrder
{
    /** Payment states where money was already taken or held: the items are frozen. */
    private const SETTLED_PAYMENT_STATUSES = [
        PaymentStatus::Authorized,
        PaymentStatus::Paid,
        PaymentStatus::PartiallyRefunded,
        PaymentStatus::Refunded,
    ];

    /** Payment states whose amount follows the order total. */
    private const OPEN_PAYMENT_STATUSES = [
        PaymentStatus::Pending,
        PaymentStatus::Failed,
    ];

    /**
     * Fresh copy of the order, locked for the rest of the transaction, after
     * checking (again, under the lock) that its items may still change.
     */
    private function lockEditableOrder(Order $order): Order
    {
        $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

        if (! $locked->itemsAreEditable()) {
            throw new OrderNotEditable(
                'Só dá para mudar os itens de pedidos de mesa que ainda não ficaram prontos.'
            );
        }

        $settled = $locked->payments()
            ->whereIn('status', array_map(fn (PaymentStatus $s) => $s->value, self::SETTLED_PAYMENT_STATUSES))
            ->exists();

        if ($settled) {
            throw new OrderNotEditable(
                'O pagamento deste pedido já foi registrado. Não dá para mudar os itens.'
            );
        }

        return $locked;
    }

    /**
     * Subtotal = sum of the item lines (addons included); total = subtotal +
     * delivery fee - discount. Open payments get the new amount and change.
     */
    private function syncTotals(Order $order): void
    {
        $subtotal = round((float) $order->items()->sum('subtotal'), 2);
        $total = round($subtotal + (float) $order->delivery_fee - (float) $order->discount, 2);

        $order->update([
            'subtotal' => $subtotal,
            'total'    => $total,
        ]);

        $payments = $order->payments()
            ->whereIn('status', array_map(fn (PaymentStatus $s) => $s->value, self::OPEN_PAYMENT_STATUSES))
            ->get();

        foreach ($payments as $payment) {
            $payment->update([
                'amount'     => $total,
                'change_due' => $payment->amount_tendered !== null
                    ? max(0, round((float) $payment->amount_tendered - $total, 2))
                    : $payment->change_due,
            ]);
        }
    }
}
