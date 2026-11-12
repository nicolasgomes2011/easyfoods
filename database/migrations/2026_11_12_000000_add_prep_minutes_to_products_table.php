<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base preparation time of a product, in whole minutes. The order's ready-time
 * estimate is the slowest item's prep_minutes (static version, before the
 * learned ETA). Existing products get the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('prep_minutes')->default(15)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('prep_minutes');
        });
    }
};
