<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log of items added to / removed from an open order after it was
     * placed: what (snapshot), who, why and when. The order status is not involved.
     */
    public function up(): void
    {
        Schema::create('order_item_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // The item row is deleted on removal, so the link may become null; the snapshot stays.
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);

            // Snapshot of the line at the time of the change
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 10, 2);

            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');

            $table->index(['order_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_changes');
    }
};
