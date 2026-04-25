import React, { Suspense, useMemo, useState } from 'react';
import { Avatar, Button, Layout, Menu, Space, Spin, Typography } from 'antd';
import {
    BarChartOutlined,
    CloudUploadOutlined,
    DashboardOutlined,
    DollarOutlined,
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

    const menuItems = useMemo(() => ([
        { key: appUrl('/app'), icon: <DashboardOutlined />, label: 'Dashboard', show: can(user, 'dashboard.view') },
        { key: appUrl('/app/onboarding'), icon: <SafetyCertificateOutlined />, label: 'First Run Guide', show: user?.is_owner || can(user, 'setup.manage') },
        { key: appUrl('/app/inventory/products'), icon: <MedicineBoxOutlined />, label: 'Inventory', show: can(user, 'inventory.products.view') },
        { key: appUrl('/app/purchases'), icon: <ShopOutlined />, label: 'Purchase', show: can(user, 'purchase.entries.view') || can(user, 'purchase.entries.create') },
        { key: appUrl('/app/sales/pos'), icon: <ShoppingCartOutlined />, label: 'Sales / POS', show: can(user, 'sales.invoices.view') || can(user, 'sales.pos.use') },
        { key: appUrl('/app/parties'), icon: <TeamOutlined />, label: 'Parties', show: can(user, 'party.suppliers.view') || can(user, 'party.customers.view') || user?.is_owner },
        { key: appUrl('/app/accounting'), icon: <DollarOutlined />, label: 'Accounting', show: can(user, 'accounting.vouchers.view') || can(user, 'accounting.books.view') },
        { key: appUrl('/app/mr/performance'), icon: <UserSwitchOutlined />, label: 'MR Tracking', show: can(user, 'mr.view') || can(user, 'mr.visits.manage') },
        { key: appUrl('/app/imports'), icon: <CloudUploadOutlined />, label: 'Imports', show: can(user, 'imports.preview') || can(user, 'imports.commit') },
        { key: appUrl('/app/reports'), icon: <BarChartOutlined />, label: 'Reports', show: can(user, 'reports.view') },
        { key: appUrl('/app/settings'), icon: <SettingOutlined />, label: 'Setup', show: can(user, 'settings.manage') || can(user, 'users.manage') || can(user, 'roles.manage') || user?.is_owner },
        { key: appUrl('/app/system/update-check'), icon: <SyncOutlined />, label: 'System', show: can(user, 'system.update.view') || user?.is_owner },
    ].filter((item) => item.show)), [user]);

    function navigate({ key }) {
        window.history.pushState({}, '', key);
        window.dispatchEvent(new PopStateEvent('popstate'));
        window.location.href = key;
    }

    function logout() {
        http.post(appUrl('/logout')).finally(() => {
            window.location.href = appUrl('/login');
        });
    }

    return (
        <Layout className={`app-shell app-shell-${layout}`}>
            {layout === 'vertical' && (
            <Sider width={260} collapsed={collapsed} className="app-sidebar" breakpoint="lg" collapsedWidth={0}>
                <div className="brand">
                    {logo ? <img src={logo} alt={appName} className="brand-logo" /> : <div className="brand-mark"><SafetyCertificateOutlined /></div>}
                    {!collapsed && (
                        <div>
                            <strong>{appName}</strong>
                            <span>Pharmacy ERP</span>
                        </div>
                    )}
                </div>
                <Menu mode="inline" selectedKeys={[activeKey]} items={menuItems} onClick={navigate} />
            </Sider>
            )}
            <Layout>
                <Header className="app-topbar">
                    <Space>
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
                        {layout === 'vertical' && <TeamOutlined />}
                        <Typography.Text strong>{user?.name}</Typography.Text>
                    </Space>
                    <Space>
                        <Avatar>{user?.name?.slice(0, 1)}</Avatar>
                        <Button onClick={logout}>Logout</Button>
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
