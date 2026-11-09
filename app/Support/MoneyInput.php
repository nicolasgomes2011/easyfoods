<?php

namespace App\Support;

/**
 * Normalizes money typed in Brazilian format so the `numeric` rule can check it.
 */
final class MoneyInput
{
    /** "R$ 1.234,50" / "50,00" / "50" → "1234.50" / "50.00" / "50". */
    public static function normalize(string $value): string
    {
        $value = trim(str_replace(['R$', ' '], '', $value));

        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        }

        return $value;
    }
}
