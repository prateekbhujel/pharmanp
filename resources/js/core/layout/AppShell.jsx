import React, { Suspense, useEffect, useMemo, useState } from 'react';
import { Avatar, Badge, Breadcrumb, Button, Drawer, Dropdown, Layout, Menu, Space, Spin, Typography } from 'antd';
import {
    BellOutlined,
    ClockCircleOutlined,
    DownOutlined,
    MenuFoldOutlined,
    MenuUnfoldOutlined,
    SettingOutlined,
    SafetyCertificateOutlined,
    UserSwitchOutlined,
    SearchOutlined,
} from '@ant-design/icons';
import { http } from '../api/http';
import { endpoints } from '../api/endpoints';
import { useAuth } from '../auth/AuthProvider';
import { isMacPlatform } from '../utils/platform';
import { appUrl } from '../utils/url';
import { useTheme } from '../theme/ThemeContext';
import { GlobalSearch } from '../components/GlobalSearch';
import { useBranding } from '../context/BrandingContext';
import { formatCalendarDate } from '../utils/calendar';
import { routes } from '../modules/routeRegistry';
import { buildNavigationModel } from '../navigation/navigationCatalog';
import { useStockAlerts } from './useStockAlerts';

const { Header, Sider, Content } = Layout;

const SIDEBAR_COLLAPSE_STORAGE_KEY = 'pharmanp-sidebar-collapsed';

function currentAppPath() {
    const path = window.location.pathname.replace(/\/$/, '') || appUrl('/app');

    return `${path}${window.location.search || ''}`;
}

export function AppShell() {
    const { user: authUser, reload: reloadAuth } = useAuth();
    const { branding: brandingData, loading: brandingLoading } = useBranding();
    const { colorPrimary } = useTheme();

    const [collapsed, setCollapsed] = useState(true);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [isCompactViewport, setIsCompactViewport] = useState(false);
    const [pathname, setPathname] = useState(currentAppPath);
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
        const routePath = pathname.split('?')[0];
        if (routes[routePath]) return routePath;
        const sortedRoutes = Object.keys(routes).sort((a, b) => b.length - a.length);
        const match = sortedRoutes.find((route) => routePath.startsWith(route));
        return match || appUrl('/app');
    }, [pathname]);
    const ActivePage = routes[activeKey] || routes[appUrl('/app')];

    const { items: menuItems, routesByKey, selectedMenuKey, openKeys, breadcrumbs, searchItems } = useMemo(
        () => buildNavigationModel(user, pathname),
        [pathname, user],
    );


    useEffect(() => {
        function syncPath() { setPathname(currentAppPath()); }
        window.addEventListener('popstate', syncPath);
        return () => window.removeEventListener('popstate', syncPath);
    }, []);

    function goTo(route) {
        if (!route || route === pathname) return;
        window.history.pushState({}, '', route);
        setPathname(currentAppPath());
    }

    useEffect(() => {
        function handleDocumentClick(event) {
            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
            ) {
                return;
            }

            const anchor = event.target?.closest?.('a[href]');
            if (!anchor || anchor.target || anchor.hasAttribute('download')) {
                return;
            }

            const url = new URL(anchor.href, window.location.href);
            const appRoot = appUrl('/app');

            if (url.origin !== window.location.origin || !url.pathname.startsWith(appRoot)) {
                return;
            }

            event.preventDefault();
            goTo(`${url.pathname}${url.search || ''}${url.hash || ''}`);
            if (isCompactViewport) {
                setMobileMenuOpen(false);
            }
        }

        document.addEventListener('click', handleDocumentClick);

        return () => document.removeEventListener('click', handleDocumentClick);
    }, [isCompactViewport, pathname]);

    const { alerts, notificationItems, handleNotificationClick } = useStockAlerts({
        calendarType: brandingData?.calendar_type || 'ad',
        navigate: goTo,
    });

    function navigate({ key }) {
        goTo(routesByKey[key]);
        if (isCompactViewport) {
            setMobileMenuOpen(false);
        }
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
        { key: 'profile', label: 'Profile Settings', icon: <SettingOutlined />, onClick: () => { goTo(appUrl('/app/settings')); } },
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
                defaultItems={searchItems}
                onNavigate={(route) => {
                    const targetRoute = route.startsWith('/') ? appUrl(route) : routesByKey[route];
                    if (targetRoute) goTo(targetRoute);
                }}
            />
        </Layout>
    );
}
