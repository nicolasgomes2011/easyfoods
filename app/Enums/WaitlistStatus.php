<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Seated  = 'seated';
    case Removed = 'removed';

    public function label(): string
    {
        return match($this) {
            self::Waiting => 'Aguardando',
            self::Seated  => 'Sentado',
            self::Removed => 'Removido',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Waiting => 'yellow',
            self::Seated  => 'green',
            self::Removed => 'gray',
        };
    }
}
