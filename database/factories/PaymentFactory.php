<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method'   => PaymentMethod::Cash,
            'status'   => PaymentStatus::Pending,
            'amount'   => $this->faker->randomFloat(2, 20, 200),
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status'  => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function pix(): static
    {
        return $this->state(['method' => PaymentMethod::Pix]);
    }

    public function creditCard(): static
    {
        return $this->state(['method' => PaymentMethod::CreditCard]);
    }
}
