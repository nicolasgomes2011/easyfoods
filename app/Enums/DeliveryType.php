<?php

namespace App\Enums;

enum DeliveryType: string
{
    case Delivery = 'delivery';
    case Pickup   = 'pickup';

    public function label(): string
    {
        return match($this) {
            self::Delivery => 'Entrega',
            self::Pickup   => 'Retirada',
        };
    }

    public function requiresAddress(): bool
    {
        return $this === self::Delivery;
    }

    public function requiresDeliveryFee(): bool
    {
        return $this === self::Delivery;
    }
}
