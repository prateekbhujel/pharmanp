<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'received_purchase_id')) {
                $table->unsignedBigInteger('received_purchase_id')->nullable()->after('status')->index('purchase_orders_received_purchase_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'received_purchase_id')) {
                $table->dropIndex('purchase_orders_received_purchase_idx');
                $table->dropColumn('received_purchase_id');
            }
        });
    }
};
