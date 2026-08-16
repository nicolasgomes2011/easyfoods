<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Entry point for `php artisan db:seed`.
 *
 * Every seeder below is idempotent (firstOrCreate on natural keys), so this can
 * be run against an existing database as many times as needed: nothing is
 * duplicated and nothing already edited by hand is overwritten.
 *
 * Order matters — the catalog, tables and orders all need the restaurant first.
 *
 * Demo data (customers and orders) is skipped in production; a single seeder can
 * always be run on its own, e.g. `php artisan db:seed --class=CatalogSeeder`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Baseline: staff accounts, the tenant and everything it needs to sell.
        $this->call([
            UserSeeder::class,
            RestaurantSeeder::class,
            CatalogSeeder::class,
            DiningTableSeeder::class,
        ]);

        if (app()->environment('production')) {
            $this->command?->info('Demo data skipped (production environment).');

            return;
        }

        // Demo data: only useful for development and manual testing.
        $this->call([
            CustomerSeeder::class,
            DemoOrderSeeder::class,
        ]);
    }
}
