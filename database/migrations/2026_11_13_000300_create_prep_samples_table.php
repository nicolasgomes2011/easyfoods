<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only prep history for the learned ETA (docs/scope/eta-intelligence.md):
     * one row per product of an order when the order goes from "in preparation"
     * to "ready", with the real duration and the conditions it was made under
     * (kitchen load, time band, day of week). Rows are never updated or deleted.
     */
    public function up(): void
    {
        Schema::create('prep_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('quantity');          // units of the product in the order
            $table->unsignedInteger('prep_seconds');      // preparation_started_at → ready_at
            $table->unsignedSmallInteger('kitchen_load'); // other orders in preparation when it started
            $table->string('time_band', 20);              // App\Enums\TimeBand of the start
            $table->unsignedTinyInteger('day_of_week');   // 0 = Sunday … 6 = Saturday

            $table->dateTime('started_at');
            $table->dateTime('ready_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['order_id', 'product_id']);
            $table->index(['restaurant_id', 'product_id', 'time_band', 'ready_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prep_samples');
    }
};
