<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete()->after('customer_id');
            $table->string('delivery_type')->nullable()->after('dining_table_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['dining_table_id']);
            $table->dropColumn(['dining_table_id', 'delivery_type']);
        });
    }
};
