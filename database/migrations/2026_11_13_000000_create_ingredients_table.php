<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('unit', 10);      // App\Enums\IngredientUnit
            $table->string('category', 20);  // App\Enums\IngredientCategory
            // 4 decimals: a per-gram cost is a fraction of a cent (R$ 45/kg = R$ 0,045/g).
            $table->decimal('cost_per_unit', 12, 4)->default(0);
            // Archived instead of deleted, so later records can keep pointing at it.
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['restaurant_id', 'name']);
            $table->index(['restaurant_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
