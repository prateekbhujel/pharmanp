<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                if (! Schema::hasColumn('branches', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->index();
                }

                if (! Schema::hasColumn('branches', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }

                if (! Schema::hasColumn('branches', 'store_id')) {
                    $table->unsignedBigInteger('store_id')->nullable()->index();
                }

                if (! Schema::hasColumn('branches', 'deleted_at')) {
                    $table->softDeletes();
                }
            });

            $tenantId = DB::table('tenants')->value('id');
            $companyId = DB::table('companies')->value('id');
            $storeId = DB::table('stores')->value('id');

            DB::table('branches')
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'store_id' => $storeId,
                ]);
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->index();
            });

            $branchId = Schema::hasTable('branches')
                ? DB::table('branches')->orderByRaw("CASE WHEN type = 'hq' THEN 0 ELSE 1 END")->value('id')
                : null;

            if ($branchId) {
                DB::table('users')->whereNull('branch_id')->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                foreach (['tenant_id', 'company_id', 'store_id', 'deleted_at'] as $column) {
                    if (Schema::hasColumn('branches', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
