<?php

namespace App\Enums;

enum WaiterCallStatus: string
{
    case Pending      = 'pending';
    case Acknowledged = 'acknowledged';

    public function label(): string
    {
        return match($this) {
            self::Pending      => 'Aguardando',
            self::Acknowledged => 'Atendido',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending      => 'yellow',
            self::Acknowledged => 'green',
        };
    }
}
