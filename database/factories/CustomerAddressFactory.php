<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'  => Customer::factory(),
            'label'        => $this->faker->randomElement(['Casa', 'Trabalho', 'Outro']),
            'street'       => $this->faker->streetName(),
            'number'       => $this->faker->buildingNumber(),
            'complement'   => $this->faker->optional()->secondaryAddress(),
            'neighborhood' => 'Centro',
            'city'         => $this->faker->city(),
            'state'        => 'SP',
            'zip'          => $this->faker->numerify('#####-###'),
            'is_default'   => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
