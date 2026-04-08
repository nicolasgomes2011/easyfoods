<?php

namespace Database\Factories;

use App\Enums\ProductAvailabilityStatus;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Frango Grelhado', 'Filé Mignon', 'Salmão ao Molho', 'Risoto de Funghi',
            'Hambúrguer Artesanal', 'Pizza Margherita', 'Lasanha Bolonhesa',
            'Fraldinha na Brasa', 'Parmegiana de Berinjela', 'Camarão Empanado',
        ]);

        return [
            'restaurant_id'       => Restaurant::factory(),
            'category_id'         => Category::factory(),
            'name'                => $name,
            'slug'                => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'description'         => $this->faker->sentence(12),
            'price'               => $this->faker->randomFloat(2, 15, 120),
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => $this->faker->numberBetween(0, 100),
            'is_featured'         => $this->faker->boolean(20),
        ];
    }

    public function unavailable(): static
    {
        return $this->state(['availability_status' => ProductAvailabilityStatus::Unavailable]);
    }

    public function outOfStock(): static
    {
        return $this->state(['availability_status' => ProductAvailabilityStatus::OutOfStock]);
    }
}
