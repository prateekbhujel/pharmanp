import React, { Suspense, useEffect, useMemo, useState } from 'react';
import { Avatar, Badge, Button, Dropdown, Layout, Menu, Space, Spin, Typography } from 'antd';
import {
    BarChartOutlined,
    BellOutlined,
    CloudUploadOutlined,
    DashboardOutlined,
    DollarOutlined,
    DownOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    MedicineBoxOutlined,
    SettingOutlined,
    SafetyCertificateOutlined,
    ShopOutlined,
    ShoppingCartOutlined,
    SyncOutlined,
    TeamOutlined,
    UserSwitchOutlined,
    WarningOutlined,
} from '@ant-design/icons';
import { http } from '../api/http';
import { endpoints } from '../api/endpoints';
import { useAuth } from '../auth/AuthProvider';
import { can } from '../utils/permissions';
import { isMacPlatform } from '../utils/platform';
import { appUrl } from '../utils/url';
import { useTheme } from '../theme/ThemeContext';
import { useApi } from '../hooks/useApi';
import { ColorPicker } from 'antd';
import { GlobalSearch } from '../components/GlobalSearch';
import { SearchOutlined } from '@ant-design/icons';

const { Header, Sider, Content } = Layout;

const DashboardPage = React.lazy(() => import('../../modules/dashboard/DashboardPage').then((module) => ({ default: module.DashboardPage })));
const ProductsPage = React.lazy(() => import('../../modules/inventory/ProductsPage').then((module) => ({ default: module.ProductsPage })));
const SalesPage = React.lazy(() => import('../../modules/sales/SalesPage').then((module) => ({ default: module.SalesPage })));
const ImportWizardPage = React.lazy(() => import('../../modules/imports/ImportWizardPage').then((module) => ({ default: module.ImportWizardPage })));
const SystemUpdatePage = React.lazy(() => import('../../modules/system/SystemUpdatePage').then((module) => ({ default: module.SystemUpdatePage })));
const OnboardingPage = React.lazy(() => import('../../modules/onboarding/OnboardingPage').then((module) => ({ default: module.OnboardingPage })));
const MrTrackingPage = React.lazy(() => import('../../modules/mr/MrTrackingPage').then((module) => ({ default: module.MrTrackingPage })));
const SettingsPage = React.lazy(() => import('../../modules/settings/SettingsPage').then((module) => ({ default: module.SettingsPage })));
const PurchasesPage = React.lazy(() => import('../../modules/purchases/PurchasesPage').then((module) => ({ default: module.PurchasesPage })));
const AccountingPage = React.lazy(() => import('../../modules/accounting/AccountingPage').then((module) => ({ default: module.AccountingPage })));
const PartiesPage = React.lazy(() => import('../../modules/party/PartiesPage').then((module) => ({ default: module.PartiesPage })));
const ReportsPage = React.lazy(() => import('../../modules/reports/ReportsPage').then((module) => ({ default: module.ReportsPage })));

const UsersPage = React.lazy(() => import('../../modules/settings/UsersPage').then((module) => ({ default: module.UsersPage })));
const RolesPage = React.lazy(() => import('../../modules/settings/RolesPage').then((module) => ({ default: module.RolesPage })));
const DataLookupPage = React.lazy(() => import('../../modules/settings/DataLookupPage').then((module) => ({ default: module.DataLookupPage })));

const routes = {
    [appUrl('/app')]: DashboardPage,
    [appUrl('/app/onboarding')]: OnboardingPage,
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
    [appUrl('/app/sales/pos')]: SalesPage,
    [appUrl('/app/sales/invoices')]: SalesPage,
    [appUrl('/app/sales/returns')]: SalesPage,
    [appUrl('/app/sales/ocr')]: SalesPage,
    [appUrl('/app/field-force/dashboard')]: MrTrackingPage,
    [appUrl('/app/field-force/performance')]: MrTrackingPage,
    [appUrl('/app/field-force/representatives')]: MrTrackingPage,
    [appUrl('/app/field-force/visits')]: MrTrackingPage,
    [appUrl('/app/field-force/branches')]: MrTrackingPage,
    [appUrl('/app/accounting')]: AccountingPage,
    [appUrl('/app/accounting/vouchers')]: AccountingPage,
    [appUrl('/app/accounting/ledgers')]: AccountingPage,
    [appUrl('/app/accounting/trial-balance')]: AccountingPage,
    [appUrl('/app/party/management')]: PartiesPage,
    [appUrl('/app/party/suppliers')]: PartiesPage,
    [appUrl('/app/party/customers')]: PartiesPage,
    [appUrl('/app/reports')]: ReportsPage,
    [appUrl('/app/reports/inventory')]: ReportsPage,
    [appUrl('/app/reports/sales')]: ReportsPage,
    [appUrl('/app/reports/supplier-performance')]: ReportsPage,
    [appUrl('/app/administration/users')]: UsersPage,
    [appUrl('/app/administration/roles')]: RolesPage,
    [appUrl('/app/administration/data-lookup')]: DataLookupPage,
    [appUrl('/app/settings')]: SettingsPage,
    [appUrl('/app/system/update-check')]: SystemUpdatePage,
};

function currentAppPath() {
    return window.location.pathname.replace(/\/$/, '') || appUrl('/app');
}

export function AppShell() {
    const { user: authUser } = useAuth();
    const { data: brandingData, loading: brandingLoading } = useApi(endpoints.branding);
    const { colorPrimary, setColorPrimary } = useTheme();
    
    const [collapsed, setCollapsed] = useState(false);
    const [pathname, setPathname] = useState(currentAppPath);
    const [alerts, setAlerts] = useState({ loading: true, lowStockRows: [], expiryRows: [], count: 0 });
    const [searchVisible, setSearchVisible] = useState(false);

    useEffect(() => {
        const handleKeyDown = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setSearchVisible(true);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    useEffect(() => {
        if (brandingData?.sidebar_default_collapsed !== undefined) {
            setCollapsed(Boolean(brandingData.sidebar_default_collapsed));
        }
    }, [brandingData]);

    useEffect(() => {
        if (brandingData?.accent_color && brandingData.accent_color !== colorPrimary) {
            setColorPrimary(brandingData.accent_color);
        }
    }, [brandingData, colorPrimary, setColorPrimary]);

    const layout = brandingData?.layout || 'vertical';
    const appName = brandingData?.app_name || 'PharmaNP';
    const logo = brandingData?.sidebar_logo_url || brandingData?.logo_url || brandingData?.app_icon_url;
    const user = authUser;
    // Best-match: try exact match first, then longest prefix match
    const activeKey = useMemo(() => {
        if (routes[pathname]) return pathname;
        // Sort routes by path length descending so most specific wins
        const sortedRoutes = Object.keys(routes).sort((a, b) => b.length - a.length);
        const match = sortedRoutes.find((route) => pathname.startsWith(route));
        return match || appUrl('/app');
    }, [pathname]);
    const ActivePage = routes[activeKey] || DashboardPage;

    const { items: menuItems, routesByKey, selectedMenuKey, openKeys } = useMemo(() => {
        const canInventory = can(user, 'inventory.products.view') || can(user, 'inventory.masters.manage');
        const canPurchase = can(user, 'purchase.entries.view') || can(user, 'purchase.entries.create') || can(user, 'purchase.orders.manage');
        const canSales = can(user, 'sales.invoices.view') || can(user, 'sales.pos.use');
        const canParties = can(user, 'party.suppliers.view') || can(user, 'party.customers.view') || user?.is_owner;
        const canAccounting = can(user, 'accounting.vouchers.view') || can(user, 'accounting.books.view') || can(user, 'accounting.trial_balance.view');
        const canReports = can(user, 'reports.view');
        const canSetup = can(user, 'settings.manage') || can(user, 'users.manage') || can(user, 'roles.manage') || user?.is_owner;
        const routeMap = {};
        const register = (key, route) => {
            routeMap[key] = route;
            return key;
        };
        const child = (key, label, route) => ({ key: register(key, route), label });
        const items = [
            { key: 'category-main', label: 'Main Menu', disabled: true, className: 'menu-category' },
            { key: register('dashboard', appUrl('/app')), icon: <DashboardOutlined />, label: 'Dashboard', show: can(user, 'dashboard.view') },
            {
                key: 'inventory',
                icon: <MedicineBoxOutlined />,
                label: 'Inventory',
                show: canInventory,
                children: [
                    child('inventory-company', 'Company (Manufacturer)', appUrl('/app/inventory/companies')),
                    child('inventory-unit', 'Unit', appUrl('/app/inventory/units')),
                    child('inventory-categories', 'Categories', appUrl('/app/inventory/categories')),
                    child('inventory-product', 'Product', appUrl('/app/inventory/products')),
                    child('inventory-batches', 'Batches', appUrl('/app/inventory/batches')),
                    child('inventory-adjustment', 'Stock Adjustment', appUrl('/app/inventory/stock-adjustment')),
                    child('inventory-movement', 'Case Movement', appUrl('/app/inventory/case-movement')),
                ],
            },
            {
                key: 'purchase',
                icon: <ShopOutlined />,
                label: 'Purchase',
                show: canPurchase,
                children: [
                    child('purchase-bills', 'Purchase Bills', appUrl('/app/purchases/bills')),
                    child('purchase-entry', 'Purchase Entry', appUrl('/app/purchases/entry')),
                    child('purchase-orders', 'Purchase Orders', appUrl('/app/purchases/orders')),
                    child('purchase-returns', 'Purchase Returns', appUrl('/app/purchases/returns')),
                ],
            },
            { key: register('party-management', appUrl('/app/party/management')), icon: <TeamOutlined />, label: 'Party Management', show: canParties },
            {
                key: 'sales',
                icon: <DollarOutlined />,
                label: 'Sales / POS',
                show: canSales,
                children: [
                    child('sales-pos', 'POS Terminal', appUrl('/app/sales/pos')),
                    child('sales-invoices', 'Sales Invoices', appUrl('/app/sales/invoices')),
                    child('sales-returns', 'Sales Returns', appUrl('/app/sales/returns')),
                ],
            },
            { key: register('sales-ocr', appUrl('/app/sales/ocr')), icon: <CloudUploadOutlined />, label: 'OCR', show: can(user, 'sales.ocr') },
            {
                key: 'accounting',
                icon: <BarChartOutlined />,
                label: 'Accounting & Finance',
                show: canAccounting,
                children: [
                    child('accounting-vouchers', 'Vouchers', appUrl('/app/accounting/vouchers')),
                    child('accounting-ledgers', 'Ledgers', appUrl('/app/accounting/ledgers')),
                    child('accounting-trial', 'Trial Balance', appUrl('/app/accounting/trial-balance')),
                ],
            },
            {
                key: 'field-force',
                icon: <UserSwitchOutlined />,
                label: 'Field Force',
                show: can(user, 'mr.view'),
                children: [
                    child('field-force-dashboard', 'Dashboard', appUrl('/app/field-force/dashboard')),
                    child('field-force-performance', 'Performance', appUrl('/app/field-force/performance')),
                    child('field-force-representatives', 'Representatives', appUrl('/app/field-force/representatives')),
                    child('field-force-visits', 'Visits', appUrl('/app/field-force/visits')),
                    child('field-force-branches', 'Branches', appUrl('/app/field-force/branches')),
                ],
            },
            {
                key: 'reports',
                icon: <BarChartOutlined />,
                label: 'Reports',
                show: canReports,
                children: [
                    child('reports-inventory', 'Inventory Reports', appUrl('/app/reports/inventory')),
                    child('reports-sales', 'Sales Reports', appUrl('/app/reports/sales')),
                    child('reports-performance', 'Supplier Performance', appUrl('/app/reports/supplier-performance')),
                ],
            },
            { key: 'category-admin', label: 'Administration', disabled: true, className: 'menu-category' },
            { key: register('admin-users', appUrl('/app/administration/users')), icon: <TeamOutlined />, label: 'Users', show: can(user, 'users.manage') },
            { key: register('admin-roles', appUrl('/app/administration/roles')), icon: <SafetyCertificateOutlined />, label: 'Role Access', show: can(user, 'roles.manage') },
            { key: register('admin-data', appUrl('/app/administration/data-lookup')), icon: <SyncOutlined />, label: 'Data Lookup', show: canSetup },
            { key: register('settings', appUrl('/app/settings')), icon: <SettingOutlined />, label: 'Settings', show: canSetup },
            { key: register('onboarding', appUrl('/app/onboarding')), icon: <ShoppingCartOutlined />, label: 'First Run Guide', show: canSetup },
            {
                key: 'system',
                icon: <SyncOutlined />,
                label: 'System',
                show: user?.is_owner,
                children: [
                    child('system-update', 'Update Check', appUrl('/app/system/update-check')),
                ],
            },
        ];

        const flatItems = items.filter(i => i.show !== false).map(i => {
            if (i.children) {
                i.children = i.children.filter(c => c.show !== false);
            }
            return i;
        });

        const activeKey = pathname;
        let selectedKey = null;
        let openKey = null;

        Object.entries(routeMap).forEach(([key, route]) => {
            if (pathname.startsWith(route)) {
                selectedKey = key;
                // Find parent for openKeys
                const parent = flatItems.find(p => p.children?.some(c => c.key === key));
                if (parent) openKey = parent.key;
            }
        });

        return { items: flatItems, routesByKey: routeMap, selectedMenuKey: selectedKey, openKeys: openKey ? [openKey] : [] };
    }, [pathname, user]);


    useEffect(() => {
        function syncPath() {
            setPathname(currentAppPath());
        }

        window.addEventListener('popstate', syncPath);

        return () => window.removeEventListener('popstate', syncPath);
    }, []);

    useEffect(() => {
        let active = true;

        http.get(endpoints.dashboard)
            .then(({ data }) => {
                if (!active) {
                    return;
                }

                const payload = data.data || {};
                const stats = payload.stats || {};
                const lowStockRows = payload.low_stock_rows || [];
                const expiryRows = payload.expiry_rows || [];
                const count = Number(stats.low_stock || lowStockRows.length || 0)
                    + Number(stats.expiring_batches || expiryRows.length || 0);

                setAlerts({ loading: false, lowStockRows, expiryRows, count });
            })
            .catch(() => {
                if (active) {
                    setAlerts({ loading: false, lowStockRows: [], expiryRows: [], count: 0 });
                }
            });

        return () => {
            active = false;
        };
    }, []);

    const notificationItems = useMemo(() => {
        const items = [];

        if (!alerts.count) {
            return [
                {
                    key: 'empty',
                    disabled: true,
                    label: <div className="notification-empty">No stock alerts right now</div>,
                },
            ];
        }

        if (alerts.lowStockRows.length > 0) {
            items.push({
                key: 'low-stock-title',
                disabled: true,
                label: <div className="notification-title"><WarningOutlined /> Low stock</div>,
            });
            alerts.lowStockRows.slice(0, 4).forEach((item) => {
                items.push({
                    key: `low-stock-${item.id}`,
                    label: (
                        <div className="notification-row">
                            <strong>{item.name}</strong>
                            <span>{item.stock_on_hand} in stock, reorder at {item.reorder_level}</span>
                        </div>
                    ),
                });
            });
        }

        if (alerts.expiryRows.length > 0) {
            items.push({
                key: 'expiry-title',
                disabled: true,
                label: <div className="notification-title"><WarningOutlined /> Expiry watch</div>,
            });
            alerts.expiryRows.slice(0, 4).forEach((item) => {
                items.push({
                    key: `expiry-${item.id}`,
                    label: (
                        <div className="notification-row">
                            <strong>{item.name}</strong>
                            <span>Batch {item.batch_no || '-'} expires {item.expires_at}</span>
                        </div>
                    ),
                });
            });
        }

        return items;
    }, [alerts]);

    const horizontalMenuItems = useMemo(() => (
        menuItems.filter((item) => item.className !== 'menu-category')
    ), [menuItems]);

    function goTo(route) {
        if (!route || route === pathname) {
            return;
        }

        window.history.pushState({}, '', route);
        setPathname(currentAppPath());
    }

    function navigate({ key }) {
        goTo(routesByKey[key]);
    }

    function handleNotificationClick({ key }) {
        if (key.startsWith('low-stock')) {
            goTo(appUrl('/app/reports/low-stock'));
        }

        if (key.startsWith('expiry')) {
            goTo(appUrl('/app/reports/expiry'));
        }
    }

    function logout() {
        http.post(appUrl('/logout')).finally(() => {
            window.location.href = appUrl('/login');
        });
    }

    const profileItems = [
        { key: 'profile', label: 'Profile', onClick: () => { window.location.href = appUrl('/app/settings'); } },
        { key: 'logout', label: 'Sign Out', onClick: logout },
    ];

    const THEME_PRESETS = [
        { color: '#0891b2', name: 'Medical Cyan' },
        { color: '#3b82f6', name: 'Royal Blue' },
        { color: '#6366f1', name: 'Indigo' },
        { color: '#8b5cf6', name: 'Amethyst' },
        { color: '#10b981', name: 'Emerald' },
        { color: '#f59e0b', name: 'Amber' },
        { color: '#ef4444', name: 'Rose' },
        { color: '#0f172a', name: 'Slate' },
    ];

    const themeMenu = {
        items: THEME_PRESETS.map((p) => ({
            key: p.color,
            label: (
                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                    <div style={{ width: 14, height: 14, borderRadius: '50%', background: p.color }} />
                    <span style={{ fontWeight: 500 }}>{p.name}</span>
                </div>
            )
        })),
        onClick: ({ key }) => setColorPrimary(key)
    };

    return (
        <Layout className={`app-shell app-shell-${layout}`}>
            {layout === 'vertical' && (
            <Sider width={250} collapsed={collapsed} className="app-sidebar" breakpoint="lg" collapsedWidth={72} trigger={null}>
                <div className="main-sidebar-header">
                    <a href={appUrl('/app')} className="header-logo">
                        {logo ? <img src={logo} alt={appName} className="brand-logo" /> : <div className="brand-mark" style={{ background: `linear-gradient(135deg, ${colorPrimary}, var(--primary-color-dark, #0369a1))` }}><SafetyCertificateOutlined /></div>}
                        {!collapsed && <strong>{appName}</strong>}
                    </a>
                </div>
                <div className="main-sidebar">
                    <Menu
                        mode="inline"
                        selectedKeys={[selectedMenuKey]}
                        defaultOpenKeys={openKeys}
                        items={menuItems}
                        onClick={navigate}
                    />
                </div>
            </Sider>
            )}
            <Layout>
                <Header className="app-topbar">
                    <Space className="header-content-left">
                        {layout === 'vertical' && (
                            <Button
                                aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                                icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                                onClick={() => setCollapsed((value) => !value)}
                            />
                        )}
                        {layout === 'horizontal' && (
                            <>
                                {logo ? <img src={logo} alt={appName} className="brand-logo brand-logo-topbar" /> : <SafetyCertificateOutlined style={{ color: colorPrimary, fontSize: 20 }} />}
                                <Typography.Text strong className="topbar-brand-name">{appName}</Typography.Text>
                                <Menu mode="horizontal" selectedKeys={[selectedMenuKey]} items={horizontalMenuItems} onClick={navigate} className="topbar-menu" />
                            </>
                        )}
                        <Button 
                            className="search-trigger-btn" 
                            onClick={() => setSearchVisible(true)}
                            icon={<SearchOutlined />}
                        >
                            <span>Search...</span>
                            <span className="search-trigger-kbd">
                                {isMacPlatform() ? '⌘K' : 'Ctrl+K'}
                            </span>
                        </Button>
                    </Space>
                    <Space className="header-content-right" size={18}>
                        <Dropdown
                            menu={{ items: notificationItems, onClick: handleNotificationClick }}
                            placement="bottomRight"
                            trigger={['click']}
                            classNames={{ root: 'notification-dropdown' }}
                        >
                            <Badge count={alerts.count} size="small" overflowCount={99}>
                                <Button type="text" loading={alerts.loading} icon={<BellOutlined />} />
                            </Badge>
                        </Dropdown>
                        <Typography.Text className="welcome-text">Welcome! <strong>{user?.name}</strong></Typography.Text>
                        <Avatar>{user?.name?.slice(0, 1)}</Avatar>
                        <Dropdown menu={{ items: profileItems }} trigger={['click']}>
                            <Button type="text" icon={<DownOutlined />} />
                        </Dropdown>
                    </Space>
                </Header>
                <Content className="app-content">
                    <Suspense fallback={<div className="screen-center"><Spin /></div>}>
                        <ActivePage key={activeKey} />
                    </Suspense>
                </Content>
            </Layout>
            <GlobalSearch 
                visible={searchVisible} 
                onCancel={() => setSearchVisible(false)} 
                onNavigate={(route) => {
                    if (route.startsWith('/')) {
                        goTo(route);
                    } else {
                        // Legacy keys if any
                        if (route === 'dashboard') goTo(appUrl('/app'));
                        if (route === 'products') goTo(appUrl('/app/inventory/products'));
                        if (route === 'sales') goTo(appUrl('/app/sales/invoices'));
                        if (route === 'users') goTo(appUrl('/app/administration/users'));
                        if (route === 'settings') goTo(appUrl('/app/settings'));
                    }
                }}
            />
        </Layout>
    );
}
