<?php

namespace Database\Factories;

use App\Enums\IngredientCategory;
use App\Enums\IngredientUnit;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Ingredient>
 */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name'          => ucfirst($this->faker->unique()->words(2, true)),
            'unit'          => $this->faker->randomElement(IngredientUnit::cases()),
            'category'      => $this->faker->randomElement(IngredientCategory::cases()),
            'cost_per_unit' => $this->faker->randomFloat(2, 1, 80),
        ];
    }
}
