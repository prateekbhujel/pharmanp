<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('representative_visits')) {
            Schema::table('representative_visits', function (Blueprint $table) {
                if (! Schema::hasColumn('representative_visits', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
                }

                if (! Schema::hasColumn('representative_visits', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('tenant_id')->index();
                }
            });

            DB::table('medical_representatives')
                ->whereNotNull('tenant_id')
                ->orderBy('id')
                ->select(['id', 'tenant_id', 'company_id'])
                ->chunkById(500, function ($representatives) {
                    foreach ($representatives as $representative) {
                        DB::table('representative_visits')
                            ->where('medical_representative_id', $representative->id)
                            ->whereNull('tenant_id')
                            ->update([
                                'tenant_id' => $representative->tenant_id,
                                'company_id' => $representative->company_id,
                            ]);
                    }
                });

            Schema::table('representative_visits', function (Blueprint $table) {
                $table->index(['tenant_id', 'company_id', 'visit_date'], 'rv_tenant_company_date_idx');
                $table->index(['tenant_id', 'medical_representative_id', 'visit_date'], 'rv_tenant_mr_date_idx');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'deleted_at', 'updated_at'], 'products_tenant_company_recent_idx');
            $table->index(['tenant_id', 'company_id', 'is_active', 'deleted_at'], 'products_tenant_company_active_idx');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'expires_at', 'quantity_available'], 'batches_tenant_company_expiry_idx');
            $table->index(['tenant_id', 'product_id', 'is_active', 'quantity_available'], 'batches_tenant_product_qty_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'movement_date'], 'stock_mov_tenant_company_date_idx');
            $table->index(['tenant_id', 'product_id', 'movement_date'], 'stock_mov_tenant_product_date_idx');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'invoice_date', 'id'], 'sales_tenant_company_date_idx');
            $table->index(['tenant_id', 'customer_id', 'invoice_date'], 'sales_tenant_customer_date_idx');
            $table->index(['tenant_id', 'medical_representative_id', 'invoice_date'], 'sales_tenant_mr_date_idx');
            $table->index(['tenant_id', 'payment_status', 'invoice_date'], 'sales_tenant_payment_date_idx');
        });

        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->index(['product_id', 'sales_invoice_id'], 'sales_items_product_invoice_idx');
            $table->index(['batch_id', 'sales_invoice_id'], 'sales_items_batch_invoice_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'purchase_date', 'id'], 'purchases_tenant_company_date_idx');
            $table->index(['tenant_id', 'supplier_id', 'purchase_date'], 'purchases_tenant_supplier_date_idx');
            $table->index(['tenant_id', 'payment_status', 'purchase_date'], 'purchases_tenant_payment_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'payment_date'], 'payments_tenant_company_date_idx');
            $table->index(['tenant_id', 'party_type', 'party_id', 'payment_date'], 'payments_tenant_party_date_idx');
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'transaction_date'], 'acct_tenant_company_date_idx');
            $table->index(['tenant_id', 'account_type', 'transaction_date'], 'acct_tenant_account_date_idx');
            $table->index(['tenant_id', 'party_type', 'party_id', 'transaction_date'], 'acct_tenant_party_date_idx');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'deleted_at', 'updated_at'], 'suppliers_tenant_company_recent_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'deleted_at', 'updated_at'], 'customers_tenant_company_recent_idx');
        });

        Schema::table('medical_representatives', function (Blueprint $table) {
            $table->index(['tenant_id', 'company_id', 'is_active', 'deleted_at'], 'mrs_tenant_company_active_idx');
            $table->index(['tenant_id', 'branch_id', 'is_active'], 'mrs_tenant_branch_active_idx');
        });
    }

    public function down(): void
    {
        $drops = [
            'products' => ['products_tenant_company_recent_idx', 'products_tenant_company_active_idx'],
            'batches' => ['batches_tenant_company_expiry_idx', 'batches_tenant_product_qty_idx'],
            'stock_movements' => ['stock_mov_tenant_company_date_idx', 'stock_mov_tenant_product_date_idx'],
            'sales_invoices' => ['sales_tenant_company_date_idx', 'sales_tenant_customer_date_idx', 'sales_tenant_mr_date_idx', 'sales_tenant_payment_date_idx'],
            'sales_invoice_items' => ['sales_items_product_invoice_idx', 'sales_items_batch_invoice_idx'],
            'purchases' => ['purchases_tenant_company_date_idx', 'purchases_tenant_supplier_date_idx', 'purchases_tenant_payment_date_idx'],
            'payments' => ['payments_tenant_company_date_idx', 'payments_tenant_party_date_idx'],
            'account_transactions' => ['acct_tenant_company_date_idx', 'acct_tenant_account_date_idx', 'acct_tenant_party_date_idx'],
            'suppliers' => ['suppliers_tenant_company_recent_idx'],
            'customers' => ['customers_tenant_company_recent_idx'],
            'medical_representatives' => ['mrs_tenant_company_active_idx', 'mrs_tenant_branch_active_idx'],
            'representative_visits' => ['rv_tenant_company_date_idx', 'rv_tenant_mr_date_idx'],
        ];

        foreach ($drops as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $index) {
                    $blueprint->dropIndex($index);
                }
            });
        }

        if (Schema::hasTable('representative_visits')) {
            Schema::table('representative_visits', function (Blueprint $table) {
                foreach (['tenant_id', 'company_id'] as $column) {
                    if (Schema::hasColumn('representative_visits', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
