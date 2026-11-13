<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers created by the guest checkout belong to one restaurant: the phone
 * identifies a returning customer of that restaurant only. Nullable so rows
 * that predate it (and a future cross-restaurant customer login) stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['restaurant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'phone']);
            $table->dropConstrainedForeignId('restaurant_id');
        });
    }
};
