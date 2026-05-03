import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const DashboardPage = lazyNamed(() => import('./DashboardPage'), 'DashboardPage');

export const dashboardFrontendModule = {
    key: 'dashboard',
    label: 'Dashboard',
    routes: [appUrl('/app')],
    component: DashboardPage,
};

export const dashboardRoutes = appRoutes(['/app'], DashboardPage);
