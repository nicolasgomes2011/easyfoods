<?php

namespace Database\Factories;

use App\Enums\TableSessionStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TableSessionFactory extends Factory
{
    /**
     * dining_table_id has no default — DiningTable has no factory of its own
     * in this codebase. Pass one explicitly via ->for($table, 'diningTable').
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'status'        => TableSessionStatus::Open,
            'opened_at'     => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'status'    => TableSessionStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
