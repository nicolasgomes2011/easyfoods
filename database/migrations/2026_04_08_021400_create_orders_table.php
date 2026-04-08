<?php

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique()->comment('Human-readable order number, e.g. #00123');
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Status
            $table->string('status')->default(OrderStatus::PendingConfirmation->value);

            // Delivery
            $table->string('delivery_type')->default(DeliveryType::Delivery->value);

            // Delivery address snapshot (copied at order time, immutable)
            $table->string('delivery_address_street')->nullable();
            $table->string('delivery_address_number')->nullable();
            $table->string('delivery_address_complement')->nullable();
            $table->string('delivery_address_neighborhood')->nullable();
            $table->string('delivery_address_city')->nullable();
            $table->string('delivery_address_state', 2)->nullable();
            $table->string('delivery_address_zip', 9)->nullable();

            // Pricing snapshot (all calculated server-side, immutable)
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Customer info snapshot
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('canceled_at')->nullable();

            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['restaurant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
