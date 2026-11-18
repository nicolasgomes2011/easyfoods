<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The person at the table this item belongs to (comanda). Null = no person
     * (shared): the item still counts in the table total. The item snapshot and
     * the order totals do not depend on it.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('table_session_guest_id')->nullable()->after('prepared_at')
                ->constrained('table_session_guests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['table_session_guest_id']);
            $table->dropColumn('table_session_guest_id');
        });
    }
};
