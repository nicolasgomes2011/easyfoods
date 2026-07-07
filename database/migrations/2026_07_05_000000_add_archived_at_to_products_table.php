<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real archive is independent from availability: a paused (unavailable) product
 * still shows greyed-out on the storefront, while an archived one disappears from
 * the panel list and the menu entirely — but keeps its row so historical order
 * snapshots and FKs stay intact (order/cart FKs are nullOnDelete by design).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('availability_status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
