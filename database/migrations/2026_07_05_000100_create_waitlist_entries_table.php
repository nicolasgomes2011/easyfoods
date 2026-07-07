<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Walk-in waitlist for the dining room (Phase 2 — dining/queue).
 * Entries are append-ish: status moves waiting → seated|removed and the row is
 * kept with its timestamps so the hostess screen and future reports can read
 * wait durations. Table link is nullable and survives table deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('party_name', 100);
            $table->unsignedTinyInteger('party_size');
            $table->string('phone', 30)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            // Hostess screen reads "waiting entries for this restaurant, FIFO".
            $table->index(['restaurant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
