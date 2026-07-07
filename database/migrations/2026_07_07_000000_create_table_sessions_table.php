<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A table session groups every order placed during one sitting (the "tab").
 * It opens on the first dine-in order at a table and stays open — later dine-in
 * orders at the same table join it — until staff close it, which also frees the
 * table. Only one open session per table is allowed (enforced in code, not a DB
 * constraint: partial unique indexes aren't portable to SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // "Does this table already have an open session?" is the hot lookup (every PlaceOrder call).
            $table->index(['dining_table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
