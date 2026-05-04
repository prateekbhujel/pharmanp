import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const DeveloperGuidePage = lazyNamed(() => import('./DeveloperGuidePage'), 'DeveloperGuidePage');

export const developerFrontendModule = {
    key: 'developer-guide',
    label: 'Developer Guide',
    routes: [appUrl('/app/developer-guide')],
    component: DeveloperGuidePage,
};

export const developerRoutes = {
    ...appRoutes(['/app/developer-guide'], DeveloperGuidePage),
};
