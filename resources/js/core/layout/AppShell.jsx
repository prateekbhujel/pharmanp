import React, { Suspense, useMemo } from 'react';
import { Avatar, Button, Layout, Menu, Space, Spin, Typography } from 'antd';
import {
    BarChartOutlined,
    CloudUploadOutlined,
    DashboardOutlined,
    MedicineBoxOutlined,
    SafetyCertificateOutlined,
    ShoppingCartOutlined,
    SyncOutlined,
    TeamOutlined,
} from '@ant-design/icons';
import { http } from '../api/http';
import { useAuth } from '../auth/AuthProvider';

const { Header, Sider, Content } = Layout;

const DashboardPage = React.lazy(() => import('../../modules/dashboard/DashboardPage').then((module) => ({ default: module.DashboardPage })));
const ProductsPage = React.lazy(() => import('../../modules/inventory/ProductsPage').then((module) => ({ default: module.ProductsPage })));
const SalesPage = React.lazy(() => import('../../modules/sales/SalesPage').then((module) => ({ default: module.SalesPage })));
const ImportWizardPage = React.lazy(() => import('../../modules/imports/ImportWizardPage').then((module) => ({ default: module.ImportWizardPage })));
const SystemUpdatePage = React.lazy(() => import('../../modules/system/SystemUpdatePage').then((module) => ({ default: module.SystemUpdatePage })));

const routes = {
    '/app': DashboardPage,
    '/app/inventory/products': ProductsPage,
    '/app/sales/pos': SalesPage,
    '/app/imports': ImportWizardPage,
    '/app/reports': DashboardPage,
    '/app/system/update-check': SystemUpdatePage,
};

export function AppShell() {
    const { user } = useAuth();
    const pathname = window.location.pathname.replace(/\/$/, '') || '/app';
    const activeKey = routes[pathname] ? pathname : '/app';
    const ActivePage = routes[activeKey] || DashboardPage;

    const menuItems = useMemo(() => [
        { key: '/app', icon: <DashboardOutlined />, label: 'Dashboard' },
        { key: '/app/inventory/products', icon: <MedicineBoxOutlined />, label: 'Products' },
        { key: '/app/sales/pos', icon: <ShoppingCartOutlined />, label: 'Sales / POS' },
        { key: '/app/imports', icon: <CloudUploadOutlined />, label: 'Imports' },
        { key: '/app/reports', icon: <BarChartOutlined />, label: 'Reports' },
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
        <Layout className="app-shell">
            <Sider width={260} className="app-sidebar" breakpoint="lg" collapsedWidth={0}>
                <div className="brand">
                    <div className="brand-mark"><SafetyCertificateOutlined /></div>
                    <div>
                        <strong>PharmaNP</strong>
                        <span>Pharmacy ERP</span>
                    </div>
                </div>
                <Menu mode="inline" selectedKeys={[activeKey]} items={menuItems} onClick={navigate} />
            </Sider>
            <Layout>
                <Header className="app-topbar">
                    <Space>
                        <TeamOutlined />
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
