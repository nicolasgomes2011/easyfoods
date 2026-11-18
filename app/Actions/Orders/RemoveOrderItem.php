<?php

namespace App\Actions\Orders;

use App\Actions\Orders\Concerns\EditsOpenOrder;
use App\Enums\OrderItemChangeType;
use App\Exceptions\OrderNotEditable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff removes an item from an open dine-in order, with a reason.
 *
 * The line is deleted from the order, a snapshot of it (with who and why) is kept
 * in order_item_changes and the totals are recomputed on the server. The last
 * item cannot be removed: to drop the whole order, cancel it. The order status
 * does not change.
 */
class RemoveOrderItem
{
    use EditsOpenOrder;

    public const MIN_REASON_LENGTH = 3;

    /**
     * @throws OrderNotEditable
     */
    public function execute(Order $order, int $orderItemId, string $reason, User $actor): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new OrderNotEditable('Informe o motivo da remoção.');
        }

        DB::transaction(function () use ($order, $orderItemId, $reason, $actor) {
            $order = $this->lockEditableOrder($order);

            // Scoped to this order: an item id of another order is simply not found.
            $item = OrderItem::where('order_id', $order->id)->find($orderItemId);

            if ($item === null) {
                throw new OrderNotEditable('Item não encontrado neste pedido.');
            }

            if ($order->items()->count() <= 1) {
                throw new OrderNotEditable(
                    'O pedido precisa de pelo menos um item. Para desistir do pedido, cancele-o.'
                );
            }

            OrderItemChange::create([
                'restaurant_id' => $order->restaurant_id,
                'order_id'      => $order->id,
                'order_item_id' => null, // the row is deleted below; the snapshot stays here
                'type'          => OrderItemChangeType::Removed,
                'product_name'  => $item->product_name,
                'variant_name'  => $item->variant_name,
                'quantity'      => $item->quantity,
                'subtotal'      => $item->subtotal,
                'reason'        => $reason,
                'changed_by'    => $actor->id,
                'changed_at'    => now(),
            ]);

            $item->addons()->delete();
            $item->delete();

            $this->syncTotals($order);
        });
    }
}
