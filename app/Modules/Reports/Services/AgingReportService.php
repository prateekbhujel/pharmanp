<?php

namespace App\Modules\Reports\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Database\SqlDialect;
use App\Core\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgingReportService
{
    public function __construct(private readonly SqlDialect $sql) {}

    public function customers(Request $request, int $perPage): array
    {
        $query = DB::table('sales_invoices')
            ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->leftJoin('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
            ->leftJoin('employees', 'employees.id', '=', 'medical_representatives.employee_id')
            ->whereNull('sales_invoices.deleted_at')
            ->whereRaw('(sales_invoices.grand_total - sales_invoices.paid_amount) > 0')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('sales_invoices.company_id', $companyId))
            ->when($request->filled('customer_id'), fn ($builder) => $builder->where('sales_invoices.customer_id', $request->integer('customer_id')))
            ->when($request->filled('employee_id'), fn ($builder) => $builder->where('employees.id', $request->integer('employee_id')))
            ->when($request->filled('area_id'), fn ($builder) => $builder->where('medical_representatives.area_id', $request->integer('area_id')))
            ->when($request->filled('division_id'), fn ($builder) => $builder->where('medical_representatives.division_id', $request->integer('division_id')));

        $days = $this->daysOverdueExpression('sales_invoices.due_date', 'sales_invoices.invoice_date');

        $query->when($request->filled('bucket'), function ($builder) use ($request, $days) {
            $bucket = $request->query('bucket');
            if ($bucket === '30') $builder->whereRaw("{$days} <= 30");
            elseif ($bucket === '45') $builder->whereRaw("{$days} > 30 AND {$days} <= 45");
            elseif ($bucket === '60') $builder->whereRaw("{$days} > 45 AND {$days} <= 60");
            elseif ($bucket === '90') $builder->whereRaw("{$days} > 60 AND {$days} <= 90");
            elseif ($bucket === '90_plus') $builder->whereRaw("{$days} > 90");
        });

        $page = (clone $query)
            ->orderByRaw('COALESCE(sales_invoices.due_date, sales_invoices.invoice_date) asc')
            ->orderBy('sales_invoices.id')
            ->selectRaw("
                sales_invoices.id,
                sales_invoices.invoice_no as reference_no,
                sales_invoices.invoice_date as bill_date,
                COALESCE(sales_invoices.due_date, sales_invoices.invoice_date) as due_date,
                customers.id as party_id,
                COALESCE(customers.name, 'Walk-in Customer') as party_name,
                COALESCE(medical_representatives.name, '-') as mr_name,
                sales_invoices.grand_total as total_amount,
                sales_invoices.paid_amount as paid_amount,
                (sales_invoices.grand_total - sales_invoices.paid_amount) as due_amount,
                {$days} as days_overdue
            ")
            ->paginate($perPage);

        return [
            'data' => collect($page->items())->map(fn ($row) => $this->agingRow($row))->values(),
            'meta' => ApiResponse::paginationMeta($page),
            'summary' => $this->summary($query, '(sales_invoices.grand_total - sales_invoices.paid_amount)', $days),
        ];
    }

    public function suppliers(Request $request, int $perPage): array
    {
        $query = DB::table('purchases')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereNull('purchases.deleted_at')
            ->whereRaw('(purchases.grand_total - purchases.paid_amount) > 0')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('purchases.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('purchases.company_id', $companyId))
            ->when($request->filled('supplier_id'), fn ($builder) => $builder->where('purchases.supplier_id', $request->integer('supplier_id')));

        $days = $this->daysOverdueExpression('purchases.due_date', 'purchases.purchase_date');

        $query->when($request->filled('bucket'), function ($builder) use ($request, $days) {
            $bucket = $request->query('bucket');
            if ($bucket === '30') $builder->whereRaw("{$days} <= 30");
            elseif ($bucket === '45') $builder->whereRaw("{$days} > 30 AND {$days} <= 45");
            elseif ($bucket === '60') $builder->whereRaw("{$days} > 45 AND {$days} <= 60");
            elseif ($bucket === '90') $builder->whereRaw("{$days} > 60 AND {$days} <= 90");
            elseif ($bucket === '90_plus') $builder->whereRaw("{$days} > 90");
        });

        $page = (clone $query)
            ->orderByRaw('COALESCE(purchases.due_date, purchases.purchase_date) asc')
            ->orderBy('purchases.id')
            ->selectRaw("
                purchases.id,
                purchases.purchase_no as reference_no,
                purchases.purchase_date as bill_date,
                COALESCE(purchases.due_date, purchases.purchase_date) as due_date,
                suppliers.id as party_id,
                suppliers.name as party_name,
                purchases.grand_total as total_amount,
                purchases.paid_amount as paid_amount,
                (purchases.grand_total - purchases.paid_amount) as due_amount,
                {$days} as days_overdue
            ")
            ->paginate($perPage);

        return [
            'data' => collect($page->items())->map(fn ($row) => $this->agingRow($row))->values(),
            'meta' => ApiResponse::paginationMeta($page),
            'summary' => $this->summary($query, '(purchases.grand_total - purchases.paid_amount)', $days),
        ];
    }

    private function agingRow(object $row): array
    {
        $days = max(0, (int) $row->days_overdue);

        return [
            ...get_object_vars($row),
            'days_overdue' => $days,
            'bucket' => match (true) {
                $days <= 30 => '30',
                $days <= 45 => '45',
                $days <= 60 => '60',
                $days <= 90 => '90',
                default => '90_plus',
            },
        ];
    }

    private function summary($query, string $amountExpression, string $daysExpression): array
    {
        $row = (clone $query)
            ->selectRaw("
                COUNT(*) as bills,
                SUM({$amountExpression}) as total_due,
                SUM(CASE WHEN {$daysExpression} <= 30 THEN {$amountExpression} ELSE 0 END) as bucket_30,
                SUM(CASE WHEN {$daysExpression} > 30 AND {$daysExpression} <= 45 THEN {$amountExpression} ELSE 0 END) as bucket_45,
                SUM(CASE WHEN {$daysExpression} > 45 AND {$daysExpression} <= 60 THEN {$amountExpression} ELSE 0 END) as bucket_60,
                SUM(CASE WHEN {$daysExpression} > 60 AND {$daysExpression} <= 90 THEN {$amountExpression} ELSE 0 END) as bucket_90,
                SUM(CASE WHEN {$daysExpression} > 90 THEN {$amountExpression} ELSE 0 END) as bucket_90_plus
            ")
            ->first();

        return [
            'bills' => (int) ($row->bills ?? 0),
            'total_due' => (float) ($row->total_due ?? 0),
            'bucket_30' => (float) ($row->bucket_30 ?? 0),
            'bucket_45' => (float) ($row->bucket_45 ?? 0),
            'bucket_60' => (float) ($row->bucket_60 ?? 0),
            'bucket_90' => (float) ($row->bucket_90 ?? 0),
            'bucket_90_plus' => (float) ($row->bucket_90_plus ?? 0),
        ];
    }

    private function daysOverdueExpression(string $dueColumn, string $fallbackColumn): string
    {
        return $this->sql->coalescedDaysUntilToday($dueColumn, $fallbackColumn);
    }
}
