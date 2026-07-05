<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order numbers are generated sequentially *per restaurant* (see PlaceOrder:
 * max(number) scoped by restaurant_id). The original table declared `number`
 * as globally unique, which guarantees a UNIQUE-constraint collision the moment
 * a second restaurant places its first order ("00001" already exists globally).
 *
 * This swaps the global unique index for a composite (restaurant_id, number)
 * unique, matching the per-restaurant numbering and keeping multi-tenant safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_number_unique');
            $table->unique(['restaurant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'number']);
            $table->unique('number');
        });
    }
};
