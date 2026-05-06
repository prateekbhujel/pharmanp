import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const ProductsPage = lazyNamed(() => import('./ProductsPage'), 'ProductsPage');

export const inventoryFrontendModule = {
    key: 'inventory',
    label: 'Inventory',
    root: appUrl('/app/inventory'),
    component: ProductsPage,
};

export const inventoryRoutes = appRoutes([
    '/app/inventory/products',
    '/app/inventory/batches',
    '/app/inventory/companies',
    '/app/inventory/units',
    '/app/inventory/stock-adjustment',
    '/app/inventory/stock-ledger',
], ProductsPage);
