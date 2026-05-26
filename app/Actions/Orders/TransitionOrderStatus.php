<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Single source for moving an order between statuses.
 *
 * Holds the invariant every transition must satisfy: the move is allowed by the
 * OrderStatus enum, the change is recorded in order_status_histories (append-only),
 * and the matching milestone timestamp is set — all inside one transaction.
 */
class TransitionOrderStatus
{
    /** Target status => milestone column on `orders`. Statuses without a column are omitted. */
    private const MILESTONES = [
        OrderStatus::Confirmed->value => 'confirmed_at',
        OrderStatus::ReadyForPickup->value => 'ready_at',
        OrderStatus::Delivered->value => 'delivered_at',
        OrderStatus::Completed->value => 'completed_at',
        OrderStatus::Canceled->value => 'canceled_at',
    ];

    public function execute(Order $order, OrderStatus $to, User $actor, ?string $notes = null): Order
    {
        if ($to === OrderStatus::Canceled && blank($notes)) {
            throw new InvalidArgumentException('A cancellation requires a reason.');
        }

        return DB::transaction(function () use ($order, $to, $actor, $notes) {
            // Re-read (and lock on engines that support it) so a concurrent change can't be clobbered.
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $order->status;

            if ($from === $to) {
                return $order; // idempotent: nothing to do
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Transição inválida: {$from->label()} → {$to->label()}.",
                ]);
            }

            $order->status = $to;

            $milestone = self::MILESTONES[$to->value] ?? null;
            if ($milestone !== null && $order->{$milestone} === null) {
                $order->{$milestone} = now();
            }

            $order->save();

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor->id,
                'notes' => $notes,
                'changed_at' => now(),
            ]);

            return $order;
        });
    }
}
