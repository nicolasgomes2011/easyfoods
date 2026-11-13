<?php

namespace App\Enums;

/** Unit an ingredient is bought and counted in; its cost is per one of these. */
enum IngredientUnit: string
{
    case Kilogram = 'kg';
    case Gram     = 'g';
    case Unit     = 'un';
    case Liter    = 'l';

    public function label(): string
    {
        return match($this) {
            self::Kilogram => 'Quilo (kg)',
            self::Gram     => 'Grama (g)',
            self::Unit     => 'Unidade (un)',
            self::Liter    => 'Litro (L)',
        };
    }

    /** Short form shown next to the cost: "R$ 45,90 / kg". */
    public function symbol(): string
    {
        return match($this) {
            self::Kilogram => 'kg',
            self::Gram     => 'g',
            self::Unit     => 'un',
            self::Liter    => 'L',
        };
    }
}
