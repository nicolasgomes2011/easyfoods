<?php

namespace App\Enums;

enum OrderItemChangeType: string
{
    case Added   = 'added';
    case Removed = 'removed';

    public function label(): string
    {
        return match($this) {
            self::Added   => 'Adicionado',
            self::Removed => 'Removido',
        };
    }
}
