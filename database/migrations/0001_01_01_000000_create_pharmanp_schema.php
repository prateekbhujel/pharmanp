<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLaravelRuntimeTables();
        $this->createAuthAndAccessTables();
        $this->createTenantAndSetupTables();
        $this->createInventoryTables();
        $this->createPartyTables();
        $this->createPurchaseTables();
        $this->createSalesTables();
        $this->createAccountingTables();
        $this->createFieldForceTables();
        $this->createImportTables();
    }

    public function down(): void
    {
        foreach ([
            'import_staged_rows',
            'import_jobs',
            'representative_visits',
            'medical_representatives',
            'account_transactions',
            'expenses',
            'payment_bill_allocations',
            'payments',
            'voucher_entries',
            'vouchers',
            'sales_return_items',
            'sales_returns',
            'sales_invoice_items',
            'sales_invoices',
            'purchase_return_items',
            'purchase_returns',
            'purchase_items',
            'purchase_order_items',
            'purchase_orders',
            'stock_adjustments',
            'stock_movements',
            'batches',
            'customers',
            'suppliers',
            'products',
            'units',
            'stores',
            'companies',
            'targets',
            'user_access_scopes',
            'employees',
            'divisions',
            'areas',
            'branches',
            'onboarding_states',
            'tenant_feature_flags',
            'feature_catalog_items',
            'fiscal_years',
            'dropdown_options',
            'supplier_types',
            'party_types',
            'document_sequences',
            'settings',
            'tenant_domains',
            'tenants',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'roles',
            'permissions',
            'jwt_revocations',
            'failed_jobs',
            'job_batches',
            'jobs',
            'cache_locks',
            'cache',
            'sessions',
            'password_reset_tokens',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createLaravelRuntimeTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->unsignedBigInteger('medical_representative_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 40)->nullable()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_owner')->default(false)->index();
            $table->boolean('is_platform_admin')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('jwt_revocations', function (Blueprint $table) {
            $table->id();
            $table->string('jti', 80)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    private function createAuthAndAccessTables(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name'], 'permissions_name_guard_unique');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name'], 'roles_name_guard_unique');
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_primary');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_idx');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_idx');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_primary');
            $table->index('role_id', 'role_has_permissions_role_idx');
        });

    }

    private function createTenantAndSetupTables(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 120)->unique();
            $table->string('status', 40)->default('active')->index();
            $table->string('plan_code', 80)->default('starter')->index();
            $table->date('trial_ends_on')->nullable()->index();
            $table->timestamp('suspended_at')->nullable()->index();
            $table->text('suspension_reason')->nullable();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->boolean('is_private')->default(false)->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 120);
            $table->string('type', 80);
            $table->string('date_part', 20)->default('');
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(['scope_key', 'type', 'date_part'], 'document_sequences_scope_type_date_unique');
            $table->index(['tenant_id', 'company_id', 'type'], 'document_sequences_tenant_company_type_idx');
        });

        Schema::create('dropdown_options', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 80)->index();
            $table->string('name');
            $table->string('data')->nullable();
            $table->json('meta')->nullable();
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

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->string('name', 80);
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->string('status', 40)->default('open')->index();
            $table->timestamp('closed_at')->nullable()->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'fiscal_years_company_name_unique');
            $table->index(['company_id', 'starts_on', 'ends_on'], 'fiscal_years_company_period_idx');
        });

        Schema::create('feature_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80)->index();
            $table->string('code', 120)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 40)->default('planned')->index();
            $table->boolean('is_billable')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('tenant_feature_flags', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->string('feature_code', 120)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'company_id', 'feature_code'], 'tenant_feature_unique');
        });

        Schema::create('onboarding_states', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('user_id')->index();
            $table->string('step_code', 120)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['user_id', 'step_code'], 'onboarding_user_step_unique');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->string('name');
            $table->string('code', 40)->nullable()->index();
            $table->string('type', 20)->default('branch')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('branch_id')->index();
            $table->string('name', 160);
            $table->string('code', 60)->nullable()->index();
            $table->string('district', 120)->nullable()->index();
            $table->string('province', 120)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['branch_id', 'name'], 'areas_branch_name_unique');
            $table->index(['tenant_id', 'branch_id', 'is_active'], 'areas_tenant_branch_active_idx');
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->string('name', 160);
            $table->string('code', 60)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'divisions_company_name_unique');
            $table->index(['tenant_id', 'company_id', 'is_active'], 'divisions_tenant_company_active_idx');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
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
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_id', 'employee_code'], 'employees_company_code_unique');
            $table->index(['tenant_id', 'branch_id', 'area_id'], 'employees_tenant_branch_area_idx');
            $table->index(['tenant_id', 'division_id', 'is_active'], 'employees_tenant_division_active_idx');
        });

        Schema::create('user_access_scopes', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('user_id')->index();
            $table->string('scope_type', 40)->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->timestamps();
            $table->unique(['user_id', 'scope_type', 'scope_id'], 'user_access_scopes_unique');
            $table->index(['tenant_id', 'company_id', 'scope_type'], 'user_access_scopes_context_idx');
        });

        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
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
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'target_level', 'start_date', 'end_date'], 'targets_tenant_level_period_idx');
            $table->index(['division_id', 'target_type', 'target_period'], 'targets_division_type_period_idx');
            $table->index(['employee_id', 'target_type', 'target_period'], 'targets_employee_type_period_idx');
        });
    }

    private function createInventoryTables(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name')->index('companies_name_idx');
            $table->string('legal_name')->nullable();
            $table->string('pan_number', 60)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('country', 80)->default('Nepal');
            $table->string('company_type', 40)->default('pharmacy')->index();
            $table->decimal('default_cc_rate', 8, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->string('name');
            $table->string('code', 60)->nullable()->index();
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->string('name');
            $table->string('code', 30)->nullable()->index();
            $table->string('type', 30)->default('both')->index();
            $table->decimal('factor', 12, 4)->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'units_company_name_unique');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->unsignedBigInteger('manufacturer_id')->nullable()->index();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->unsignedBigInteger('unit_id')->nullable()->index();
            $table->string('sku', 80)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('product_code', 80)->nullable()->index();
            $table->string('hs_code', 80)->nullable()->index();
            $table->string('name');
            $table->string('generic_name')->nullable()->index();
            $table->string('composition')->nullable();
            $table->string('group_name', 120)->nullable()->index();
            $table->string('manufacturer_name', 180)->nullable()->index();
            $table->string('packaging_type', 120)->nullable()->index();
            $table->string('strength', 80)->nullable();
            $table->decimal('conversion', 12, 3)->default(1);
            $table->string('rack_location', 80)->nullable();
            $table->decimal('previous_price', 14, 2)->default(0);
            $table->decimal('mrp', 14, 2)->default(0);
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('cc_rate', 8, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->unsignedInteger('reorder_level')->default(10);
            $table->unsignedInteger('reorder_quantity')->default(0);
            $table->boolean('is_batch_tracked')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->text('keywords')->nullable();
            $table->mediumText('description')->nullable();
            $table->string('image_path')->nullable();
            $this->auditColumns($table, true);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_id', 'sku'], 'products_company_sku_unique');
            $table->unique(['company_id', 'barcode'], 'products_company_barcode_unique');
            $table->unique(['company_id', 'product_code'], 'products_company_code_unique');
            $table->index(['name', 'generic_name'], 'products_name_generic_idx');
            $table->index(['is_active', 'deleted_at'], 'products_active_deleted_idx');
            $table->index(['tenant_id', 'company_id', 'deleted_at', 'updated_at'], 'products_tenant_company_recent_idx');
            $table->index(['tenant_id', 'company_id', 'is_active', 'deleted_at'], 'products_tenant_company_active_idx');
            $table->index(['tenant_id', 'division_id', 'is_active'], 'products_tenant_division_active_idx');
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedBigInteger('purchase_id')->nullable()->index();
            $table->string('batch_no', 120);
            $table->string('barcode', 120)->nullable()->index();
            $table->string('storage_location', 120)->nullable()->index();
            $table->date('manufactured_at')->nullable()->index();
            $table->date('expires_at')->nullable()->index();
            $table->decimal('quantity_received', 14, 3)->default(0);
            $table->decimal('quantity_available', 14, 3)->default(0);
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('mrp', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_id', 'product_id', 'batch_no'], 'batches_company_product_batch_unique');
            $table->index(['product_id', 'expires_at'], 'batches_product_expiry_idx');
            $table->index(['is_active', 'quantity_available'], 'batches_active_qty_idx');
            $table->index(['tenant_id', 'company_id', 'expires_at', 'quantity_available'], 'batches_tenant_company_expiry_idx');
            $table->index(['tenant_id', 'product_id', 'is_active', 'quantity_available'], 'batches_tenant_product_qty_idx');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
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
            $table->index(['tenant_id', 'company_id', 'movement_date'], 'stock_mov_tenant_company_date_idx');
            $table->index(['tenant_id', 'product_id', 'movement_date'], 'stock_mov_tenant_product_date_idx');
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->date('adjustment_date')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('batch_id')->index();
            $table->string('adjustment_type', 40)->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable()->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['product_id', 'adjustment_date'], 'stock_adjustments_product_date_idx');
            $table->index(['batch_id', 'adjustment_date'], 'stock_adjustments_batch_date_idx');
        });
    }

    private function createPartyTables(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('supplier_type_id')->nullable()->index();
            $table->string('supplier_code', 80)->nullable();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('pan_number', 60)->nullable()->index();
            $table->string('address')->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['tenant_id', 'supplier_code'], 'suppliers_tenant_code_unique');
            $table->index(['tenant_id', 'company_id', 'deleted_at', 'updated_at'], 'suppliers_tenant_company_recent_idx');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('party_type_id')->nullable()->index();
            $table->string('customer_code', 80)->nullable();
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
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_code'], 'customers_tenant_code_idx');
            $table->index(['tenant_id', 'company_id', 'deleted_at', 'updated_at'], 'customers_tenant_company_recent_idx');
        });
    }

    private function createPurchaseTables(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('order_no')->unique();
            $table->date('order_date')->index();
            $table->date('expected_date')->nullable()->index();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedBigInteger('received_purchase_id')->nullable()->index('purchase_orders_received_purchase_idx');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['supplier_id', 'order_date'], 'purchase_orders_supplier_date_idx');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
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

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('purchase_no')->unique();
            $table->string('supplier_invoice_no')->nullable()->index();
            $table->date('purchase_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('status', 40)->default('received')->index();
            $table->string('payment_status', 40)->default('unpaid')->index();
            $table->unsignedBigInteger('payment_mode_id')->nullable()->index();
            $table->string('payment_type', 40)->nullable()->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'company_id', 'purchase_date', 'id'], 'purchases_tenant_company_date_idx');
            $table->index(['tenant_id', 'supplier_id', 'purchase_date'], 'purchases_tenant_supplier_date_idx');
            $table->index(['tenant_id', 'payment_status', 'purchase_date'], 'purchases_tenant_payment_date_idx');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
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
            $table->decimal('cc_rate', 8, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('free_goods_value', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['product_id', 'expires_at'], 'purchase_items_product_expiry_idx');
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->unsignedBigInteger('purchase_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('return_no')->unique();
            $table->string('return_type', 40)->default('regular')->index();
            $table->date('return_date')->index();
            $table->string('status', 40)->default('posted')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable()->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['supplier_id', 'return_date'], 'purchase_returns_supplier_date_idx');
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
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

    private function createSalesTables(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('medical_representative_id')->nullable()->index();
            $table->string('invoice_no')->unique();
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->string('sale_type', 40)->default('retail')->index();
            $table->string('status', 40)->default('confirmed')->index();
            $table->string('payment_status', 40)->default('unpaid')->index();
            $table->unsignedBigInteger('payment_mode_id')->nullable()->index();
            $table->string('payment_type', 40)->nullable()->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'company_id', 'invoice_date', 'id'], 'sales_tenant_company_date_idx');
            $table->index(['tenant_id', 'customer_id', 'invoice_date'], 'sales_tenant_customer_date_idx');
            $table->index(['tenant_id', 'medical_representative_id', 'invoice_date'], 'sales_tenant_mr_date_idx');
            $table->index(['tenant_id', 'payment_status', 'invoice_date'], 'sales_tenant_payment_date_idx');
        });

        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('sales_invoice_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('free_quantity', 14, 3)->default(0);
            $table->decimal('mrp', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('cc_rate', 8, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('free_goods_value', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['product_id', 'sales_invoice_id'], 'sales_items_product_invoice_idx');
            $table->index(['batch_id', 'sales_invoice_id'], 'sales_items_batch_invoice_idx');
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->unsignedBigInteger('sales_invoice_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('return_no')->unique();
            $table->string('return_type', 40)->default('regular')->index();
            $table->date('return_date')->index();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('reason')->nullable();
            $table->string('status', 40)->default('confirmed')->index();
            $table->text('notes')->nullable();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('sales_return_id')->index();
            $table->unsignedBigInteger('sales_invoice_item_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    private function createAccountingTables(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->string('voucher_no')->unique();
            $table->date('voucher_date')->index();
            $table->string('voucher_type', 40)->index();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
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

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
            $table->string('payment_no')->unique();
            $table->date('payment_date')->index();
            $table->string('direction', 20)->index();
            $table->string('party_type', 40)->nullable()->index();
            $table->unsignedBigInteger('party_id')->nullable()->index();
            $table->unsignedBigInteger('payment_mode_id')->nullable()->index();
            $table->string('payment_mode', 60)->default('cash')->index();
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('reference_no')->nullable()->index();
            $table->text('notes')->nullable();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['party_type', 'party_id', 'payment_date'], 'payments_party_date_idx');
            $table->index(['tenant_id', 'company_id', 'payment_date'], 'payments_tenant_company_date_idx');
            $table->index(['tenant_id', 'party_type', 'party_id', 'payment_date'], 'payments_tenant_party_date_idx');
        });

        Schema::create('payment_bill_allocations', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('payment_id')->index();
            $table->unsignedBigInteger('bill_id');
            $table->string('bill_type', 40);
            $table->decimal('allocated_amount', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['bill_type', 'bill_id'], 'payment_allocations_bill_idx');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
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

        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
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
            $table->index(['tenant_id', 'company_id', 'transaction_date'], 'acct_tenant_company_date_idx');
            $table->index(['tenant_id', 'account_type', 'transaction_date'], 'acct_tenant_account_date_idx');
            $table->index(['tenant_id', 'party_type', 'party_id', 'transaction_date'], 'acct_tenant_party_date_idx');
        });
    }

    private function createFieldForceTables(): void
    {
        Schema::create('medical_representatives', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('area_id')->nullable()->index();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->string('name');
            $table->string('employee_code', 80)->nullable()->index();
            $table->string('phone', 40)->nullable()->index();
            $table->string('email')->nullable();
            $table->decimal('monthly_target', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['tenant_id', 'employee_code'], 'medical_representatives_tenant_code_unique');
            $table->index(['tenant_id', 'company_id', 'is_active', 'deleted_at'], 'mrs_tenant_company_active_idx');
            $table->index(['tenant_id', 'branch_id', 'is_active'], 'mrs_tenant_branch_active_idx');
        });

        Schema::create('representative_visits', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table);
            $table->unsignedBigInteger('medical_representative_id')->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->date('visit_date')->index();
            $table->time('visit_time')->nullable()->index();
            $table->string('status', 40)->default('planned')->index();
            $table->decimal('order_value', 14, 2)->default(0);
            $table->string('purpose', 160)->nullable();
            $table->text('notes')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_name')->nullable();
            $this->auditColumns($table);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'company_id', 'visit_date'], 'rv_tenant_company_date_idx');
            $table->index(['tenant_id', 'medical_representative_id', 'visit_date'], 'rv_tenant_mr_date_idx');
        });
    }

    private function createImportTables(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $this->tenantColumns($table, true);
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
            $this->tenantColumns($table);
            $table->unsignedBigInteger('import_job_id')->index();
            $table->unsignedInteger('row_number')->index();
            $table->json('raw_data');
            $table->json('mapped_data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->timestamps();
        });
    }

    private function tenantColumns(Blueprint $table, bool $withStore = false): void
    {
        $table->unsignedBigInteger('tenant_id')->nullable()->index();
        $table->unsignedBigInteger('company_id')->nullable()->index();

        if ($withStore) {
            $table->unsignedBigInteger('store_id')->nullable()->index();
        }
    }

    private function auditColumns(Blueprint $table, bool $withDeletedBy = false): void
    {
        $table->unsignedBigInteger('created_by')->nullable()->index();
        $table->unsignedBigInteger('updated_by')->nullable()->index();

        if ($withDeletedBy) {
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
        }
    }
};
