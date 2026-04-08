<?php

use App\Enums\ProductAvailabilityStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('e.g. P, M, G, 300ml, 500ml');
            $table->decimal('price', 10, 2);
            $table->string('availability_status')->default(ProductAvailabilityStatus::Available->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'availability_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
