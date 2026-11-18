<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Milestone column => status whose first entry in the history starts it. */
    private const MILESTONES = [
        'preparation_started_at' => 'in_preparation',
        'out_for_delivery_at'    => 'out_for_delivery',
    ];

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('preparation_started_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
        });

        // Backfill existing orders from the append-only status history
        // (correlated subquery: portable across SQLite and MySQL).
        foreach (self::MILESTONES as $column => $status) {
            DB::table('orders')
                ->whereNull($column)
                ->update([
                    $column => DB::table('order_status_histories')
                        ->selectRaw('min(changed_at)')
                        ->whereColumn('order_status_histories.order_id', 'orders.id')
                        ->where('order_status_histories.to_status', $status),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(array_keys(self::MILESTONES));
        });
    }
};
