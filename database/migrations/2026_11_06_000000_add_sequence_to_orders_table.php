<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order numbers were generated with max(CAST(number AS INTEGER)), which only
 * SQLite accepts. An integer `sequence` per restaurant replaces the cast:
 * PlaceOrder takes max(sequence) + 1 and formats `number` from it, so the
 * query is portable and never depends on string ordering.
 *
 * Existing orders are backfilled from their numeric `number` in PHP (no
 * database-specific SQL). The column stays nullable so the backfill can never
 * fail on a legacy row; every order placed from now on gets a value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->nullable()->after('number');
        });

        DB::table('orders')
            ->select(['id', 'number'])
            ->orderBy('id')
            ->chunkById(500, function ($orders) {
                foreach ($orders as $order) {
                    if (ctype_digit((string) $order->number)) {
                        DB::table('orders')
                            ->where('id', $order->id)
                            ->update(['sequence' => (int) $order->number]);
                    }
                }
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique(['restaurant_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['restaurant_id', 'sequence']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sequence');
        });
    }
};
