import React, { Suspense, useEffect, useMemo, useState } from 'react';
import { Avatar, Badge, Button, Dropdown, Layout, Menu, Space, Spin, Typography } from 'antd';
import {
    BarChartOutlined,
    BellOutlined,
    ClockCircleOutlined,
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
    TeamOutlined,
    UserSwitchOutlined,
    WarningOutlined,
    SearchOutlined,
    ContainerOutlined,
    FileTextOutlined,
    WalletOutlined,
    HistoryOutlined,
} from '@ant-design/icons';
import { http } from '../api/http';
import { endpoints } from '../api/endpoints';
import { useAuth } from '../auth/AuthProvider';
import { can } from '../utils/permissions';
import { isMacPlatform } from '../utils/platform';
import { appUrl } from '../utils/url';
import { useTheme } from '../theme/ThemeContext';
import { useApi } from '../hooks/useApi';
import { GlobalSearch } from '../components/GlobalSearch';
import { useBranding } from '../context/BrandingContext';
import { formatCalendarDate } from '../utils/calendar';

const { Header, Sider, Content } = Layout;

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

const SIDEBAR_COLLAPSE_STORAGE_KEY = 'pharmanp-sidebar-collapsed';

const routes = {
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

function currentAppPath() {
    return window.location.pathname.replace(/\/$/, '') || appUrl('/app');
}

export function AppShell() {
    const { user: authUser } = useAuth();
    const { branding: brandingData, loading: brandingLoading } = useBranding();
    const { colorPrimary } = useTheme();

    const [collapsed, setCollapsed] = useState(true);
    const [isCompactViewport, setIsCompactViewport] = useState(false);
    const [pathname, setPathname] = useState(currentAppPath);
    const [alerts, setAlerts] = useState({ loading: true, lowStockRows: [], expiryRows: [], count: 0 });
    const [searchVisible, setSearchVisible] = useState(false);
    const [now, setNow] = useState(new Date());

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
        const timer = window.setInterval(() => setNow(new Date()), 1000);
        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        const media = window.matchMedia('(max-width: 900px)');
        const syncViewport = () => {
            setIsCompactViewport(media.matches);
            if (media.matches) {
                setCollapsed(true);
            }
        };

        syncViewport();
        media.addEventListener('change', syncViewport);

        return () => media.removeEventListener('change', syncViewport);
    }, []);

    useEffect(() => {
        if (isCompactViewport) return;

        const storedPreference = window.localStorage.getItem(SIDEBAR_COLLAPSE_STORAGE_KEY);
        if (storedPreference !== null) {
            setCollapsed(storedPreference === '1');
            return;
        }

        if (brandingData?.sidebar_default_collapsed !== undefined) {
            setCollapsed(Boolean(brandingData.sidebar_default_collapsed));
            return;
        }

        setCollapsed(true);
    }, [brandingData, isCompactViewport]);

    useEffect(() => {
        if (isCompactViewport) return;
        window.localStorage.setItem(SIDEBAR_COLLAPSE_STORAGE_KEY, collapsed ? '1' : '0');
    }, [collapsed, isCompactViewport]);

    const layout = brandingData?.layout || 'vertical';
    const appName = brandingData?.app_name || 'PharmaNP';
    const logo = brandingData?.sidebar_logo_url || brandingData?.logo_url || brandingData?.app_icon_url;
    const user = authUser;

    const activeKey = useMemo(() => {
        if (routes[pathname]) return pathname;
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
        const canImports = canInventory || canPurchase || canSetup;
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
                    child('inventory-product', 'Products', appUrl('/app/inventory/products')),
                    child('inventory-batches', 'Batches', appUrl('/app/inventory/batches')),
                    child('inventory-company', 'Companies (MFR)', appUrl('/app/inventory/companies')),
                    child('inventory-unit', 'Units', appUrl('/app/inventory/units')),
                    child('inventory-categories', 'Categories', appUrl('/app/inventory/categories')),
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
                    child('purchase-orders', 'Purchase Orders', appUrl('/app/purchases/orders')),
                    child('purchase-returns', 'Purchase Returns', appUrl('/app/purchases/returns')),
                ],
            },
            {
                key: 'sales',
                icon: <DollarOutlined />,
                label: 'Sales',
                show: canSales,
                children: [
                    child('sales-index', 'Sales', appUrl('/app/sales')),
                    child('sales-returns', 'Sales Return', appUrl('/app/sales/returns')),
                ],
            },
            { key: register('party-management', appUrl('/app/party/management')), icon: <TeamOutlined />, label: 'Party Management', show: canParties },
            { key: register('imports', appUrl('/app/imports')), icon: <CloudUploadOutlined />, label: 'Import Center', show: canImports },
            { key: register('sales-ocr', appUrl('/app/sales/ocr')), icon: <CloudUploadOutlined />, label: 'OCR Purchase', show: can(user, 'sales.ocr') || canImports },
            {
                key: 'accounting',
                icon: <WalletOutlined />,
                label: 'Accounting & Finance',
                show: canAccounting,
                children: [
                    child('accounting-vouchers', 'Vouchers', appUrl('/app/accounting/vouchers')),
                    child('accounting-payments', 'Payments', appUrl('/app/accounting/payments')),
                    child('accounting-expenses', 'Expenses', appUrl('/app/accounting/expenses')),
                    child('accounting-day-book', 'Day Book', appUrl('/app/accounting/day-book')),
                    child('accounting-cash-book', 'Cash Book', appUrl('/app/accounting/cash-book')),
                    child('accounting-bank-book', 'Bank Book', appUrl('/app/accounting/bank-book')),
                    child('accounting-ledger', 'Ledger', appUrl('/app/accounting/ledger')),
                    child('accounting-account-tree', 'Account Tree', appUrl('/app/accounting/account-tree')),
                    child('accounting-trial-balance', 'Trial Balance', appUrl('/app/accounting/trial-balance')),
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
                ],
            },
            { key: register('reports', appUrl('/app/reports')), icon: <BarChartOutlined />, label: 'Reports', show: canReports },
            { key: 'category-admin', label: 'Administration', disabled: true, className: 'menu-category' },
            {
                key: 'admin-master',
                icon: <ContainerOutlined />,
                label: 'Master',
                show: canSetup,
                children: [
                    child('admin-users', 'Users', appUrl('/app/administration/users')),
                    child('admin-roles', 'Roles & Permissions', appUrl('/app/administration/roles')),
                    child('admin-data', 'Master Data', appUrl('/app/administration/data-lookup')),
                ],
            },
            { key: register('settings', appUrl('/app/settings')), icon: <SettingOutlined />, label: 'Settings', show: canSetup },
        ];

        const flatItems = items.filter((item) => item.show !== false).map(({ show, children, ...item }) => {
            if (!children) {
                return item;
            }

            return {
                ...item,
                children: children
                    .filter((childItem) => childItem.show !== false)
                    .map(({ show: childShow, ...childItem }) => childItem),
            };
        });

        const sortedRouteKeys = Object.entries(routeMap).sort((a, b) => b[1].length - a[1].length);
        let selectedKey = null;
        let openKey = null;

        for (const [key, route] of sortedRouteKeys) {
            if (pathname === route || (route !== appUrl('/app') && pathname.startsWith(route))) {
                selectedKey = key;
                const parent = flatItems.find(p => p.children?.some(c => c.key === key));
                if (parent) openKey = parent.key;
                break;
            }
        }

        // Fallback to dashboard only if exactly on home
        if (!selectedKey && pathname === appUrl('/app')) {
            selectedKey = 'dashboard';
        }

        return { items: flatItems, routesByKey: routeMap, selectedMenuKey: selectedKey, openKeys: openKey ? [openKey] : [] };
    }, [pathname, user]);


    useEffect(() => {
        function syncPath() { setPathname(currentAppPath()); }
        window.addEventListener('popstate', syncPath);
        return () => window.removeEventListener('popstate', syncPath);
    }, []);

    useEffect(() => {
        let active = true;
        http.get(endpoints.dashboard)
            .then(({ data }) => {
                if (!active) return;
                const payload = data.data || {};
                const stats = payload.stats || {};
                const lowStockRows = payload.low_stock_rows || [];
                const expiryRows = payload.expiry_rows || [];
                const count = Number(stats.low_stock || lowStockRows.length || 0)
                    + Number(stats.expiring_batches || expiryRows.length || 0);
                setAlerts({ loading: false, lowStockRows, expiryRows, count });
            })
            .catch(() => {
                if (active) setAlerts({ loading: false, lowStockRows: [], expiryRows: [], count: 0 });
            });
        return () => { active = false; };
    }, []);

    const notificationItems = useMemo(() => {
        if (!alerts.count) {
            return [{ key: 'empty', disabled: true, label: <div className="notification-empty">No stock alerts right now</div> }];
        }
        const items = [];

        // Header with Mark All as Read
        items.push({
            key: 'header',
            disabled: true,
            label: (
                <div className="notification-tray-header">
                    <strong>Notifications</strong>
                    <Button
                        type="link"
                        size="small"
                        onClick={(e) => {
                            e.stopPropagation();
                            setAlerts({ loading: false, lowStockRows: [], expiryRows: [], count: 0 });
                        }}
                    >
                        Mark all as read
                    </Button>
                </div>
            )
        });
        items.push({ type: 'divider' });

        if (alerts.lowStockRows.length > 0) {
            items.push({ key: 'low-stock-title', disabled: true, label: <div className="notification-title"><WarningOutlined /> Low stock alert</div> });
            alerts.lowStockRows.slice(0, 5).forEach((item) => {
                items.push({
                    key: `low-stock-${item.id}`,
                    label: (
                        <div className="notification-row">
                            <div className="notification-content">
                                <span className="notification-subject">{item.name}</span>
                                <span className="notification-meta">{item.stock_on_hand} in stock, reorder at {item.reorder_level}</span>
                            </div>
                        </div>
                    ),
                });
            });
        }

        if (alerts.lowStockRows.length > 0 && alerts.expiryRows.length > 0) {
            items.push({ type: 'divider' });
        }

        if (alerts.expiryRows.length > 0) {
            items.push({ key: 'expiry-title', disabled: true, label: <div className="notification-title"><HistoryOutlined /> Expiry watch</div> });
            alerts.expiryRows.slice(0, 5).forEach((item) => {
                items.push({
                    key: `expiry-${item.id}`,
                    label: (
                        <div className="notification-row">
                            <div className="notification-content">
                                <span className="notification-subject">{item.name}</span>
                                <span className="notification-meta">
                                    Batch {item.batch_no || '-'} expires {formatCalendarDate(item.expires_at, brandingData?.calendar_type || 'ad', { style: 'compact' })}
                                </span>
                            </div>
                        </div>
                    ),
                });
            });
        }

        items.push({ type: 'divider' });
        items.push({
            key: 'footer',
            label: <div className="notification-tray-footer">View detailed reports</div>,
            onClick: () => goTo(appUrl('/app/reports/inventory'))
        });

        return items;
    }, [alerts]);

    function goTo(route) {
        if (!route || route === pathname) return;
        window.history.pushState({}, '', route);
        setPathname(currentAppPath());
    }

    function navigate({ key }) { goTo(routesByKey[key]); }

    function handleNotificationClick({ key }) {
        if (key.startsWith('low-stock')) goTo(appUrl('/app/reports/low-stock'));
        if (key.startsWith('expiry')) goTo(appUrl('/app/reports/expiry'));
    }

    function logout() {
        http.post(appUrl('/logout')).finally(() => {
            window.location.href = appUrl('/login');
        });
    }

    const profileItems = [
        { key: 'profile', label: 'Profile Settings', icon: <UserSwitchOutlined />, onClick: () => { goTo(appUrl('/app/settings')); } },
        { type: 'divider' },
        { key: 'logout', label: 'Sign Out', danger: true, onClick: logout },
    ];
    const timeLabel = formatCalendarDate(now, brandingData?.calendar_type || 'ad', {
        style: brandingData?.calendar_type === 'bs' ? 'medium-long' : 'medium',
        includeWeekday: true,
        includeTime: true,
        includeSeconds: false,
        includeEra: false,
    });

    return (
        <Layout className={`app-shell app-shell-${layout}`}>
            {layout === 'vertical' && (
                <Sider
                    width={260}
                    collapsed={collapsed}
                    className="app-sidebar"
                    breakpoint="lg"
                    collapsedWidth={72}
                    trigger={null}
                >
                    <div className="main-sidebar-header">
                        <a href={appUrl('/app')} className="header-logo">
                            {logo ? <img src={logo} alt={appName} className="brand-logo" /> : <div className="brand-mark"><SafetyCertificateOutlined /></div>}
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
                            className="app-menu"
                        />
                    </div>
                </Sider>

            )}
            <Layout>
                <Header className="app-topbar">
                    <Space className="header-content-left">
                        {layout === 'vertical' && (
                            <Button
                                type="text"
                                className="sidebar-toggle-btn"
                                icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                                onClick={() => setCollapsed((value) => !value)}
                            />
                        )}
                        <div className="search-wrapper" onClick={() => setSearchVisible(true)}>
                            <SearchOutlined className="search-icon-inner" />
                            <input
                                className="search-input-sleek"
                                placeholder="Search modules, invoices or products..."
                                onClick={() => setSearchVisible(true)}
                                readOnly
                            />
                            <div className="search-kbd">
                                {isMacPlatform() ? '⌘K' : 'Ctrl K'}
                            </div>
                        </div>
                    </Space>
                    <Space className="header-content-right" size={16}>
                        <div className="topbar-clock">
                            <ClockCircleOutlined />
                            <span>{timeLabel}</span>
                        </div>
                        <Dropdown
                            menu={{ items: notificationItems, onClick: handleNotificationClick }}
                            placement="bottomRight"
                            trigger={['click']}
                            classNames={{ root: 'notification-dropdown' }}
                        >
                            <Badge count={alerts.count} size="small" overflowCount={99}>
                                <Button type="text" shape="circle" icon={<BellOutlined />} />
                            </Badge>
                        </Dropdown>

                        <Dropdown menu={{ items: profileItems }} trigger={['click']} placement="bottomRight">
                            <div className="user-profile-trigger">
                                <Avatar size="small" style={{ backgroundColor: colorPrimary }}>{user?.name?.slice(0, 1).toUpperCase()}</Avatar>
                                <span className="user-name-label">{user?.name}</span>
                                <DownOutlined style={{ fontSize: 10, opacity: 0.5 }} />
                            </div>
                        </Dropdown>
                    </Space>
                </Header>
                <Content className="app-content">
                    <Suspense fallback={<div className="screen-center"><Spin /></div>}>
                        <ActivePage key={activeKey} />
                    </Suspense>
                </Content>
                <div className="app-footer-premium">
                    <div className="footer-left">
                        <span className="footer-copyright">© {new Date().getFullYear()} <strong>PharmaNP</strong></span>
                        <span className="footer-sep">|</span>
                        <span className="footer-credit">Developed with excellence by <strong>Pratik Bhujel</strong></span>
                    </div>
                    <div className="footer-right">
                        <Space size="middle">
                            <Typography.Text type="secondary" size="small">v1.0.0 Stable</Typography.Text>
                            <span className="footer-contact">prateekbhujelpb@gmail.com</span>
                        </Space>
                    </div>
                </div>
            </Layout>
            <GlobalSearch
                visible={searchVisible}
                onCancel={() => setSearchVisible(false)}
                onNavigate={(route) => {
                    if (route.startsWith('/')) {
                        goTo(appUrl(route));
                    } else {
                        if (route === 'dashboard') goTo(appUrl('/app'));
                        if (route === 'products') goTo(appUrl('/app/inventory/products'));
                        if (route === 'sales') goTo(appUrl('/app/sales'));
                        if (route === 'users') goTo(appUrl('/app/administration/users'));
                        if (route === 'settings') goTo(appUrl('/app/settings'));
                    }
                }}
            />
        </Layout>
    );
}
