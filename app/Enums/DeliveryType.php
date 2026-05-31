<?php

namespace App\Enums;

enum DeliveryType: string
{
    case Delivery = 'delivery';
    case Pickup   = 'pickup';
    case DineIn   = 'dine_in';

    public function label(): string
    {
        return match($this) {
            self::Delivery => 'Entrega',
            self::Pickup   => 'Retirada',
            self::DineIn   => 'Mesa',
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
