<?php

namespace App\Enums;

enum IngredientCategory: string
{
    case Meat    = 'meat';
    case Produce = 'produce';
    case Dairy   = 'dairy';
    case Bakery  = 'bakery';
    case Other   = 'other';

    public function label(): string
    {
        return match($this) {
            self::Meat    => 'Carnes',
            self::Produce => 'Hortifrúti',
            self::Dairy   => 'Laticínios',
            self::Bakery  => 'Pães',
            self::Other   => 'Outros',
        };
    }
}
