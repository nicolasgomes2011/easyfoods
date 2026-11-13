<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business reason the items of an order cannot be changed (not a dine-in order,
 * already ready or finished, payment already taken, invalid item…). Its message is
 * written for the staff member and is shown as is on the order screen.
 */
final class OrderNotEditable extends RuntimeException
{
    /** The order is not an open dine-in order any more (e.g. the kitchen marked it ready). */
    public const ITEMS_LOCKED = 'Só dá para mudar os itens de pedidos de mesa que ainda não ficaram prontos.';
}
