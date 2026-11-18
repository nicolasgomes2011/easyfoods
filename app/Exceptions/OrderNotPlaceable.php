<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business reason the order cannot be placed (store closed, order type not
 * offered, item no longer available…). Its message is written for the customer
 * and is the only one the checkout shows as is.
 */
final class OrderNotPlaceable extends RuntimeException
{
    public const GENERIC_MESSAGE = 'Não foi possível concluir o pedido. Tente novamente.';

    /**
     * Customer-facing text for any failure while placing an order: the domain
     * reason, or a generic message — other errors are reported, never shown.
     */
    public static function messageFor(\Throwable $e): string
    {
        if ($e instanceof self) {
            return $e->getMessage();
        }

        report($e);

        return self::GENERIC_MESSAGE;
    }
}
