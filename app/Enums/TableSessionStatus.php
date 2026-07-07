<?php

namespace App\Enums;

enum TableSessionStatus: string
{
    case Open   = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match($this) {
            self::Open   => 'Aberta',
            self::Closed => 'Fechada',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Open   => 'orange',
            self::Closed => 'zinc',
        };
    }
}
