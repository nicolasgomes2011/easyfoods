<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoPurge extends Command
{
    protected $signature   = 'demo:purge {--force : Skip confirmation prompt}';
    protected $description = 'Remove all demo/seed data, preserving admin users only';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will permanently delete all restaurants, orders, products, customers, and related data. Continue?')) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->info('Purging demo data...');

        DB::statement('PRAGMA foreign_keys = OFF');

        $tables = [
            'payments',
            'order_item_addons',
            'order_items',
            'order_status_histories',
            'orders',
            'cart_item_addons',
            'cart_items',
            'carts',
            'addon_options',
            'addon_groups',
            'product_variants',
            'products',
            'categories',
            'customer_addresses',
            'customers',
            'delivery_zones',
            'operating_hours',
            'store_settings',
            'dining_tables',
            'restaurants',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->line("  <comment>truncated</comment> {$table}");
        }

        DB::statement('PRAGMA foreign_keys = ON');

        $this->newLine();
        $this->info('Done. All demo data removed. Admin users preserved.');

        return self::SUCCESS;
    }
}
