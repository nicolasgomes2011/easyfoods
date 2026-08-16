<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\OperatingHour;
use App\Models\Restaurant;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the base restaurant (tenant) with everything the store front needs
 * to work: operating hours, delivery zones and payment settings.
 *
 * Idempotent: keyed by the restaurant slug and by the natural unique keys of
 * each child table, so re-running never duplicates rows and never overwrites
 * values the user changed by hand in the admin panel.
 */
class RestaurantSeeder extends Seeder
{
    /** Slug used across the app to resolve the store front URL. */
    public const SLUG = 'testando-teste';

    public function run(): void
    {
        $restaurant = Restaurant::firstOrCreate(
            ['slug' => self::SLUG],
            [
                'name'                 => 'Testando teste',
                'description'          => 'Hamburgueria e pizzaria — pedidos para entrega, retirada e mesa.',
                'phone'                => '51 99381-0808',
                'email'                => 'testegomes.nicolas.2011@gmail.com',
                'address_street'       => 'Avenida Independência',
                'address_number'       => '1200',
                'address_neighborhood' => 'Centro',
                'address_city'         => 'Porto Alegre',
                'address_state'        => 'RS',
                'address_zip'          => '90035-078',
                'is_open'              => true,
                'is_active'            => true,
                'accepts_delivery'     => true,
                'accepts_pickup'       => true,
                'accepts_dine_in'      => true,
                'min_order_minutes'    => 20,
                'max_order_minutes'    => 45,
            ]
        );

        $this->seedOperatingHours($restaurant);
        $this->seedDeliveryZones($restaurant);
        $this->seedPaymentSettings($restaurant);
    }

    /**
     * One row per weekday (0 = Sunday .. 6 = Saturday).
     * Monday is the closing day; unique key is [restaurant_id, weekday].
     */
    private function seedOperatingHours(Restaurant $restaurant): void
    {
        $hours = [
            0 => ['11:00', '22:30', false], // Domingo
            1 => ['11:00', '23:00', true],  // Segunda — fechado
            2 => ['11:00', '23:00', false], // Terça
            3 => ['11:00', '23:00', false], // Quarta
            4 => ['11:00', '23:00', false], // Quinta
            5 => ['11:00', '23:59', false], // Sexta
            6 => ['11:00', '23:59', false], // Sábado
        ];

        foreach ($hours as $weekday => [$opensAt, $closesAt, $isClosed]) {
            OperatingHour::firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'weekday' => $weekday],
                ['opens_at' => $opensAt, 'closes_at' => $closesAt, 'is_closed' => $isClosed]
            );
        }
    }

    /**
     * Delivery zones drive the delivery fee and the transit estimate at checkout.
     * No DB unique key here, so we match on [restaurant_id, name].
     */
    private function seedDeliveryZones(Restaurant $restaurant): void
    {
        $zones = [
            ['Centro',      'Centro',        5.00, 30],
            ['Zona Sul',    'Menino Deus',   8.00, 45],
            ['Zona Norte',  'Sarandi',      10.00, 55],
            ['Zona Leste',  'Partenon',     12.00, 60],
        ];

        foreach ($zones as [$name, $neighborhood, $fee, $minutes]) {
            DeliveryZone::firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $name],
                [
                    'neighborhood'      => $neighborhood,
                    'city'              => 'Porto Alegre',
                    'fee'               => $fee,
                    'estimated_minutes' => $minutes,
                    'is_active'         => true,
                ]
            );
        }
    }

    /**
     * Accepted payment methods, stored as store_settings rows.
     * Keys must match the ones read by resources/views/livewire/settings/payments.blade.php.
     */
    private function seedPaymentSettings(Restaurant $restaurant): void
    {
        $settings = [
            'payment_cash'          => '1',
            'payment_pix'           => '1',
            'payment_credit_card'   => '1',
            'payment_debit_card'    => '1',
            'payment_meal_voucher'  => '0',
        ];

        foreach ($settings as $key => $value) {
            StoreSetting::firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'key' => $key],
                ['value' => $value, 'type' => 'boolean']
            );
        }
    }
}
