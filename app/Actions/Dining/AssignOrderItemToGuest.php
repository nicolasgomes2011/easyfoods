<?php

namespace App\Actions\Dining;

use App\Enums\OrderStatus;
use App\Exceptions\TabNotEditable;
use App\Models\OrderItem;
use App\Models\TableSessionGuest;

/**
 * Staff says which person at the table an item belongs to (or none: shared).
 * Only the link changes: the item snapshot (name, price, subtotal) and the
 * order totals stay as they are. The person must be of the same table session
 * (and restaurant) as the item's order, the session must be open and the order
 * not canceled (a canceled order is out of the tab total).
 */
class AssignOrderItemToGuest
{
    /**
     * @throws TabNotEditable
     */
    public function execute(OrderItem $item, ?TableSessionGuest $guest): OrderItem
    {
        $order = $item->order;
        $session = $order->tableSession;

        if ($session === null) {
            throw new TabNotEditable('Este item não é de um pedido de mesa.');
        }

        if (! $session->isOpen()) {
            throw new TabNotEditable('A conta desta mesa já foi fechada.');
        }

        if ($order->status === OrderStatus::Canceled) {
            throw new TabNotEditable('Itens de pedido cancelado não entram na conta.');
        }

        if ($guest !== null
            && ($guest->table_session_id !== $session->id || $guest->restaurant_id !== $order->restaurant_id)) {
            throw new TabNotEditable('Esta pessoa não é desta mesa.');
        }

        $item->forceFill(['table_session_guest_id' => $guest?->id])->save();

        return $item;
    }
}
