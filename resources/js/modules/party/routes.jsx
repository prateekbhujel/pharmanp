import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const PartiesPage = lazyNamed(() => import('./PartiesPage'), 'PartiesPage');

export const partyFrontendModule = {
    key: 'party',
    label: 'Party Management',
    root: appUrl('/app/party'),
    component: PartiesPage,
};

export const partyRoutes = appRoutes([
    '/app/party/management',
    '/app/party/suppliers',
    '/app/party/customers',
], PartiesPage);
