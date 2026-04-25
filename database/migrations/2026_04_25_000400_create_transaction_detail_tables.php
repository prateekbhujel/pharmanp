<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('order_no')->unique();
            $table->date('order_date')->index();
            $table->date('expected_date')->nullable()->index();
            $table->string('status', 40)->default('draft')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['supplier_id', 'order_date'], 'purchase_orders_supplier_date_idx');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->string('batch_no', 120)->nullable()->index();
            $table->date('manufactured_at')->nullable()->index();
            $table->date('expires_at')->nullable()->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('free_quantity', 14, 3)->default(0);
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('mrp', 14, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['product_id', 'expires_at'], 'purchase_items_product_expiry_idx');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('payment_no')->unique();
            $table->date('payment_date')->index();
            $table->string('direction', 20)->index();
            $table->string('party_type', 40)->nullable()->index();
            $table->unsignedBigInteger('party_id')->nullable()->index();
            $table->string('payment_mode', 60)->default('cash')->index();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('reference_no')->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['party_type', 'party_id', 'payment_date'], 'payments_party_date_idx');
        });

        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->date('transaction_date')->index();
            $table->string('account_type', 60)->index();
            $table->string('party_type', 40)->nullable()->index();
            $table->unsignedBigInteger('party_id')->nullable()->index();
            $table->string('source_type', 80)->index();
            $table->unsignedBigInteger('source_id')->index();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'account_transactions_source_idx');
            $table->index(['party_type', 'party_id'], 'account_transactions_party_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
