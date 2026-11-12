<?php

namespace App\Actions\Delivery;

use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * A driver takes a delivery from "Disponíveis": orders.driver_id is set with a
 * single conditional UPDATE, so when two drivers accept the same order at once
 * only the first one gets it. The order status does not change here.
 * A driver on pause (users.is_available = false) gets no offers, so cannot accept.
 */
class AcceptDelivery
{
    public const ON_PAUSE_MESSAGE = 'Você está em pausa. Fique disponível para receber entregas.';

    public function execute(?int $restaurantId, int $orderId, User $driver): Order
    {
        if (! $driver->is_available) {
            throw ValidationException::withMessages(['order' => self::ON_PAUSE_MESSAGE]);
        }

        $taken = Order::query()
            ->whereKey($orderId)
            ->availableForDelivery($restaurantId)
            ->update(['driver_id' => $driver->id]);

        if ($taken === 0) {
            throw ValidationException::withMessages([
                'order' => 'Este pedido não está mais disponível.',
            ]);
        }

        return Order::findOrFail($orderId);
    }
}
