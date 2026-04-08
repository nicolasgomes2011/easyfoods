<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\OperatingHour;
use App\Models\Restaurant;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::create([
            'name'              => 'EasyFoods Demo',
            'slug'              => 'easyfoods-demo',
            'description'       => 'Restaurante demonstração da plataforma EasyFoods.',
            'phone'             => '(11) 99999-0000',
            'email'             => 'contato@easyfoods.demo',
            'is_open'           => true,
            'is_active'         => true,
            'accepts_delivery'  => true,
            'accepts_pickup'    => true,
            'address_street'    => 'Rua das Flores',
            'address_number'    => '100',
            'address_neighborhood' => 'Centro',
            'address_city'      => 'São Paulo',
            'address_state'     => 'SP',
            'address_zip'       => '01310-100',
            'min_order_minutes' => 25,
            'max_order_minutes' => 45,
        ]);

        // Operating hours: Mon–Sat 11h–23h, Sunday closed
        $hours = [
            0 => ['opens_at' => '11:00', 'closes_at' => '22:00', 'is_closed' => true],
            1 => ['opens_at' => '11:00', 'closes_at' => '23:00', 'is_closed' => false],
            2 => ['opens_at' => '11:00', 'closes_at' => '23:00', 'is_closed' => false],
            3 => ['opens_at' => '11:00', 'closes_at' => '23:00', 'is_closed' => false],
            4 => ['opens_at' => '11:00', 'closes_at' => '23:00', 'is_closed' => false],
            5 => ['opens_at' => '11:00', 'closes_at' => '00:00', 'is_closed' => false],
            6 => ['opens_at' => '11:00', 'closes_at' => '00:00', 'is_closed' => false],
        ];

        foreach ($hours as $weekday => $config) {
            OperatingHour::create(array_merge(['restaurant_id' => $restaurant->id, 'weekday' => $weekday], $config));
        }

        // Delivery zones
        DeliveryZone::create([
            'restaurant_id'     => $restaurant->id,
            'name'              => 'Centro',
            'neighborhood'      => 'Centro',
            'city'              => 'São Paulo',
            'fee'               => 5.00,
            'estimated_minutes' => 30,
            'is_active'         => true,
        ]);

        DeliveryZone::create([
            'restaurant_id'     => $restaurant->id,
            'name'              => 'Bairro próximo',
            'neighborhood'      => null,
            'city'              => 'São Paulo',
            'fee'               => 8.00,
            'estimated_minutes' => 45,
            'is_active'         => true,
        ]);

        // Default store settings
        $settings = [
            ['key' => 'min_order_value',       'value' => '20.00',    'type' => 'string'],
            ['key' => 'free_delivery_above',   'value' => '80.00',    'type' => 'string'],
            ['key' => 'whatsapp_notifications', 'value' => 'false',   'type' => 'boolean'],
            ['key' => 'payment_methods',       'value' => '["cash","pix","credit_card","debit_card"]', 'type' => 'json'],
        ];

        foreach ($settings as $setting) {
            StoreSetting::create(array_merge(['restaurant_id' => $restaurant->id], $setting));
        }
    }
}
