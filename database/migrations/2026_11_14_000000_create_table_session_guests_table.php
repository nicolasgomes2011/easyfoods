<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People at a table (comanda): staff adds and names them on the tab of the
     * table session, so each item of the session's orders can be assigned to
     * one of them (order_items.table_session_guest_id) and the bill split later.
     */
    public function up(): void
    {
        Schema::create('table_session_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_session_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();

            $table->index(['restaurant_id', 'table_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_session_guests');
    }
};
