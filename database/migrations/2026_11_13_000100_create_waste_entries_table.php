<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only waste log: what was thrown away (ingredient snapshot), how much,
     * why, who and when. A correction is a new row pointing at the entry it
     * corrects (adjusts_id) with the difference; rows are never updated or deleted.
     */
    public function up(): void
    {
        Schema::create('waste_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // Ingredients are archived, not deleted; the snapshot below keeps the entry readable anyway.
            $table->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('adjusts_id')->nullable()->constrained('waste_entries')->nullOnDelete();

            // Snapshot of the ingredient at the time of the discard
            $table->string('ingredient_name', 100);
            $table->string('unit', 10);       // App\Enums\IngredientUnit
            $table->decimal('unit_cost', 12, 4);

            // In the ingredient unit; negative only on a correction that lowers the original.
            $table->decimal('quantity', 12, 3);
            $table->string('reason', 30);     // App\Enums\WasteReason
            $table->string('note', 255)->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['restaurant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_entries');
    }
};
