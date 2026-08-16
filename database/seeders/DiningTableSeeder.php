<?php

namespace Database\Seeders;

use App\Enums\DiningTableStatus;
use App\Models\DiningTable;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

/**
 * Seeds the dine-in floor plan (mesas). Each table gets a uuid from the model's
 * creating hook — that uuid is what the QR code on the table points to.
 *
 * Idempotent: matched on [restaurant_id, number]. Existing tables keep their
 * uuid and status, so QR codes already printed keep working after re-seeding.
 */
class DiningTableSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', RestaurantSeeder::SLUG)->first()
            ?? Restaurant::first();

        if (! $restaurant) {
            $this->command?->warn('DiningTableSeeder skipped: no restaurant found. Run RestaurantSeeder first.');

            return;
        }

        // number => capacity
        $tables = [
            '1'  => 2,  '2'  => 2,  '3'  => 4,  '4'  => 4,
            '5'  => 4,  '6'  => 4,  '7'  => 6,  '8'  => 6,
            '9'  => 8,  '10' => 8,
            'B1' => 2,  'B2' => 2, // banquetas do balcão
        ];

        foreach ($tables as $number => $capacity) {
            DiningTable::firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'number' => (string) $number],
                [
                    'capacity' => $capacity,
                    'status'   => DiningTableStatus::Free,
                ]
            );
        }
    }
}
