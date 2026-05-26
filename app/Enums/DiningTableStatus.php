<?php

namespace App\Enums;

enum DiningTableStatus: string
{
    case Free     = 'free';
    case Occupied = 'occupied';
    case Reserved = 'reserved';

    public function label(): string
    {
        return match($this) {
            self::Free     => 'Livre',
            self::Occupied => 'Ocupada',
            self::Reserved => 'Reservada',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Free     => 'green',
            self::Occupied => 'orange',
            self::Reserved => 'blue',
        };
    }
}
