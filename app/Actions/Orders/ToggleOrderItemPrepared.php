<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Kitchen checklist: tick an item off (or back on) while its order is in preparation.
 *
 * Runs under the order row lock — the same lock TransitionOrderStatus and
 * AddOrderItem take — and re-checks the status after the lock, so an item can't
 * be unticked on an order another screen has just marked "Pronto". The item is
 * re-read under the lock and flipped from its stored value, not from the
 * caller's copy.
 */
class ToggleOrderItemPrepared
{
    /**
     * @return bool Whether the item was written: false when the order is no longer
     *              in preparation or the item is no longer on it (nothing changes).
     */
    public function execute(OrderItem $item): bool
    {
        return DB::transaction(function () use ($item) {
            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);

            if ($order->status !== OrderStatus::InPreparation) {
                return false;
            }

            $stored = OrderItem::query()->where('order_id', $order->id)->find($item->id);

            if ($stored === null) {
                return false; // removed from the order meanwhile
            }

            $stored->forceFill(['prepared_at' => $stored->isPrepared() ? null : now()])->save();

            return true;
        });
    }
}
