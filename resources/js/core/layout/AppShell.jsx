import React, { Suspense, useEffect, useMemo, useState } from 'react';
import { Avatar, Badge, Breadcrumb, Button, Drawer, Dropdown, Layout, Menu, Space, Spin, Typography } from 'antd';
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
import { routes } from '../modules/routeRegistry';

const { Header, Sider, Content } = Layout;

const SIDEBAR_COLLAPSE_STORAGE_KEY = 'pharmanp-sidebar-collapsed';
const ALERT_DISMISS_STORAGE_KEY = 'pharmanp-dismissed-alert-signature';

function buildAlertSignature(lowStockRows = [], expiryRows = []) {
    return [
        ...lowStockRows.map((item) => [
            'low',
            item.id,
            item.stock_on_hand,
            item.reorder_level,
        ].join(':')),
        ...expiryRows.map((item) => [
            'expiry',
            item.id,
            item.batch_no || '',
            item.expires_at || '',
            item.quantity_available || '',
        ].join(':')),
    ].sort().join('|');
}

function currentAppPath() {
    return window.location.pathname.replace(/\/$/, '') || appUrl('/app');
}

export function AppShell() {
    const { user: authUser, reload: reloadAuth } = useAuth();
    const { branding: brandingData, loading: brandingLoading } = useBranding();
    const { colorPrimary } = useTheme();

    const [collapsed, setCollapsed] = useState(true);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
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
    const product = brandingData?.product || {};
    const productName = product.name || 'PharmaNP';
    const developerName = product.developer_name || 'Pratik Bhujel';
    const developerEmail = product.developer_email || 'prateekbhujelpb@gmail.com';
    const productVersion = [product.version_label || 'dev', product.release_channel].filter(Boolean).join(' ');
    const logo = brandingData?.sidebar_logo_url || brandingData?.logo_url || brandingData?.app_icon_url;
    const user = authUser;

    const activeKey = useMemo(() => {
        if (routes[pathname]) return pathname;
        const sortedRoutes = Object.keys(routes).sort((a, b) => b.length - a.length);
        const match = sortedRoutes.find((route) => pathname.startsWith(route));
        return match || appUrl('/app');
    }, [pathname]);
    const ActivePage = routes[activeKey] || routes[appUrl('/app')];

    const { items: menuItems, routesByKey, selectedMenuKey, openKeys, breadcrumbs } = useMemo(() => {
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
            { key: register('dashboard', appUrl('/app')), icon: <DashboardOutlined />, label: 'Dashboard', show: can(user, 'dashboard.view') },

            {
                key: 'inventory',
                icon: <MedicineBoxOutlined />,
                label: 'Inventory',
                show: canInventory,
                children: [
                    child('inventory-product', 'Products', appUrl('/app/inventory/products')),
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
                    child('purchase-expiry-returns', 'Purchase Expiry Returns', appUrl('/app/purchases/expiry-returns')),
                    child('purchase-payment-out', 'Payment Out', appUrl('/app/accounting/payments?direction=out')),
                    child('purchase-aging', 'Supplier Aging', appUrl('/app/reports/supplier-aging')),
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
                    child('sales-expiry-returns', 'Sales Expiry Return', appUrl('/app/sales/expiry-returns')),
                    child('sales-payment-in', 'Payment In', appUrl('/app/accounting/payments?direction=in')),
                    child('sales-aging', 'Customer Aging', appUrl('/app/reports/customer-aging')),
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
                    child('accounting-profit-loss', 'Profit & Loss', appUrl('/app/accounting/profit-loss')),
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
                    child('field-force-targets', 'Targets', appUrl('/app/administration/targets')),
                ],
            },
            { key: register('reports', appUrl('/app/reports')), icon: <BarChartOutlined />, label: 'Reports', show: canReports },
            {
                key: 'admin-master',
                icon: <ContainerOutlined />,
                label: 'Master',
                show: canSetup,
                children: [
                    child('admin-users', 'Users', appUrl('/app/administration/users')),
                    child('admin-roles', 'Roles & Permissions', appUrl('/app/administration/roles')),
                    child('admin-employees', 'Employees', appUrl('/app/administration/employees')),
                    child('admin-branches', 'Branches', appUrl('/app/administration/branches')),
                    child('admin-areas', 'Areas', appUrl('/app/administration/areas')),
                    child('admin-divisions', 'Divisions', appUrl('/app/administration/divisions')),
                    child('admin-targets', 'Targets', appUrl('/app/administration/targets')),
                    child('admin-payment-modes', 'Payment Modes', appUrl('/app/administration/payment-modes')),
                    child('admin-party-types', 'Party Types', appUrl('/app/administration/party-types')),
                    child('admin-supplier-types', 'Supplier Types', appUrl('/app/administration/supplier-types')),
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

        const selectedParent = flatItems.find((item) => item.children?.some((childItem) => childItem.key === selectedKey));
        const selectedChild = selectedParent?.children?.find((childItem) => childItem.key === selectedKey);
        const selectedRoot = flatItems.find((item) => item.key === selectedKey);
        const pageBreadcrumbs = selectedParent && selectedChild
            ? [
                { key: selectedParent.key, title: selectedParent.label },
                { key: selectedChild.key, title: selectedChild.label },
            ]
            : selectedRoot
                ? [{ key: selectedRoot.key, title: selectedRoot.label }]
                : [];

        return {
            items: flatItems,
            routesByKey: routeMap,
            selectedMenuKey: selectedKey,
            openKeys: openKey ? [openKey] : [],
            breadcrumbs: pageBreadcrumbs,
        };
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
                const signature = buildAlertSignature(lowStockRows, expiryRows);
                const dismissedSignature = window.localStorage.getItem(ALERT_DISMISS_STORAGE_KEY);
                const dismissed = signature && dismissedSignature === signature;
                const count = dismissed
                    ? 0
                    : Number(stats.low_stock || lowStockRows.length || 0)
                        + Number(stats.expiring_batches || expiryRows.length || 0);
                setAlerts({
                    loading: false,
                    lowStockRows: dismissed ? [] : lowStockRows,
                    expiryRows: dismissed ? [] : expiryRows,
                    count,
                    signature,
                });
            })
            .catch(() => {
                if (active) setAlerts({ loading: false, lowStockRows: [], expiryRows: [], count: 0, signature: '' });
            });
        return () => { active = false; };
    }, []);

    const notificationItems = useMemo(() => {
        if (!alerts.count) {
            return [{ key: 'empty', disabled: true, label: <div className="notification-empty">No stock alerts right now</div> }];
        }
        const items = [];

        items.push({
            key: 'header',
            disabled: true,
            label: (
                <div className="notification-tray-header">
                    <strong>Notifications</strong>
                    <span>{alerts.count} active</span>
                </div>
            )
        });
        items.push({
            key: 'mark-read',
            label: <div className="notification-tray-action">Mark all as read</div>,
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
        });

        return items;
    }, [alerts, brandingData?.calendar_type]);

    function goTo(route) {
        if (!route || route === pathname) return;
        window.history.pushState({}, '', route);
        setPathname(currentAppPath());
    }

    function navigate({ key }) {
        goTo(routesByKey[key]);
        if (isCompactViewport) {
            setMobileMenuOpen(false);
        }
    }

    function handleNotificationClick({ key }) {
        if (key === 'mark-read') {
            if (alerts.signature) {
                window.localStorage.setItem(ALERT_DISMISS_STORAGE_KEY, alerts.signature);
            }
            setAlerts((current) => ({
                ...current,
                loading: false,
                lowStockRows: [],
                expiryRows: [],
                count: 0,
            }));
            return;
        }
        if (key === 'footer') {
            goTo(appUrl('/app/reports/inventory'));
            return;
        }
        if (key.startsWith('low-stock')) goTo(appUrl('/app/reports/low-stock'));
        if (key.startsWith('expiry')) goTo(appUrl('/app/reports/expiry'));
    }

    function logout() {
        http.post(appUrl('/logout')).finally(() => {
            window.location.href = appUrl('/login');
        });
    }

    function stopImpersonating() {
        http.post(endpoints.stopImpersonation).finally(() => {
            reloadAuth?.();
            window.location.href = appUrl('/app/administration/users');
        });
    }

    const profileItems = [
        ...(user?.impersonating ? [
            { key: 'stop-impersonation', label: 'Return to Admin', icon: <UserSwitchOutlined />, onClick: stopImpersonating },
            { type: 'divider' },
        ] : []),
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
    const isDashboardBreadcrumb = breadcrumbs.length === 1 && breadcrumbs[0].key === 'dashboard';
    const shouldShowBreadcrumbs = brandingData?.show_breadcrumbs !== false && !isDashboardBreadcrumb;
    const breadcrumbItems = [
        ...(!isDashboardBreadcrumb ? [{
            title: (
                <button type="button" className="breadcrumb-link" onClick={() => goTo(appUrl('/app'))}>
                    Dashboard
                </button>
            ),
        }] : []),
        ...breadcrumbs.map((item, index) => ({
            title: index === breadcrumbs.length - 1
                ? <span className="breadcrumb-current">{item.title}</span>
                : <span className="breadcrumb-section">{item.title}</span>,
        })),
    ];

    return (
        <Layout className={`app-shell app-shell-${layout}`}>
            {layout === 'vertical' && !isCompactViewport && (
                <Sider
                    width={260}
                    collapsed={collapsed}
                    className="app-sidebar"
                    breakpoint="lg"
                    collapsedWidth={isCompactViewport ? 0 : 72}
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
            {layout === 'vertical' && isCompactViewport && (
                <Drawer
                    className="mobile-nav-drawer"
                    placement="left"
                    width={280}
                    open={mobileMenuOpen}
                    onClose={() => setMobileMenuOpen(false)}
                    destroyOnHidden
                    title={(
                        <a href={appUrl('/app')} className="header-logo">
                            {logo ? <img src={logo} alt={appName} className="brand-logo" /> : <div className="brand-mark"><SafetyCertificateOutlined /></div>}
                            <strong>{appName}</strong>
                        </a>
                    )}
                >
                    <div className="main-sidebar mobile-sidebar-menu">
                        <Menu
                            mode="inline"
                            selectedKeys={[selectedMenuKey]}
                            defaultOpenKeys={openKeys}
                            items={menuItems}
                            onClick={navigate}
                            className="app-menu"
                        />
                    </div>
                </Drawer>
            )}
            <Layout>
                <Header className="app-topbar">
                    <Space className="header-content-left">
                        {layout === 'vertical' && (
                            <Button
                                type="text"
                                className="sidebar-toggle-btn"
                                icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                                onClick={() => {
                                    if (isCompactViewport) {
                                        setMobileMenuOpen(true);
                                        return;
                                    }

                                    setCollapsed((value) => !value);
                                }}
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
                    {shouldShowBreadcrumbs && breadcrumbItems.length > 0 && (
                        <Breadcrumb className="app-breadcrumbs" items={breadcrumbItems} />
                    )}
                    <Suspense fallback={<div className="screen-center"><Spin /></div>}>
                        <ActivePage key={activeKey} />
                    </Suspense>
                </Content>
                <div className="app-footer-premium">
                    <div className="footer-left">
                        <span className="footer-copyright">© {new Date().getFullYear()} <strong>{productName}</strong></span>
                        <span className="footer-sep">|</span>
                        <span className="footer-credit">Developed with excellence by <strong>{developerName}</strong></span>
                    </div>
                    <div className="footer-right">
                        <Space size="middle">
                            <Typography.Text type="secondary" size="small">{productVersion}</Typography.Text>
                            <span className="footer-contact">{developerEmail}</span>
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
