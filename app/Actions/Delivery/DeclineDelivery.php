<?php

namespace App\Actions\Delivery;

use App\Models\DeliveryDecline;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * A driver turns down a delivery from "Disponíveis". The decline is recorded
 * (once per order + driver) and the order stays available to the other drivers;
 * the panel only hides it from who declined. No driver is set.
 */
class DeclineDelivery
{
    public function execute(?int $restaurantId, int $orderId, User $driver): void
    {
        $order = Order::query()
            ->whereKey($orderId)
            ->availableForDelivery($restaurantId)
            ->first();

        if ($order === null) {
            throw ValidationException::withMessages([
                'order' => 'Este pedido não está mais disponível.',
            ]);
        }

        DeliveryDecline::firstOrCreate(
            ['order_id' => $order->id, 'user_id' => $driver->id],
            ['restaurant_id' => $order->restaurant_id],
        );
    }
}
