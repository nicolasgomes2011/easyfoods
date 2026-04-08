<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method')->default(PaymentMethod::Cash->value);
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->decimal('amount', 10, 2);

            // Change for cash payments
            $table->decimal('amount_tendered', 10, 2)->nullable()->comment('Amount given by customer (cash)');
            $table->decimal('change_due', 10, 2)->nullable();

            // Gateway integration preparation
            $table->string('gateway')->nullable()->comment('e.g. stripe, mercadopago, pagseguro');
            $table->string('gateway_transaction_id')->nullable();
            $table->string('gateway_status')->nullable();
            $table->json('gateway_payload')->nullable()->comment('Raw response from gateway');

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('gateway_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
