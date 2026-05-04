<?php

use App\Http\Middleware\WrapApiResponse;
use App\Modules\Accounting\Providers\AccountingServiceProvider;
use App\Modules\Analytics\Providers\AnalyticsServiceProvider;
use App\Modules\Core\Providers\CoreServiceProvider;
use App\Modules\ImportExport\Providers\ImportExportServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\MR\Providers\MrServiceProvider;
use App\Modules\Party\Providers\PartyServiceProvider;
use App\Modules\Purchase\Providers\PurchaseServiceProvider;
use App\Modules\Reports\Providers\ReportsServiceProvider;
use App\Modules\Sales\Providers\SalesServiceProvider;
use App\Modules\Setup\Providers\SetupServiceProvider;

return [
    'api_prefix' => 'api/v1',

    'api_middleware' => [
        'api',
        WrapApiResponse::class,
        'installed',
        'pharmanp.api',
    ],

    'modules' => [
        'core' => [
            'name' => 'Core',
            'domain' => 'Shared application services, dashboard, search and notifications',
            'namespace' => 'App\\Modules\\Core',
            'frontend' => 'resources/js/modules/dashboard',
            'provider' => CoreServiceProvider::class,
        ],
        'inventory' => [
            'name' => 'Inventory',
            'domain' => 'Products, manufacturers, units, batches, stock adjustments and stock movements',
            'namespace' => 'App\\Modules\\Inventory',
            'frontend' => 'resources/js/modules/inventory',
            'provider' => InventoryServiceProvider::class,
        ],
        'party' => [
            'name' => 'Party',
            'domain' => 'Customers, suppliers and party ledgers',
            'namespace' => 'App\\Modules\\Party',
            'frontend' => 'resources/js/modules/party',
            'provider' => PartyServiceProvider::class,
        ],
        'purchase' => [
            'name' => 'Purchase',
            'domain' => 'Purchase orders, entries, returns, supplier payments and inventory posting',
            'namespace' => 'App\\Modules\\Purchase',
            'frontend' => 'resources/js/modules/purchases',
            'provider' => PurchaseServiceProvider::class,
        ],
        'sales' => [
            'name' => 'Sales',
            'domain' => 'Sales invoices, POS flow, returns, payment updates and stock deduction',
            'namespace' => 'App\\Modules\\Sales',
            'frontend' => 'resources/js/modules/sales',
            'provider' => SalesServiceProvider::class,
        ],
        'accounting' => [
            'name' => 'Accounting',
            'domain' => 'Vouchers, payments, expenses, books, trial balance and reports',
            'namespace' => 'App\\Modules\\Accounting',
            'frontend' => 'resources/js/modules/accounting',
            'provider' => AccountingServiceProvider::class,
        ],
        'mr' => [
            'name' => 'Field Force',
            'domain' => 'Medical representatives, branches, visits, targets and performance',
            'namespace' => 'App\\Modules\\MR',
            'frontend' => 'resources/js/modules/mr',
            'provider' => MrServiceProvider::class,
        ],
        'import_export' => [
            'name' => 'Import Export',
            'domain' => 'CSV/XLSX imports, mapping, validation, rejected rows, exports and OCR handoff',
            'namespace' => 'App\\Modules\\ImportExport',
            'frontend' => 'resources/js/modules/imports',
            'provider' => ImportExportServiceProvider::class,
        ],
        'reports' => [
            'name' => 'Reports',
            'domain' => 'Server-side filtered operational and accounting reports',
            'namespace' => 'App\\Modules\\Reports',
            'frontend' => 'resources/js/modules/reports',
            'provider' => ReportsServiceProvider::class,
        ],
        'setup' => [
            'name' => 'Setup',
            'domain' => 'Installation, settings, branding, fiscal years, users, roles and dropdown masters',
            'namespace' => 'App\\Modules\\Setup',
            'frontend' => 'resources/js/modules/settings',
            'provider' => SetupServiceProvider::class,
        ],
        'analytics' => [
            'name' => 'Analytics',
            'domain' => 'Smart inventory signals and future local ML scoring',
            'namespace' => 'App\\Modules\\Analytics',
            'frontend' => null,
            'provider' => AnalyticsServiceProvider::class,
        ],
    ],
];
