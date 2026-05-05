<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'sales_invoices',
            'sales_returns',
            'purchases',
            'purchase_returns',
            'payments',
            'vouchers',
            'account_transactions'
        ];

        foreach ($tables as $table) {
            if (!Schema::hasColumn($table, 'fiscal_year_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'sales_invoices',
            'sales_returns',
            'purchases',
            'purchase_returns',
            'payments',
            'vouchers',
            'account_transactions'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['fiscal_year_id']);
                $t->dropColumn('fiscal_year_id');
            });
        }
    }
};
