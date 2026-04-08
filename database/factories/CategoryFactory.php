<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Entradas', 'Pratos Principais', 'Massas', 'Grelhados',
            'Pizzas', 'Sobremesas', 'Bebidas', 'Combos', 'Lanches',
        ]);

        return [
            'restaurant_id' => Restaurant::factory(),
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'description'   => $this->faker->optional()->sentence(),
            'sort_order'    => $this->faker->numberBetween(0, 100),
            'is_active'     => true,
        ];
    }
}
