<?php

use App\Enums\ProductAvailabilityStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('availability_status')->default(ProductAvailabilityStatus::Available->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->unique(['restaurant_id', 'slug']);
            $table->index(['category_id', 'availability_status', 'sort_order']);
            $table->index(['restaurant_id', 'availability_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
