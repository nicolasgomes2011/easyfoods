<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->foreignId('restaurant_id')->nullable()->constrained()->nullOnDelete()->after('uuid');
        });

        // Backfill uuid for any existing rows
        \DB::table('dining_tables')->whereNull('uuid')->get()->each(
            fn ($row) => \DB::table('dining_tables')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()])
        );
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn(['uuid', 'restaurant_id']);
        });
    }
};
