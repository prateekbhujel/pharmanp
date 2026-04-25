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
    '/app': DashboardPage,
    '/app/onboarding': OnboardingPage,
    '/app/inventory/products': ProductsPage,
    '/app/purchases': PurchasesPage,
    '/app/sales/pos': SalesPage,
    '/app/parties': PartiesPage,
    '/app/accounting': AccountingPage,
    '/app/mr/performance': MrPerformancePage,
    '/app/imports': ImportWizardPage,
    '/app/reports': ReportsPage,
    '/app/settings': SettingsPage,
    '/app/system/update-check': SystemUpdatePage,
};

export function AppShell() {
    const { user, branding } = useAuth();
    const [collapsed, setCollapsed] = useState(Boolean(branding?.sidebar_default_collapsed));
    const pathname = window.location.pathname.replace(/\/$/, '') || '/app';
    const activeKey = routes[pathname] ? pathname : '/app';
    const ActivePage = routes[activeKey] || DashboardPage;
    const layout = branding?.layout || 'vertical';
    const appName = branding?.app_name || 'PharmaNP';
    const logo = branding?.sidebar_logo_url || branding?.logo_url;

    const menuItems = useMemo(() => [
        { key: '/app', icon: <DashboardOutlined />, label: 'Dashboard' },
        { key: '/app/onboarding', icon: <SafetyCertificateOutlined />, label: 'Setup Guide' },
        { key: '/app/inventory/products', icon: <MedicineBoxOutlined />, label: 'Products' },
        { key: '/app/purchases', icon: <ShopOutlined />, label: 'Purchase' },
        { key: '/app/sales/pos', icon: <ShoppingCartOutlined />, label: 'Sales / POS' },
        { key: '/app/parties', icon: <TeamOutlined />, label: 'Parties' },
        { key: '/app/accounting', icon: <DollarOutlined />, label: 'Accounting' },
        { key: '/app/mr/performance', icon: <UserSwitchOutlined />, label: 'MR Tracking' },
        { key: '/app/imports', icon: <CloudUploadOutlined />, label: 'Imports' },
        { key: '/app/reports', icon: <BarChartOutlined />, label: 'Reports' },
        { key: '/app/settings', icon: <SettingOutlined />, label: 'Settings' },
        { key: '/app/system/update-check', icon: <SyncOutlined />, label: 'System' },
    ], []);

    function navigate({ key }) {
        window.history.pushState({}, '', key);
        window.dispatchEvent(new PopStateEvent('popstate'));
        window.location.href = key;
    }

    function logout() {
        http.post('/logout').finally(() => {
            window.location.href = '/login';
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
