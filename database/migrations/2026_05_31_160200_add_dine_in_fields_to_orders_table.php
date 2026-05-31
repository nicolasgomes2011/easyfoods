<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Public tracking token — exposed in URLs instead of numeric ID
            $table->uuid('token')->nullable()->unique()->after('number');

            // Dine-in: nullable FK + snapshot of table number at order time
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete()->after('customer_id');
            $table->string('table_number', 20)->nullable()->after('dining_table_id');
        });

        // Backfill token for any existing orders
        \DB::table('orders')->whereNull('token')->get()->each(
            fn ($row) => \DB::table('orders')->where('id', $row->id)->update(['token' => (string) Str::uuid()])
        );
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['dining_table_id']);
            $table->dropColumn(['token', 'dining_table_id', 'table_number']);
        });
    }
};
