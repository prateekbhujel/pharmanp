<?php

namespace App\Modules\Reports\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceReportService
{
    public function mrVsProduct(Request $request, int $perPage): array
    {
        $query = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
            ->whereNull('sales_invoices.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('sales_invoices.company_id', $companyId))
            ->when($request->filled('medical_representative_id'), fn ($builder) => $builder->where('sales_invoices.medical_representative_id', $request->integer('medical_representative_id')))
            ->when($request->filled('product_id'), fn ($builder) => $builder->where('sales_invoice_items.product_id', $request->integer('product_id')));

        $this->applyDateRange($query, 'sales_invoices.invoice_date', $request);

        return $this->paged($query
            ->groupBy('medical_representatives.id', 'medical_representatives.name', 'products.id', 'products.product_code', 'products.name')
            ->orderByDesc('sales_value')
            ->selectRaw('medical_representatives.id as mr_id, COALESCE(medical_representatives.name, "-") as mr_name, products.id as product_id, products.product_code, products.name as product, SUM(sales_invoice_items.quantity) as quantity, SUM(sales_invoice_items.line_total) as sales_value'), $perPage);
    }

    public function mrVsDivision(Request $request, int $perPage): array
    {
        $query = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('divisions', 'divisions.id', '=', 'products.division_id')
            ->leftJoin('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
            ->whereNull('sales_invoices.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('sales_invoices.company_id', $companyId))
            ->when($request->filled('division_id'), fn ($builder) => $builder->where('products.division_id', $request->integer('division_id')));

        $this->applyDateRange($query, 'sales_invoices.invoice_date', $request);

        return $this->paged($query
            ->groupBy('medical_representatives.id', 'medical_representatives.name', 'divisions.id', 'divisions.name')
            ->orderByDesc('sales_value')
            ->selectRaw('medical_representatives.id as mr_id, COALESCE(medical_representatives.name, "-") as mr_name, divisions.id as division_id, COALESCE(divisions.name, "Unassigned") as division, SUM(sales_invoice_items.quantity) as quantity, SUM(sales_invoice_items.line_total) as sales_value'), $perPage);
    }

    public function mrVsSales(Request $request, int $perPage): array
    {
        $query = DB::table('sales_invoices')
            ->leftJoin('medical_representatives', 'medical_representatives.id', '=', 'sales_invoices.medical_representative_id')
            ->leftJoin('areas', 'areas.id', '=', 'medical_representatives.area_id')
            ->whereNull('sales_invoices.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('sales_invoices.company_id', $companyId))
            ->when($request->filled('area_id'), fn ($builder) => $builder->where('medical_representatives.area_id', $request->integer('area_id')));

        $this->applyDateRange($query, 'sales_invoices.invoice_date', $request);

        return $this->paged($query
            ->groupBy('medical_representatives.id', 'medical_representatives.name', 'areas.name')
            ->orderByDesc('sales_value')
            ->selectRaw('medical_representatives.id as mr_id, COALESCE(medical_representatives.name, "-") as mr_name, COALESCE(areas.name, "-") as area, COUNT(sales_invoices.id) as bill_count, SUM(sales_invoices.grand_total) as sales_value, SUM(sales_invoices.paid_amount) as collected_value'), $perPage);
    }

    public function companyVsCustomer(Request $request, int $perPage): array
    {
        $query = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->join('products', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('companies', 'companies.id', '=', 'products.manufacturer_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->whereNull('sales_invoices.deleted_at')
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('sales_invoices.tenant_id', $tenantId))
            ->when($request->user()?->company_id, fn ($builder, $companyId) => $builder->where('sales_invoices.company_id', $companyId))
            ->when($request->filled('manufacturer_id'), fn ($builder) => $builder->where('products.manufacturer_id', $request->integer('manufacturer_id')))
            ->when($request->filled('customer_id'), fn ($builder) => $builder->where('sales_invoices.customer_id', $request->integer('customer_id')));

        $this->applyDateRange($query, 'sales_invoices.invoice_date', $request);

        return $this->paged($query
            ->groupBy('companies.id', 'companies.name', 'customers.id', 'customers.name')
            ->orderByDesc('sales_value')
            ->selectRaw('companies.id as company_id, COALESCE(companies.name, "Unassigned") as company, customers.id as customer_id, COALESCE(customers.name, "Walk-in Customer") as customer, SUM(sales_invoice_items.quantity) as quantity, SUM(sales_invoice_items.line_total) as sales_value'), $perPage);
    }

    private function applyDateRange($query, string $column, Request $request): void
    {
        if ($request->filled('from')) {
            $query->whereDate($column, '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate($column, '<=', $request->query('to'));
        }
    }

    private function paged($query, int $perPage): array
    {
        $page = $query->paginate($perPage);

        return [
            'data' => $page->items(),
            'meta' => ApiResponse::paginationMeta($page),
            'summary' => [
                'rows' => $page->total(),
                'sales_value' => (float) collect($page->items())->sum('sales_value'),
            ],
        ];
    }
}
