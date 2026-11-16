<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The restaurant each staff member works in (multi-tenant 1/4). Nullable for now:
     * existing staff are filled with the restaurant the panel shows today (lowest id).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->after('id')->constrained('restaurants')->nullOnDelete();
        });

        $currentRestaurantId = DB::table('restaurants')->orderBy('id')->value('id');

        if ($currentRestaurantId !== null) {
            DB::table('users')->whereNull('restaurant_id')->update(['restaurant_id' => $currentRestaurantId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });
    }
};
