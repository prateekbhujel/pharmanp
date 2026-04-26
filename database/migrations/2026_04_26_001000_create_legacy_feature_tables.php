<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropdown_options', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 80)->index();
            $table->string('name');
            $table->string('data')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();

            $table->unique(['alias', 'name'], 'dropdown_options_alias_name_unique');
        });

        Schema::create('party_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 80)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('supplier_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 80)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->date('expense_date')->index();
            $table->unsignedBigInteger('expense_category_id')->nullable()->index();
            $table->string('category')->nullable();
            $table->string('vendor_name')->nullable();
            $table->unsignedBigInteger('payment_mode_id')->nullable()->index();
            $table->string('payment_mode', 40)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['expense_date', 'expense_category_id'], 'expenses_date_category_idx');
        });

        Schema::create('payment_bill_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->index();
            $table->unsignedBigInteger('bill_id');
            $table->string('bill_type', 40);
            $table->decimal('allocated_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['bill_type', 'bill_id'], 'payment_allocations_bill_idx');
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('sales_invoice_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('return_no')->unique();
            $table->date('return_date')->index();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('reason')->nullable();
            $table->string('status', 40)->default('confirmed')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_return_id')->index();
            $table->unsignedBigInteger('sales_invoice_item_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });

        // Seed default dropdown options that legacy app expects.
        $this->seedDefaultDropdownOptions();
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('payment_bill_allocations');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('supplier_types');
        Schema::dropIfExists('party_types');
        Schema::dropIfExists('dropdown_options');
    }

    private function seedDefaultDropdownOptions(): void
    {
        $now = now();
        $rows = [
            ['alias' => 'payment_mode', 'name' => 'Cash', 'data' => 'cash', 'status' => 1],
            ['alias' => 'payment_mode', 'name' => 'Bank Transfer', 'data' => 'bank', 'status' => 1],
            ['alias' => 'payment_mode', 'name' => 'Cheque', 'data' => 'bank', 'status' => 1],
            ['alias' => 'expense_category', 'name' => 'Rent', 'data' => null, 'status' => 1],
            ['alias' => 'expense_category', 'name' => 'Utilities', 'data' => null, 'status' => 1],
            ['alias' => 'expense_category', 'name' => 'Salary', 'data' => null, 'status' => 1],
            ['alias' => 'expense_category', 'name' => 'Miscellaneous', 'data' => null, 'status' => 1],
            ['alias' => 'product_status', 'name' => 'Active', 'data' => null, 'status' => 1],
            ['alias' => 'product_status', 'name' => 'Discontinued', 'data' => null, 'status' => 1],
            ['alias' => 'formulation', 'name' => 'Tablet', 'data' => null, 'status' => 1],
            ['alias' => 'formulation', 'name' => 'Capsule', 'data' => null, 'status' => 1],
            ['alias' => 'formulation', 'name' => 'Syrup', 'data' => null, 'status' => 1],
            ['alias' => 'formulation', 'name' => 'Injection', 'data' => null, 'status' => 1],
            ['alias' => 'formulation', 'name' => 'Ointment', 'data' => null, 'status' => 1],
            ['alias' => 'sales_type', 'name' => 'Retail', 'data' => null, 'status' => 1],
            ['alias' => 'sales_type', 'name' => 'Wholesale', 'data' => null, 'status' => 1],
        ];

        foreach ($rows as $row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            \Illuminate\Support\Facades\DB::table('dropdown_options')->insert($row);
        }
    }
};
