<?php

namespace App\Enums;

enum ProductAvailabilityStatus: string
{
    case Available   = 'available';
    case Unavailable = 'unavailable';
    case OutOfStock  = 'out_of_stock';

    public function label(): string
    {
        return match($this) {
            self::Available   => 'Disponível',
            self::Unavailable => 'Indisponível',
            self::OutOfStock  => 'Sem estoque',
        };
    }

    public function isOrderable(): bool
    {
        return $this === self::Available;
    }
}
