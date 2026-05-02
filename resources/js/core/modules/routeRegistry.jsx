import React from 'react';
import { appUrl } from '../utils/url';

const DashboardPage = React.lazy(() => import('../../modules/dashboard/DashboardPage').then((module) => ({ default: module.DashboardPage })));
const ProductsPage = React.lazy(() => import('../../modules/inventory/ProductsPage').then((module) => ({ default: module.ProductsPage })));
const SalesPage = React.lazy(() => import('../../modules/sales/SalesPage').then((module) => ({ default: module.SalesPage })));
const ImportWizardPage = React.lazy(() => import('../../modules/imports/ImportWizardPage').then((module) => ({ default: module.ImportWizardPage })));
const OcrImportPage = React.lazy(() => import('../../modules/imports/OcrImportPage').then((module) => ({ default: module.OcrImportPage })));
const MrTrackingPage = React.lazy(() => import('../../modules/mr/MrTrackingPage').then((module) => ({ default: module.MrTrackingPage })));
const SettingsPage = React.lazy(() => import('../../modules/settings/SettingsPage').then((module) => ({ default: module.SettingsPage })));
const PurchasesPage = React.lazy(() => import('../../modules/purchases/PurchasesPage').then((module) => ({ default: module.PurchasesPage })));
const AccountingPage = React.lazy(() => import('../../modules/accounting/AccountingPage').then((module) => ({ default: module.AccountingPage })));
const PartiesPage = React.lazy(() => import('../../modules/party/PartiesPage').then((module) => ({ default: module.PartiesPage })));
const ReportsPage = React.lazy(() => import('../../modules/reports/ReportsPage').then((module) => ({ default: module.ReportsPage })));
const UsersPage = React.lazy(() => import('../../modules/settings/UsersPage').then((module) => ({ default: module.UsersPage })));
const RolesPage = React.lazy(() => import('../../modules/settings/RolesPage').then((module) => ({ default: module.RolesPage })));
const DataLookupPage = React.lazy(() => import('../../modules/settings/DataLookupPage').then((module) => ({ default: module.DataLookupPage })));

export const frontendModules = [
    { key: 'dashboard', label: 'Dashboard', routes: [appUrl('/app')], component: DashboardPage },
    { key: 'inventory', label: 'Inventory', root: appUrl('/app/inventory'), component: ProductsPage },
    { key: 'purchase', label: 'Purchase', root: appUrl('/app/purchases'), component: PurchasesPage },
    { key: 'sales', label: 'Sales', root: appUrl('/app/sales'), component: SalesPage },
    { key: 'party', label: 'Party Management', root: appUrl('/app/party'), component: PartiesPage },
    { key: 'accounting', label: 'Accounting & Finance', root: appUrl('/app/accounting'), component: AccountingPage },
    { key: 'field-force', label: 'Field Force', root: appUrl('/app/field-force'), component: MrTrackingPage },
    { key: 'imports', label: 'Import Center', routes: [appUrl('/app/imports')], component: ImportWizardPage },
    { key: 'ocr', label: 'OCR Purchase', routes: [appUrl('/app/sales/ocr')], component: OcrImportPage },
    { key: 'reports', label: 'Reports', root: appUrl('/app/reports'), component: ReportsPage },
    { key: 'users', label: 'Users', routes: [appUrl('/app/administration/users')], component: UsersPage },
    { key: 'roles', label: 'Role Access', routes: [appUrl('/app/administration/roles')], component: RolesPage },
    { key: 'data-lookup', label: 'Master Data', routes: [appUrl('/app/administration/data-lookup')], component: DataLookupPage },
    { key: 'settings', label: 'Settings', routes: [appUrl('/app/settings')], component: SettingsPage },
];

export const routes = {
    [appUrl('/app')]: DashboardPage,
    [appUrl('/app/inventory/products')]: ProductsPage,
    [appUrl('/app/inventory/companies')]: ProductsPage,
    [appUrl('/app/inventory/units')]: ProductsPage,
    [appUrl('/app/inventory/categories')]: ProductsPage,
    [appUrl('/app/inventory/batches')]: ProductsPage,
    [appUrl('/app/inventory/stock-adjustment')]: ProductsPage,
    [appUrl('/app/inventory/case-movement')]: ProductsPage,
    [appUrl('/app/purchases')]: PurchasesPage,
    [appUrl('/app/purchases/entry')]: PurchasesPage,
    [appUrl('/app/purchases/bills')]: PurchasesPage,
    [appUrl('/app/purchases/orders')]: PurchasesPage,
    [appUrl('/app/purchases/returns')]: PurchasesPage,
    [appUrl('/app/sales')]: SalesPage,
    [appUrl('/app/sales/pos')]: SalesPage,
    [appUrl('/app/sales/invoices')]: SalesPage,
    [appUrl('/app/sales/returns')]: SalesPage,
    [appUrl('/app/sales/ocr')]: OcrImportPage,
    [appUrl('/app/imports')]: ImportWizardPage,
    [appUrl('/app/field-force/dashboard')]: MrTrackingPage,
    [appUrl('/app/field-force/performance')]: MrTrackingPage,
    [appUrl('/app/field-force/representatives')]: MrTrackingPage,
    [appUrl('/app/field-force/visits')]: MrTrackingPage,
    [appUrl('/app/field-force/branches')]: MrTrackingPage,
    [appUrl('/app/accounting')]: AccountingPage,
    [appUrl('/app/accounting/vouchers')]: AccountingPage,
    [appUrl('/app/accounting/day-book')]: ReportsPage,
    [appUrl('/app/accounting/cash-book')]: ReportsPage,
    [appUrl('/app/accounting/bank-book')]: ReportsPage,
    [appUrl('/app/accounting/ledgers')]: ReportsPage,
    [appUrl('/app/accounting/ledger')]: ReportsPage,
    [appUrl('/app/accounting/account-tree')]: ReportsPage,
    [appUrl('/app/accounting/trial-balance')]: ReportsPage,
    [appUrl('/app/accounting/profit-loss')]: ReportsPage,
    [appUrl('/app/accounting/payments')]: AccountingPage,
    [appUrl('/app/accounting/expenses')]: AccountingPage,
    [appUrl('/app/party/management')]: PartiesPage,
    [appUrl('/app/party/suppliers')]: PartiesPage,
    [appUrl('/app/party/customers')]: PartiesPage,
    [appUrl('/app/reports')]: ReportsPage,
    [appUrl('/app/reports/inventory')]: ReportsPage,
    [appUrl('/app/reports/sales')]: ReportsPage,
    [appUrl('/app/reports/purchase')]: ReportsPage,
    [appUrl('/app/reports/stock')]: ReportsPage,
    [appUrl('/app/reports/low-stock')]: ReportsPage,
    [appUrl('/app/reports/expiry')]: ReportsPage,
    [appUrl('/app/reports/smart-inventory')]: ReportsPage,
    [appUrl('/app/reports/accounting')]: ReportsPage,
    [appUrl('/app/reports/profit-loss')]: ReportsPage,
    [appUrl('/app/reports/supplier-performance')]: ReportsPage,
    [appUrl('/app/reports/supplier-ledger')]: ReportsPage,
    [appUrl('/app/reports/customer-ledger')]: ReportsPage,
    [appUrl('/app/reports/product-movement')]: ReportsPage,
    [appUrl('/app/reports/mr-performance')]: ReportsPage,
    [appUrl('/app/administration/users')]: UsersPage,
    [appUrl('/app/administration/roles')]: RolesPage,
    [appUrl('/app/administration/data-lookup')]: DataLookupPage,
    [appUrl('/app/settings')]: SettingsPage,
};
