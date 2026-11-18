<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Part of the day an order is prepared in (restaurant local time). The learned
 * ETA only averages samples from the same band, so a burger made at lunch rush
 * is not compared with one made mid-afternoon.
 */
enum TimeBand: string
{
    case Breakfast = 'breakfast';  // 05:00–10:59
    case Lunch     = 'lunch';      // 11:00–14:59
    case Afternoon = 'afternoon';  // 15:00–17:59
    case Dinner    = 'dinner';     // 18:00–22:59
    case LateNight = 'late_night'; // 23:00–04:59

    public static function at(CarbonInterface $moment): self
    {
        $hour = (int) $moment->copy()->setTimezone(config('app.timezone'))->format('G');

        return match (true) {
            $hour >= 5 && $hour < 11  => self::Breakfast,
            $hour >= 11 && $hour < 15 => self::Lunch,
            $hour >= 15 && $hour < 18 => self::Afternoon,
            $hour >= 18 && $hour < 23 => self::Dinner,
            default                   => self::LateNight,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'Café da manhã',
            self::Lunch     => 'Almoço',
            self::Afternoon => 'Tarde',
            self::Dinner    => 'Jantar',
            self::LateNight => 'Madrugada',
        };
    }
}
