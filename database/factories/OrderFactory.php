<?php

namespace Database\Factories;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    private static int $sequence = 1;

    public function definition(): array
    {
        $subtotal    = $this->faker->randomFloat(2, 20, 150);
        $deliveryFee = $this->faker->randomElement([0, 5.00, 7.50, 10.00]);
        $discount    = 0;
        $total       = $subtotal + $deliveryFee - $discount;

        return [
            'number'         => str_pad(self::$sequence++, 5, '0', STR_PAD_LEFT),
            'restaurant_id'  => Restaurant::factory(),
            'customer_id'    => Customer::factory(),
            'status'         => OrderStatus::PendingConfirmation,
            'delivery_type'  => DeliveryType::Delivery,
            'delivery_address_street'       => $this->faker->streetName(),
            'delivery_address_number'       => $this->faker->buildingNumber(),
            'delivery_address_neighborhood' => 'Centro',
            'delivery_address_city'         => $this->faker->city(),
            'delivery_address_state'        => 'SP',
            'delivery_address_zip'          => $this->faker->numerify('#####-###'),
            'subtotal'       => $subtotal,
            'delivery_fee'   => $deliveryFee,
            'discount'       => $discount,
            'total'          => $total,
            'customer_name'  => $this->faker->name(),
            'customer_phone' => $this->faker->numerify('(##) #####-####'),
        ];
    }

    public function withStatus(OrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function inPreparation(): static
    {
        return $this->state(['status' => OrderStatus::InPreparation]);
    }

    public function confirmed(): static
    {
        return $this->state([
            'status'       => OrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status'       => OrderStatus::Completed,
            'confirmed_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state([
            'status'      => OrderStatus::Canceled,
            'canceled_at' => now(),
        ]);
    }

    public function pickup(): static
    {
        return $this->state([
            'delivery_type' => DeliveryType::Pickup,
            'delivery_fee'  => 0,
        ]);
    }
}
