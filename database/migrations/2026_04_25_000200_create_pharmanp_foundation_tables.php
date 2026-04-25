<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->boolean('is_private')->default(false)->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('pan_number', 60)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('country', 80)->default('Nepal');
            $table->string('company_type', 40)->default('pharmacy')->index();
            $table->decimal('default_cc_rate', 8, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index('name', 'companies_name_idx');
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 60)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 30)->nullable()->index();
            $table->string('type', 30)->default('both')->index();
            $table->decimal('factor', 12, 4)->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'name'], 'units_company_name_unique');
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->string('code', 60)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedBigInteger('manufacturer_id')->nullable()->index();
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->string('sku', 80)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('name');
            $table->string('generic_name')->nullable()->index();
            $table->string('composition')->nullable();
            $table->string('formulation', 80)->nullable()->index();
            $table->string('strength', 80)->nullable();
            $table->string('rack_location', 80)->nullable();
            $table->decimal('mrp', 14, 2)->default(0);
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('cc_rate', 8, 2)->default(0);
            $table->unsignedInteger('reorder_level')->default(10);
            $table->unsignedInteger('reorder_quantity')->default(0);
            $table->boolean('is_batch_tracked')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'sku'], 'products_company_sku_unique');
            $table->unique(['company_id', 'barcode'], 'products_company_barcode_unique');
            $table->index(['name', 'generic_name'], 'products_name_generic_idx');
            $table->index(['is_active', 'deleted_at'], 'products_active_deleted_idx');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('pan_number', 60)->nullable()->index();
            $table->string('address')->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('pan_number', 60)->nullable()->index();
            $table->string('address')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedBigInteger('purchase_id')->nullable()->index();
            $table->string('batch_no', 120);
            $table->string('barcode', 120)->nullable()->index();
            $table->date('manufactured_at')->nullable()->index();
            $table->date('expires_at')->nullable()->index();
            $table->decimal('quantity_received', 14, 3)->default(0);
            $table->decimal('quantity_available', 14, 3)->default(0);
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('mrp', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'batch_no'], 'batches_company_product_batch_unique');
            $table->index(['product_id', 'expires_at'], 'batches_product_expiry_idx');
            $table->index(['is_active', 'quantity_available'], 'batches_active_qty_idx');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->date('movement_date')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->string('movement_type', 60)->index();
            $table->decimal('quantity_in', 14, 3)->default(0);
            $table->decimal('quantity_out', 14, 3)->default(0);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['product_id', 'movement_date'], 'stock_movements_product_date_idx');
            $table->index(['batch_id', 'movement_date'], 'stock_movements_batch_date_idx');
            $table->index(['reference_type', 'reference_id'], 'stock_movements_reference_idx');
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('purchase_no')->unique();
            $table->string('supplier_invoice_no')->nullable()->index();
            $table->date('purchase_date')->index();
            $table->string('status', 40)->default('received')->index();
            $table->string('payment_status', 40)->default('unpaid')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('medical_representative_id')->nullable()->index();
            $table->string('invoice_no')->unique();
            $table->date('invoice_date')->index();
            $table->string('sale_type', 40)->default('retail')->index();
            $table->string('status', 40)->default('confirmed')->index();
            $table->string('payment_status', 40)->default('unpaid')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_invoice_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('mrp', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('voucher_no')->unique();
            $table->date('voucher_date')->index();
            $table->string('voucher_type', 40)->index();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id')->index();
            $table->unsignedInteger('line_no')->default(1);
            $table->string('account_type', 60)->index();
            $table->string('party_type', 40)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('entry_type', 20)->index();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['party_type', 'party_id'], 'voucher_entries_party_idx');
        });

        Schema::create('medical_representatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->string('employee_code', 80)->nullable()->index();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('territory')->nullable()->index();
            $table->decimal('monthly_target', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('representative_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medical_representative_id')->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->date('visit_date')->index();
            $table->string('status', 40)->default('planned')->index();
            $table->decimal('order_value', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('target', 80)->index();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->json('detected_columns')->nullable();
            $table->json('mapping')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->string('status', 40)->default('previewed')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('import_staged_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_job_id')->index();
            $table->unsignedInteger('row_number')->index();
            $table->json('raw_data');
            $table->json('mapped_data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staged_rows');
        Schema::dropIfExists('import_jobs');
        Schema::dropIfExists('representative_visits');
        Schema::dropIfExists('medical_representatives');
        Schema::dropIfExists('voucher_entries');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('sales_invoice_items');
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('units');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('settings');
    }
};
