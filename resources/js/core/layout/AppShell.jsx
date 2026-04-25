import React, { Suspense, useMemo, useState } from 'react';
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
} from '@ant-design/icons';
import { http } from '../api/http';
import { useAuth } from '../auth/AuthProvider';
import { can } from '../utils/permissions';
import { appUrl } from '../utils/url';

const { Header, Sider, Content } = Layout;

const DashboardPage = React.lazy(() => import('../../modules/dashboard/DashboardPage').then((module) => ({ default: module.DashboardPage })));
const ProductsPage = React.lazy(() => import('../../modules/inventory/ProductsPage').then((module) => ({ default: module.ProductsPage })));
const SalesPage = React.lazy(() => import('../../modules/sales/SalesPage').then((module) => ({ default: module.SalesPage })));
const ImportWizardPage = React.lazy(() => import('../../modules/imports/ImportWizardPage').then((module) => ({ default: module.ImportWizardPage })));
const SystemUpdatePage = React.lazy(() => import('../../modules/system/SystemUpdatePage').then((module) => ({ default: module.SystemUpdatePage })));
const OnboardingPage = React.lazy(() => import('../../modules/onboarding/OnboardingPage').then((module) => ({ default: module.OnboardingPage })));
const MrPerformancePage = React.lazy(() => import('../../modules/mr/MrPerformancePage').then((module) => ({ default: module.MrPerformancePage })));
const SettingsPage = React.lazy(() => import('../../modules/settings/SettingsPage').then((module) => ({ default: module.SettingsPage })));
const PurchasesPage = React.lazy(() => import('../../modules/purchases/PurchasesPage').then((module) => ({ default: module.PurchasesPage })));
const AccountingPage = React.lazy(() => import('../../modules/accounting/AccountingPage').then((module) => ({ default: module.AccountingPage })));
const PartiesPage = React.lazy(() => import('../../modules/party/PartiesPage').then((module) => ({ default: module.PartiesPage })));
const ReportsPage = React.lazy(() => import('../../modules/reports/ReportsPage').then((module) => ({ default: module.ReportsPage })));

const routes = {
    [appUrl('/app')]: DashboardPage,
    [appUrl('/app/onboarding')]: OnboardingPage,
    [appUrl('/app/inventory/products')]: ProductsPage,
    [appUrl('/app/purchases')]: PurchasesPage,
    [appUrl('/app/sales/pos')]: SalesPage,
    [appUrl('/app/parties')]: PartiesPage,
    [appUrl('/app/accounting')]: AccountingPage,
    [appUrl('/app/mr/performance')]: MrPerformancePage,
    [appUrl('/app/imports')]: ImportWizardPage,
    [appUrl('/app/reports')]: ReportsPage,
    [appUrl('/app/settings')]: SettingsPage,
    [appUrl('/app/system/update-check')]: SystemUpdatePage,
};

export function AppShell() {
    const { user, branding } = useAuth();
    const [collapsed, setCollapsed] = useState(Boolean(branding?.sidebar_default_collapsed));
    const pathname = window.location.pathname.replace(/\/$/, '') || appUrl('/app');
    const activeKey = routes[pathname] ? pathname : appUrl('/app');
    const ActivePage = routes[activeKey] || DashboardPage;
    const layout = branding?.layout || 'vertical';
    const appName = branding?.app_name || 'PharmaNP';
    const logo = branding?.sidebar_logo_url || branding?.logo_url || branding?.app_icon_url;

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
                    child('inventory-company', 'Company', appUrl('/app/inventory/products')),
                    child('inventory-unit', 'Unit', appUrl('/app/inventory/products')),
                    child('inventory-product', 'Product', appUrl('/app/inventory/products')),
                    child('inventory-batches', 'Batches', appUrl('/app/inventory/products')),
                    child('inventory-adjustment', 'Stock Adjustment', appUrl('/app/inventory/products')),
                    child('inventory-movement', 'Case Movement', appUrl('/app/inventory/products')),
                ],
            },
            {
                key: 'purchase',
                icon: <ShopOutlined />,
                label: 'Purchase',
                show: canPurchase,
                children: [
                    child('purchase-supplier', 'Supplier', appUrl('/app/parties')),
                    child('purchase-bills', 'Purchase Bills', appUrl('/app/purchases')),
                    child('purchase-orders', 'Purchase Orders', appUrl('/app/purchases')),
                    child('purchase-returns', 'Purchase Returns', appUrl('/app/purchases')),
                ],
            },
            { key: register('parties', appUrl('/app/parties')), icon: <TeamOutlined />, label: 'Party Management', show: canParties },
            {
                key: 'sales',
                icon: <ShoppingCartOutlined />,
                label: 'Sales / POS',
                show: canSales,
                children: [
                    child('sales-invoices', 'Sales', appUrl('/app/sales/pos')),
                    child('sales-returns', 'Sales Return', appUrl('/app/sales/pos')),
                ],
            },
            { key: register('ocr', appUrl('/app/imports')), icon: <CloudUploadOutlined />, label: 'OCR', show: can(user, 'purchase.entries.create') },
            {
                key: 'accounting',
                icon: <DollarOutlined />,
                label: 'Accounting & Finance',
                show: canAccounting,
                children: [
                    child('ledger', 'Ledger', appUrl('/app/accounting')),
                    child('day-book', 'Day Book', appUrl('/app/accounting')),
                    child('account-tree', 'Account Tree', appUrl('/app/accounting')),
                    child('trial-balance', 'Trial Balance', appUrl('/app/accounting')),
                    child('cash-book', 'Cash Book', appUrl('/app/accounting')),
                    child('bank-book', 'Bank Book', appUrl('/app/accounting')),
                    child('payments', 'Payments', appUrl('/app/accounting')),
                    child('expenses', 'Expenses', appUrl('/app/accounting')),
                ],
            },
            { key: register('mr', appUrl('/app/mr/performance')), icon: <UserSwitchOutlined />, label: 'MR Tracking', show: can(user, 'mr.view') || can(user, 'mr.visits.manage') },
            {
                key: 'reports',
                icon: <BarChartOutlined />,
                label: 'Reports',
                show: canReports,
                children: [
                    child('report-low-stock', 'Low Stock', appUrl('/app/reports')),
                    child('report-expiry', 'Expiry Alert', appUrl('/app/reports')),
                    child('report-purchase', 'Purchase History', appUrl('/app/reports')),
                    child('report-sales', 'Sales Report', appUrl('/app/reports')),
                    child('report-supplier', 'Supplier Performance', appUrl('/app/reports')),
                ],
            },
            { key: 'category-admin', label: 'Administration', disabled: true, className: 'menu-category' },
            { key: register('users', appUrl('/app/settings')), icon: <TeamOutlined />, label: 'Users', show: canSetup },
            { key: register('roles', appUrl('/app/settings')), icon: <SafetyCertificateOutlined />, label: 'Role Access', show: canSetup },
            { key: register('settings', appUrl('/app/settings')), icon: <SettingOutlined />, label: 'Settings', show: canSetup },
            { key: register('onboarding', appUrl('/app/onboarding')), icon: <SafetyCertificateOutlined />, label: 'First Run Guide', show: user?.is_owner || can(user, 'setup.manage') },
            { key: register('system', appUrl('/app/system/update-check')), icon: <SyncOutlined />, label: 'System', show: can(user, 'system.update.view') || user?.is_owner },
        ].filter((item) => item.className === 'menu-category' || item.show);

        const selectedByRoute = {
            [appUrl('/app')]: 'dashboard',
            [appUrl('/app/onboarding')]: 'onboarding',
            [appUrl('/app/inventory/products')]: 'inventory-product',
            [appUrl('/app/purchases')]: 'purchase-bills',
            [appUrl('/app/sales/pos')]: 'sales-invoices',
            [appUrl('/app/parties')]: 'parties',
            [appUrl('/app/accounting')]: 'ledger',
            [appUrl('/app/mr/performance')]: 'mr',
            [appUrl('/app/imports')]: 'ocr',
            [appUrl('/app/reports')]: 'report-sales',
            [appUrl('/app/settings')]: 'settings',
            [appUrl('/app/system/update-check')]: 'system',
        };

        const selected = selectedByRoute[activeKey] || Object.entries(routeMap)
            .find(([, route]) => route === activeKey)?.[0] || 'dashboard';

        const parent = items.find((item) => item.children?.some((nested) => nested.key === selected));

        return {
            items,
            routesByKey: routeMap,
            selectedMenuKey: selected,
            openKeys: parent ? [parent.key] : [],
        };
    }, [activeKey, user]);

    function navigate({ key }) {
        const route = routesByKey[key];

        if (route) {
            window.location.href = route;
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

    return (
        <Layout className={`app-shell app-shell-${layout}`}>
            {layout === 'vertical' && (
            <Sider width={250} collapsed={collapsed} className="app-sidebar" breakpoint="lg" collapsedWidth={72} trigger={null}>
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
                                {logo ? <img src={logo} alt={appName} className="brand-logo brand-logo-topbar" /> : <SafetyCertificateOutlined />}
                                <Menu mode="horizontal" selectedKeys={[activeKey]} items={menuItems} onClick={navigate} className="topbar-menu" />
                            </>
                        )}
                    </Space>
                    <Space className="header-content-right" size={18}>
                        <Badge dot={false} count={0}>
                            <Button type="text" icon={<BellOutlined />} />
                        </Badge>
                        <Typography.Text className="welcome-text">Welcome! <strong>{user?.name}</strong></Typography.Text>
                        <Avatar>{user?.name?.slice(0, 1)}</Avatar>
                        <Dropdown menu={{ items: profileItems }} trigger={['click']}>
                            <Button type="text" icon={<DownOutlined />} />
                        </Dropdown>
                    </Space>
                </Header>
                <Content className="app-content">
                    <Suspense fallback={<div className="screen-center"><Spin /></div>}>
                        <ActivePage />
                    </Suspense>
                </Content>
            </Layout>
        </Layout>
    );
}
