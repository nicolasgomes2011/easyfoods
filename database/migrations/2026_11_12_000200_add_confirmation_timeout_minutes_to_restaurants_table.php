<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minutes an order may wait in pending_confirmation before the scheduler
     * cancels it (App\Jobs\ExpireUnconfirmedOrders). 0 turns it off.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->unsignedSmallInteger('confirmation_timeout_minutes')->default(10)->after('max_order_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('confirmation_timeout_minutes');
        });
    }
};
