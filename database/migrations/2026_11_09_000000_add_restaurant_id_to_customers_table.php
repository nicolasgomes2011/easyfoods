<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers created by the guest checkout belong to one restaurant: the phone
 * identifies a returning customer of that restaurant only. Nullable so rows
 * that predate it (and a future cross-restaurant customer login) stay valid.
 *
 * (restaurant_id, phone) is unique so two checkouts with the same phone at the
 * same moment cannot create two customers. NULLs never collide, so older rows
 * without restaurant_id are not affected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->unique(['restaurant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Foreign key first: on MySQL the unique index may be the one backing it.
            $table->dropForeign(['restaurant_id']);
            $table->dropUnique(['restaurant_id', 'phone']);
            $table->dropColumn('restaurant_id');
        });
    }
};
