<?php

return [
    'api_prefix' => 'api/v1',

    'modules' => [
        'core' => [
            'name' => 'Core',
            'domain' => 'Shared application services, dashboard, search and notifications',
            'namespace' => 'App\\Modules\\Core',
            'frontend' => 'resources/js/modules/dashboard',
            'provider' => null,
        ],
        'inventory' => [
            'name' => 'Inventory',
            'domain' => 'Products, manufacturers, units, batches, stock adjustments and stock movements',
            'namespace' => 'App\\Modules\\Inventory',
            'frontend' => 'resources/js/modules/inventory',
            'provider' => App\Modules\Inventory\InventoryServiceProvider::class,
        ],
        'party' => [
            'name' => 'Party',
            'domain' => 'Customers, suppliers and party ledgers',
            'namespace' => 'App\\Modules\\Party',
            'frontend' => 'resources/js/modules/party',
            'provider' => App\Modules\Party\PartyServiceProvider::class,
        ],
        'purchase' => [
            'name' => 'Purchase',
            'domain' => 'Purchase orders, entries, returns, supplier payments and inventory posting',
            'namespace' => 'App\\Modules\\Purchase',
            'frontend' => 'resources/js/modules/purchases',
            'provider' => App\Modules\Purchase\PurchaseServiceProvider::class,
        ],
        'sales' => [
            'name' => 'Sales',
            'domain' => 'Sales invoices, POS flow, returns, payment updates and stock deduction',
            'namespace' => 'App\\Modules\\Sales',
            'frontend' => 'resources/js/modules/sales',
            'provider' => App\Modules\Sales\SalesServiceProvider::class,
        ],
        'accounting' => [
            'name' => 'Accounting',
            'domain' => 'Vouchers, payments, expenses, books, trial balance and reports',
            'namespace' => 'App\\Modules\\Accounting',
            'frontend' => 'resources/js/modules/accounting',
            'provider' => App\Modules\Accounting\AccountingServiceProvider::class,
        ],
        'mr' => [
            'name' => 'Field Force',
            'domain' => 'Medical representatives, branches, visits, targets and performance',
            'namespace' => 'App\\Modules\\MR',
            'frontend' => 'resources/js/modules/mr',
            'provider' => App\Modules\MR\MrServiceProvider::class,
        ],
        'import_export' => [
            'name' => 'Import Export',
            'domain' => 'CSV/XLSX imports, mapping, validation, rejected rows, exports and OCR handoff',
            'namespace' => 'App\\Modules\\ImportExport',
            'frontend' => 'resources/js/modules/imports',
            'provider' => App\Modules\ImportExport\ImportExportServiceProvider::class,
        ],
        'reports' => [
            'name' => 'Reports',
            'domain' => 'Server-side filtered operational and accounting reports',
            'namespace' => 'App\\Modules\\Reports',
            'frontend' => 'resources/js/modules/reports',
            'provider' => App\Modules\Reports\ReportsServiceProvider::class,
        ],
        'setup' => [
            'name' => 'Setup',
            'domain' => 'Installation, settings, branding, fiscal years, users, roles and dropdown masters',
            'namespace' => 'App\\Modules\\Setup',
            'frontend' => 'resources/js/modules/settings',
            'provider' => App\Modules\Setup\SetupServiceProvider::class,
        ],
        'analytics' => [
            'name' => 'Analytics',
            'domain' => 'Smart inventory signals and future local ML scoring',
            'namespace' => 'App\\Modules\\Analytics',
            'frontend' => null,
            'provider' => App\Modules\Analytics\AnalyticsServiceProvider::class,
        ],
    ],
];
