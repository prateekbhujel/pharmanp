<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'cc_rate')) {
                $table->decimal('cc_rate', 8, 2)->default(0)->after('mrp');
            }

            if (! Schema::hasColumn('purchase_items', 'free_goods_value')) {
                $table->decimal('free_goods_value', 14, 2)->default(0)->after('discount_amount');
            }
        });

        Schema::table('sales_invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_invoice_items', 'free_quantity')) {
                $table->decimal('free_quantity', 14, 3)->default(0)->after('quantity');
            }

            if (! Schema::hasColumn('sales_invoice_items', 'cc_rate')) {
                $table->decimal('cc_rate', 8, 2)->default(0)->after('discount_percent');
            }

            if (! Schema::hasColumn('sales_invoice_items', 'free_goods_value')) {
                $table->decimal('free_goods_value', 14, 2)->default(0)->after('discount_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            foreach (['free_quantity', 'cc_rate', 'free_goods_value'] as $column) {
                if (Schema::hasColumn('sales_invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            foreach (['cc_rate', 'free_goods_value'] as $column) {
                if (Schema::hasColumn('purchase_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
