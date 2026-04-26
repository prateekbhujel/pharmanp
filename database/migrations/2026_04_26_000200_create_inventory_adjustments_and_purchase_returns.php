<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->date('adjustment_date')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('batch_id')->index();
            $table->string('adjustment_type', 40)->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['product_id', 'adjustment_date'], 'stock_adjustments_product_date_idx');
            $table->index(['batch_id', 'adjustment_date'], 'stock_adjustments_batch_date_idx');
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('purchase_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('return_no')->unique();
            $table->date('return_date')->index();
            $table->string('status', 40)->default('posted')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['supplier_id', 'return_date'], 'purchase_returns_supplier_date_idx');
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id')->index();
            $table->unsignedBigInteger('purchase_item_id')->nullable()->index();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->decimal('return_qty', 14, 3)->default(0);
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('net_rate', 14, 2)->default(0);
            $table->decimal('return_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['product_id', 'batch_id'], 'purchase_return_items_product_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('stock_adjustments');
    }
};
