<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ready-time estimate shown to the customer ("Pronto às HH:MM"): set when the
 * order is confirmed and only ever pushed forward, at most twice
 * (eta_adjustments counts the pushes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('estimated_ready_at')->nullable();
            $table->unsignedTinyInteger('eta_adjustments')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['estimated_ready_at', 'eta_adjustments']);
        });
    }
};
