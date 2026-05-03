import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const ReportsPage = lazyNamed(() => import('./ReportsPage'), 'ReportsPage');

export const reportsFrontendModule = {
    key: 'reports',
    label: 'Reports',
    root: appUrl('/app/reports'),
    component: ReportsPage,
};

export const reportRoutes = appRoutes([
    '/app/reports',
    '/app/reports/inventory',
    '/app/reports/sales',
    '/app/reports/purchase',
    '/app/reports/stock',
    '/app/reports/low-stock',
    '/app/reports/expiry',
    '/app/reports/expiry-buckets',
    '/app/reports/dumping',
    '/app/reports/smart-inventory',
    '/app/reports/accounting',
    '/app/reports/profit-loss',
    '/app/reports/supplier-performance',
    '/app/reports/supplier-aging',
    '/app/reports/customer-aging',
    '/app/reports/supplier-ledger',
    '/app/reports/customer-ledger',
    '/app/reports/product-movement',
    '/app/reports/mr-performance',
    '/app/reports/mr-vs-product',
    '/app/reports/mr-vs-division',
    '/app/reports/mr-vs-sales',
    '/app/reports/company-vs-customer',
    '/app/reports/target-achievement',
], ReportsPage);
