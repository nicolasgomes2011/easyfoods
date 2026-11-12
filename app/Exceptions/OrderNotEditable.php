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
}
