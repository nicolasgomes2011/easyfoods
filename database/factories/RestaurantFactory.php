<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RestaurantFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name'              => $name,
            'slug'              => Str::slug($name),
            'description'       => $this->faker->sentence(10),
            'phone'             => $this->faker->numerify('(##) #####-####'),
            'email'             => $this->faker->companyEmail(),
            'is_open'           => true,
            'is_active'         => true,
            'accepts_delivery'  => true,
            'accepts_pickup'    => true,
            'address_street'    => $this->faker->streetName(),
            'address_number'    => $this->faker->buildingNumber(),
            'address_neighborhood' => 'Centro',
            'address_city'      => $this->faker->city(),
            'address_state'     => 'SP',
            'address_zip'       => $this->faker->numerify('#####-###'),
            'min_order_minutes' => 25,
            'max_order_minutes' => 45,
        ];
    }
}
