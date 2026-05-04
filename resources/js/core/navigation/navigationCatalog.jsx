import React from 'react';
import {
    BarChartOutlined,
    CloudUploadOutlined,
    ContainerOutlined,
    DashboardOutlined,
    DollarOutlined,
    MedicineBoxOutlined,
    SettingOutlined,
    ShopOutlined,
    ShoppingCartOutlined,
    TeamOutlined,
    UserSwitchOutlined,
    WalletOutlined,
} from '@ant-design/icons';
import { can } from '../utils/permissions';
import { appUrl } from '../utils/url';

function normalizePath(route) {
    return String(route || '').split('?')[0].replace(/\/$/, '') || appUrl('/app');
}

function normalizeFullPath(route) {
    const [path, query] = String(route || '').split('?');
    const normalizedPath = normalizePath(path);

    return query ? `${normalizedPath}?${query}` : normalizedPath;
}

function createRouteRegistry() {
    const routesByKey = {};

    const register = (key, route) => {
        routesByKey[key] = route;

        return key;
    };

    const child = (key, label, route, meta = {}) => ({
        key: register(key, route),
        label,
        ...meta,
    });

    return { routesByKey, register, child };
}

function visibleItems(items) {
    return items
        .filter((item) => item.show !== false)
        .map(({ show, children, description, searchType, ...item }) => {
            const meta = { description, searchType };

            if (!children) {
                return { ...item, meta };
            }

            return {
                ...item,
                meta,
                children: visibleItems(children),
            };
        })
        .filter((item) => !item.children || item.children.length > 0);
}

function flattenMenuItems(items, parent = null) {
    return items.flatMap((item) => [
        { ...item, parent },
        ...flattenMenuItems(item.children || [], item),
    ]);
}

function routeMatches(pathname, route) {
    if (String(route || '').includes('?')) {
        return normalizeFullPath(pathname) === normalizeFullPath(route);
    }

    const path = normalizePath(pathname);
    const candidate = normalizePath(route);
    return path === candidate || (candidate !== appUrl('/app') && path.startsWith(candidate));
}

function findSelectedNavigation(items, routesByKey, pathname) {
    const routeEntries = Object.entries(routesByKey).sort((a, b) => normalizeFullPath(b[1]).length - normalizeFullPath(a[1]).length);
    const selectedEntry = routeEntries.find(([, route]) => routeMatches(pathname, route));
    const selectedKey = selectedEntry?.[0] || (normalizePath(pathname) === appUrl('/app') ? 'dashboard' : null);
    const selectedParent = items.find((item) => item.children?.some((childItem) => childItem.key === selectedKey));
    const selectedChild = selectedParent?.children?.find((childItem) => childItem.key === selectedKey);
    const selectedRoot = items.find((item) => item.key === selectedKey);

    return {
        selectedKey,
        openKeys: selectedParent ? [selectedParent.key] : [],
        breadcrumbs: selectedParent && selectedChild
            ? [
                { key: selectedParent.key, title: selectedParent.label },
                { key: selectedChild.key, title: selectedChild.label },
            ]
            : selectedRoot
                ? [{ key: selectedRoot.key, title: selectedRoot.label }]
                : [],
    };
}

function buildSearchItems(items, routesByKey) {
    return flattenMenuItems(items)
        .filter((item) => routesByKey[item.key])
        .map((item) => ({
            key: item.key,
            label: item.label,
            route: routesByKey[item.key],
            type: item.meta?.searchType || item.parent?.label || 'Page',
            description: item.meta?.description || `${item.label} workspace`,
        }));
}

export function buildNavigationModel(user, pathname) {
    const canInventory = can(user, 'inventory.products.view') || can(user, 'inventory.masters.manage');
    const canPurchase = can(user, 'purchase.entries.view') || can(user, 'purchase.entries.create') || can(user, 'purchase.orders.manage');
    const canSales = can(user, 'sales.invoices.view') || can(user, 'sales.pos.use');
    const canParties = can(user, 'party.suppliers.view') || can(user, 'party.customers.view') || user?.is_owner;
    const canAccounting = can(user, 'accounting.vouchers.view') || can(user, 'accounting.books.view') || can(user, 'accounting.trial_balance.view');
    const canReports = can(user, 'reports.view');
    const canSetup = can(user, 'settings.manage') || can(user, 'users.manage') || can(user, 'roles.manage') || user?.is_owner;
    const canImports = canInventory || canPurchase || canSetup;
    const { routesByKey, register, child } = createRouteRegistry();

    const items = visibleItems([
        {
            key: register('dashboard', appUrl('/app')),
            icon: <DashboardOutlined />,
            label: 'Dashboard',
            show: can(user, 'dashboard.view'),
            searchType: 'Page',
            description: 'Business overview, stock alerts and recent activity',
        },
        {
            key: 'inventory',
            icon: <MedicineBoxOutlined />,
            label: 'Inventory',
            show: canInventory,
            children: [
                child('inventory-product', 'Products', appUrl('/app/inventory/products'), { description: 'Product master, pricing, barcode and stock setup' }),
                child('inventory-company', 'Companies (MFR)', appUrl('/app/inventory/companies'), { description: 'Manufacturer and company master records' }),
                child('inventory-unit', 'Units', appUrl('/app/inventory/units'), { description: 'Product units and packaging units' }),
                child('inventory-adjustment', 'Stock Adjustment', appUrl('/app/inventory/stock-adjustment'), { description: 'Manual stock correction with ledger impact' }),
                child('inventory-movement', 'Stock Ledger', appUrl('/app/inventory/stock-ledger'), { description: 'Stock movement ledger and remaining stock flow' }),
            ],
        },
        {
            key: 'purchase',
            icon: <ShopOutlined />,
            label: 'Purchase',
            show: canPurchase,
            children: [
                child('purchase-bills', 'Purchase Bills', appUrl('/app/purchases/bills'), { description: 'Supplier bills, batch creation and payables' }),
                child('purchase-orders', 'Purchase Orders', appUrl('/app/purchases/orders'), { description: 'Purchase order workflow and receiving' }),
                child('purchase-returns', 'Purchase Returns', appUrl('/app/purchases/returns'), { description: 'Supplier return workflow' }),
                child('purchase-expiry-returns', 'Purchase Expiry Returns', appUrl('/app/purchases/expiry-returns'), { description: 'Expired or near-expired supplier return workflow' }),
                child('purchase-payment-out', 'Payment Out', appUrl('/app/accounting/payments?direction=out'), { description: 'Supplier payment settlement' }),
                child('purchase-aging', 'Supplier Aging', appUrl('/app/reports/supplier-aging'), { description: 'Supplier due and aging buckets' }),
            ],
        },
        {
            key: 'sales',
            icon: <DollarOutlined />,
            label: 'Sales',
            show: canSales,
            children: [
                child('sales-index', 'Sales', appUrl('/app/sales'), { description: 'Sales invoice and POS workflow' }),
                child('sales-returns', 'Sales Return', appUrl('/app/sales/returns'), { description: 'Customer return workflow' }),
                child('sales-expiry-returns', 'Sales Expiry Return', appUrl('/app/sales/expiry-returns'), { description: 'Expired product return from customer' }),
                child('sales-payment-in', 'Payment In', appUrl('/app/accounting/payments?direction=in'), { description: 'Customer payment settlement' }),
                child('sales-aging', 'Customer Aging', appUrl('/app/reports/customer-aging'), { description: 'Customer receivable and aging buckets' }),
            ],
        },
        {
            key: register('party-management', appUrl('/app/party/management')),
            icon: <TeamOutlined />,
            label: 'Party Management',
            show: canParties,
            searchType: 'Party',
            description: 'Customers, suppliers and ledgers',
        },
        {
            key: register('imports', appUrl('/app/imports')),
            icon: <CloudUploadOutlined />,
            label: 'Import Center',
            show: canImports,
            searchType: 'Import',
            description: 'Excel import, mapping, validation and rejected rows',
        },
        {
            key: register('sales-ocr', appUrl('/app/sales/ocr')),
            icon: <CloudUploadOutlined />,
            label: 'OCR Purchase',
            show: can(user, 'sales.ocr') || canImports,
            searchType: 'Import',
            description: 'OCR purchase helper and draft handoff',
        },
        {
            key: 'accounting',
            icon: <WalletOutlined />,
            label: 'Accounting & Finance',
            show: canAccounting,
            children: [
                child('accounting-vouchers', 'Vouchers', appUrl('/app/accounting/vouchers'), { description: 'Manual journal and adjustment vouchers' }),
                child('accounting-payments', 'Payments', appUrl('/app/accounting/payments'), { description: 'Payment in, payment out and settlement history' }),
                child('accounting-expenses', 'Expenses', appUrl('/app/accounting/expenses'), { description: 'Business expense entries' }),
                child('accounting-day-book', 'Day Book', appUrl('/app/accounting/day-book'), { description: 'Daily accounting activity' }),
                child('accounting-cash-book', 'Cash Book', appUrl('/app/accounting/cash-book'), { description: 'Cash movement register' }),
                child('accounting-bank-book', 'Bank Book', appUrl('/app/accounting/bank-book'), { description: 'Bank movement register' }),
                child('accounting-ledger', 'Ledger', appUrl('/app/accounting/ledger'), { description: 'Account ledger and running movement' }),
                child('accounting-account-tree', 'Account Tree', appUrl('/app/accounting/account-tree'), { description: 'Chart of accounts tree' }),
                child('accounting-trial-balance', 'Trial Balance', appUrl('/app/accounting/trial-balance'), { description: 'Debit and credit balance summary' }),
                child('accounting-profit-loss', 'Profit & Loss', appUrl('/app/accounting/profit-loss'), { description: 'Income and expense summary' }),
            ],
        },
        {
            key: 'field-force',
            icon: <UserSwitchOutlined />,
            label: 'Field Force',
            show: can(user, 'mr.view'),
            children: [
                child('field-force-dashboard', 'Dashboard', appUrl('/app/field-force/dashboard'), { description: 'MR and area field-force overview' }),
                child('field-force-performance', 'Performance', appUrl('/app/field-force/performance'), { description: 'MR, division and area performance' }),
                child('field-force-representatives', 'Representatives', appUrl('/app/field-force/representatives'), { description: 'MR employee profiles and hierarchy' }),
                child('field-force-visits', 'Visits', appUrl('/app/field-force/visits'), { description: 'MR visits, purpose and location names' }),
                child('field-force-targets', 'Targets', appUrl('/app/administration/targets'), { description: 'Primary and secondary target setup' }),
            ],
        },
        {
            key: register('reports', appUrl('/app/reports')),
            icon: <BarChartOutlined />,
            label: 'Reports',
            show: canReports,
            searchType: 'Report',
            description: 'Sales, stock, aging, target and accounting reports',
        },
        {
            key: 'admin-master',
            icon: <ContainerOutlined />,
            label: 'Master',
            show: canSetup,
            children: [
                child('admin-users', 'Users', appUrl('/app/administration/users'), { description: 'User accounts and impersonation' }),
                child('admin-roles', 'Roles & Permissions', appUrl('/app/administration/roles'), { description: 'Permission matrix and role access' }),
                child('admin-employees', 'Employees', appUrl('/app/administration/employees'), { description: 'Employee profiles and reporting hierarchy' }),
                child('admin-branches', 'Branches', appUrl('/app/administration/branches'), { description: 'Branch setup with areas' }),
                child('admin-areas', 'Areas', appUrl('/app/administration/areas'), { description: 'Area setup under branches' }),
                child('admin-divisions', 'Divisions', appUrl('/app/administration/divisions'), { description: 'Division-wise product and MR separation' }),
                child('admin-targets', 'Targets', appUrl('/app/administration/targets'), { description: 'Company, division, area, product and MR targets' }),
                child('admin-payment-modes', 'Payment Modes', appUrl('/app/administration/payment-modes'), { description: 'Cash, bank, QR and payment mode setup' }),
                child('admin-party-types', 'Party Types', appUrl('/app/administration/party-types'), { description: 'Customer and party classification' }),
                child('admin-supplier-types', 'Supplier Types', appUrl('/app/administration/supplier-types'), { description: 'Supplier classification' }),
                child('admin-data', 'Master Data', appUrl('/app/administration/data-lookup'), { description: 'Reusable dropdown and lookup values' }),
                child('admin-developer-guide', 'Developer Guide', appUrl('/app/developer-guide'), { description: 'Frontend, backend and full-stack onboarding guide' }),
            ],
        },
        {
            key: register('settings', appUrl('/app/settings')),
            icon: <SettingOutlined />,
            label: 'Settings',
            show: canSetup,
            searchType: 'Admin',
            description: 'Branding, fiscal years, mail and document numbering',
        },
    ]);

    const selected = findSelectedNavigation(items, routesByKey, pathname);

    return {
        items,
        routesByKey,
        selectedMenuKey: selected.selectedKey,
        openKeys: selected.openKeys,
        breadcrumbs: selected.breadcrumbs,
        searchItems: buildSearchItems(items, routesByKey),
    };
}
