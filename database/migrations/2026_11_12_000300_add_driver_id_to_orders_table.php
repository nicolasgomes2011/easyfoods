<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The driver who accepted the delivery (driver panel). Null while the order is up for grabs. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete()->after('table_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
        });
    }
};
