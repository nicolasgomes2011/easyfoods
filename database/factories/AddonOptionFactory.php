<?php

namespace Database\Factories;

use App\Models\AddonGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddonOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'addon_group_id' => AddonGroup::factory(),
            'name'           => $this->faker->randomElement([
                'Molho Barbecue', 'Molho Ranch', 'Cheddar', 'Bacon',
                'Queijo Extra', 'Ovo', 'Abacate', 'Catupiry',
                'Bem Passado', 'Ao Ponto', 'Mal Passado',
            ]),
            'price'          => $this->faker->randomElement([0, 0, 2.00, 3.50, 5.00]),
            'is_active'      => true,
            'sort_order'     => $this->faker->numberBetween(0, 20),
        ];
    }

    public function free(): static
    {
        return $this->state(['price' => 0]);
    }
}
