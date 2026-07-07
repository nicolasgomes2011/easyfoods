<?php

namespace Database\Factories;

use App\Enums\WaitlistStatus;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class WaitlistEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'party_name'    => $this->faker->name(),
            'party_size'    => $this->faker->numberBetween(1, 8),
            'phone'         => $this->faker->numerify('(##) #####-####'),
            'status'        => WaitlistStatus::Waiting,
        ];
    }

    public function seated(): static
    {
        return $this->state([
            'status'    => WaitlistStatus::Seated,
            'seated_at' => now(),
        ]);
    }
}
