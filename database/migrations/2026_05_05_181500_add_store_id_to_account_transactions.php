<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('account_transactions', 'store_id')) {
            Schema::table('account_transactions', function (Blueprint $table): void {
                $table->unsignedBigInteger('store_id')->nullable()->index()->after('company_id');
            });
        }

        if (! Schema::hasIndex('account_transactions', 'acct_tenant_company_store_date_idx')) {
            Schema::table('account_transactions', function (Blueprint $table): void {
                $table->index(['tenant_id', 'company_id', 'store_id', 'transaction_date'], 'acct_tenant_company_store_date_idx');
            });
        }

        $this->backfillStoreIds();
    }

    public function down(): void
    {
        if (Schema::hasColumn('account_transactions', 'store_id')) {
            if (Schema::hasIndex('account_transactions', 'acct_tenant_company_store_date_idx')) {
                Schema::table('account_transactions', function (Blueprint $table): void {
                    $table->dropIndex('acct_tenant_company_store_date_idx');
                });
            }

            Schema::table('account_transactions', function (Blueprint $table): void {
                $table->dropColumn('store_id');
            });
        }
    }

    private function backfillStoreIds(): void
    {
        $sourceTables = [
            'sales_invoice' => 'sales_invoices',
            'sales_return' => 'sales_returns',
            'purchase' => 'purchases',
            'purchase_return' => 'purchase_returns',
            'payment' => 'payments',
        ];

        foreach ($sourceTables as $sourceType => $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'store_id')) {
                continue;
            }

            DB::table('account_transactions')
                ->where('source_type', $sourceType)
                ->whereNull('store_id')
                ->orderBy('id')
                ->select(['id', 'source_id'])
                ->chunkById(500, function ($rows) use ($table): void {
                    $sourceIds = $rows->pluck('source_id')->filter()->unique()->values();

                    if ($sourceIds->isEmpty()) {
                        return;
                    }

                    $stores = DB::table($table)
                        ->whereIn('id', $sourceIds)
                        ->pluck('store_id', 'id');

                    foreach ($rows as $row) {
                        $storeId = $stores->get($row->source_id);

                        if ($storeId !== null) {
                            DB::table('account_transactions')
                                ->where('id', $row->id)
                                ->update(['store_id' => $storeId]);
                        }
                    }
                });
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'store_id')) {
            return;
        }

        DB::table('account_transactions')
            ->whereNull('store_id')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->select(['id', 'created_by'])
            ->chunkById(500, function ($rows): void {
                $userIds = $rows->pluck('created_by')->filter()->unique()->values();

                if ($userIds->isEmpty()) {
                    return;
                }

                $stores = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('store_id', 'id');

                foreach ($rows as $row) {
                    $storeId = $stores->get($row->created_by);

                    if ($storeId !== null) {
                        DB::table('account_transactions')
                            ->where('id', $row->id)
                            ->update(['store_id' => $storeId]);
                    }
                }
            });
    }
};
