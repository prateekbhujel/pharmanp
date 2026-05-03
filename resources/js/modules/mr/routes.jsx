import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const MrTrackingPage = lazyNamed(() => import('./MrTrackingPage'), 'MrTrackingPage');

export const fieldForceFrontendModule = {
    key: 'field-force',
    label: 'Field Force',
    root: appUrl('/app/field-force'),
    component: MrTrackingPage,
};

export const fieldForceRoutes = appRoutes([
    '/app/field-force/dashboard',
    '/app/field-force/performance',
    '/app/field-force/representatives',
    '/app/field-force/visits',
    '/app/field-force/branches',
], MrTrackingPage);
