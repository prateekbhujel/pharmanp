import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const SalesPage = lazyNamed(() => import('./SalesPage'), 'SalesPage');

export const salesFrontendModule = {
    key: 'sales',
    label: 'Sales',
    root: appUrl('/app/sales'),
    component: SalesPage,
};

export const salesRoutes = appRoutes([
    '/app/sales',
    '/app/sales/pos',
    '/app/sales/invoices',
    '/app/sales/returns',
    '/app/sales/expiry-returns',
], SalesPage);
