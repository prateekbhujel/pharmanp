import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const PurchasesPage = lazyNamed(() => import('./PurchasesPage'), 'PurchasesPage');

export const purchaseFrontendModule = {
    key: 'purchase',
    label: 'Purchase',
    root: appUrl('/app/purchases'),
    component: PurchasesPage,
};

export const purchaseRoutes = appRoutes([
    '/app/purchases',
    '/app/purchases/entry',
    '/app/purchases/bills',
    '/app/purchases/orders',
    '/app/purchases/returns',
    '/app/purchases/expiry-returns',
], PurchasesPage);
