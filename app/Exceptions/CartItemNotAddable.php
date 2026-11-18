<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business reason an item cannot be added to the cart (invalid quantity,
 * option no longer available, product of another restaurant…). Its message is
 * written for the customer and is shown as is in the product modal.
 */
final class CartItemNotAddable extends RuntimeException
{
}
