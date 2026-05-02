<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('name', 160);
            $table->string('code', 60)->nullable()->index();
            $table->string('district', 120)->nullable()->index();
            $table->string('province', 120)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['branch_id', 'name'], 'areas_branch_name_unique');
            $table->index(['tenant_id', 'branch_id', 'is_active'], 'areas_tenant_branch_active_idx');
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name', 160);
            $table->string('code', 60)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'name'], 'divisions_company_name_unique');
            $table->index(['tenant_id', 'company_id', 'is_active'], 'divisions_tenant_company_active_idx');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('area_id')->nullable()->index();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->unsignedBigInteger('reports_to_employee_id')->nullable()->index();
            $table->string('employee_code', 80)->nullable();
            $table->string('name', 180);
            $table->string('designation', 120)->nullable()->index();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->date('joined_on')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'employee_code'], 'employees_company_code_unique');
            $table->index(['tenant_id', 'branch_id', 'area_id'], 'employees_tenant_branch_area_idx');
            $table->index(['tenant_id', 'division_id', 'is_active'], 'employees_tenant_division_active_idx');
        });

        Schema::create('user_access_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('scope_type', 40)->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'scope_type', 'scope_id'], 'user_access_scopes_unique');
            $table->index(['tenant_id', 'company_id', 'scope_type'], 'user_access_scopes_context_idx');
        });

        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('area_id')->nullable()->index();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('target_type', 40)->index();
            $table->string('target_period', 40)->index();
            $table->string('target_level', 40)->index();
            $table->decimal('target_amount', 16, 2)->nullable();
            $table->decimal('target_quantity', 16, 3)->nullable();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('status', 40)->default('active')->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'target_level', 'start_date', 'end_date'], 'targets_tenant_level_period_idx');
            $table->index(['division_id', 'target_type', 'target_period'], 'targets_division_type_period_idx');
            $table->index(['employee_id', 'target_type', 'target_period'], 'targets_employee_type_period_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->index()->after('medical_representative_id');
            }

            if (! Schema::hasColumn('users', 'is_platform_admin')) {
                $table->boolean('is_platform_admin')->default(false)->index()->after('is_owner');
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'supplier_code')) {
                $table->string('supplier_code', 80)->nullable()->after('supplier_type_id');
                $table->index(['tenant_id', 'supplier_code'], 'suppliers_tenant_code_idx');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'customer_code')) {
                $table->string('customer_code', 80)->nullable()->after('party_type_id');
                $table->index(['tenant_id', 'customer_code'], 'customers_tenant_code_idx');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'division_id')) {
                $table->unsignedBigInteger('division_id')->nullable()->index()->after('manufacturer_id');
            }

            if (! Schema::hasColumn('products', 'hs_code')) {
                $table->string('hs_code', 80)->nullable()->index()->after('product_code');
            }

            if (! Schema::hasColumn('products', 'packaging_type')) {
                $table->string('packaging_type', 120)->nullable()->index()->after('manufacturer_name');
            }

            if (! Schema::hasColumn('products', 'case_movement')) {
                $table->string('case_movement', 120)->nullable()->index()->after('packaging_type');
            }

            $table->index(['tenant_id', 'division_id', 'is_active'], 'products_tenant_division_active_idx');
        });

        Schema::table('medical_representatives', function (Blueprint $table) {
            if (! Schema::hasColumn('medical_representatives', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->index()->after('branch_id');
            }

            if (! Schema::hasColumn('medical_representatives', 'area_id')) {
                $table->unsignedBigInteger('area_id')->nullable()->index()->after('employee_id');
            }

            if (! Schema::hasColumn('medical_representatives', 'division_id')) {
                $table->unsignedBigInteger('division_id')->nullable()->index()->after('area_id');
            }
        });

        Schema::table('representative_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('representative_visits', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->index()->after('medical_representative_id');
            }

            if (! Schema::hasColumn('representative_visits', 'visit_time')) {
                $table->time('visit_time')->nullable()->index()->after('visit_date');
            }

            if (! Schema::hasColumn('representative_visits', 'purpose')) {
                $table->string('purpose', 160)->nullable()->after('status');
            }

            if (! Schema::hasColumn('representative_visits', 'remarks')) {
                $table->text('remarks')->nullable()->after('notes');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'due_date')) {
                $table->date('due_date')->nullable()->index()->after('purchase_date');
            }

            if (! Schema::hasColumn('purchases', 'payment_mode_id')) {
                $table->unsignedBigInteger('payment_mode_id')->nullable()->index()->after('payment_status');
            }

            if (! Schema::hasColumn('purchases', 'payment_type')) {
                $table->string('payment_type', 40)->nullable()->index()->after('payment_mode_id');
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_invoices', 'due_date')) {
                $table->date('due_date')->nullable()->index()->after('invoice_date');
            }

            if (! Schema::hasColumn('sales_invoices', 'payment_mode_id')) {
                $table->unsignedBigInteger('payment_mode_id')->nullable()->index()->after('payment_status');
            }

            if (! Schema::hasColumn('sales_invoices', 'payment_type')) {
                $table->string('payment_type', 40)->nullable()->index()->after('payment_mode_id');
            }
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_returns', 'return_type')) {
                $table->string('return_type', 40)->default('regular')->index()->after('return_no');
            }
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_returns', 'return_type')) {
                $table->string('return_type', 40)->default('regular')->index()->after('return_no');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
        Schema::dropIfExists('user_access_scopes');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('areas');
    }
};
