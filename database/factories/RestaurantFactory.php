<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RestaurantFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name'              => $name,
            // restaurants.slug is unique and the pt_BR company() names repeat often:
            // a random suffix keeps tests with several restaurants from colliding.
            'slug'              => Str::slug($name).'-'.Str::lower(Str::random(8)),
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

    /**
     * Factory stores are open around the clock (00:00–00:00 every weekday): orders
     * are refused while a store is closed, and ordering tests must not depend on
     * the wall clock. Tests about opening hours start from withoutHours().
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Restaurant $restaurant) {
            foreach (range(0, 6) as $weekday) {
                $restaurant->operatingHours()->create([
                    'weekday'   => $weekday,
                    'opens_at'  => '00:00',
                    'closes_at' => '00:00',
                    'is_closed' => false,
                ]);
            }
        });
    }

    /** No operating hours at all (the test sets its own). Runs after configure()'s callback. */
    public function withoutHours(): static
    {
        return $this->afterCreating(function (Restaurant $restaurant) {
            $restaurant->operatingHours()->delete();
            $restaurant->unsetRelation('operatingHours');
        });
    }
}
