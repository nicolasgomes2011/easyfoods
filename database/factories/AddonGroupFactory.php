<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddonGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id'  => Product::factory(),
            'name'        => $this->faker->randomElement([
                'Molhos', 'Adicionais', 'Ponto da carne', 'Acompanhamentos',
                'Extras', 'Bebida', 'Tamanho', 'Massa',
            ]),
            'required'    => false,
            'min_choices' => 0,
            'max_choices' => 3,
            'sort_order'  => $this->faker->numberBetween(0, 10),
            'is_active'   => true,
        ];
    }

    public function required(): static
    {
        return $this->state([
            'required'    => true,
            'min_choices' => 1,
            'max_choices' => 1,
        ]);
    }
}
