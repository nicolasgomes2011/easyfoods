<?php

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Enums\WasteReason;
use App\Models\Restaurant;
use App\Support\CurrentRestaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WasteEntry>
 */
class WasteEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id'   => fn () => CurrentRestaurant::id() ?? Restaurant::factory(),
            'ingredient_id'   => null,
            'ingredient_name' => ucfirst($this->faker->words(2, true)),
            'unit'            => IngredientUnit::Kilogram,
            'unit_cost'       => $this->faker->randomFloat(2, 1, 80),
            'quantity'        => $this->faker->randomFloat(3, 0.1, 10),
            'reason'          => $this->faker->randomElement(WasteReason::cases()),
        ];
    }
}
