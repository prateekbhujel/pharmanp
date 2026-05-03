import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const SettingsPage = lazyNamed(() => import('./SettingsPage'), 'SettingsPage');
export const UsersPage = lazyNamed(() => import('./UsersPage'), 'UsersPage');
export const RolesPage = lazyNamed(() => import('./RolesPage'), 'RolesPage');
export const DataLookupPage = lazyNamed(() => import('./DataLookupPage'), 'DataLookupPage');
export const SetupStructurePage = lazyNamed(() => import('./SetupStructurePage'), 'SetupStructurePage');

export const usersFrontendModule = {
    key: 'users',
    label: 'Users',
    routes: [appUrl('/app/administration/users')],
    component: UsersPage,
};

export const rolesFrontendModule = {
    key: 'roles',
    label: 'Role Access',
    routes: [appUrl('/app/administration/roles')],
    component: RolesPage,
};

export const dataLookupFrontendModule = {
    key: 'data-lookup',
    label: 'Master Data',
    routes: [appUrl('/app/administration/data-lookup')],
    component: DataLookupPage,
};

export const settingsFrontendModule = {
    key: 'settings',
    label: 'Settings',
    routes: [appUrl('/app/settings')],
    component: SettingsPage,
};

export const settingsRoutes = {
    ...appRoutes(['/app/administration/users'], UsersPage),
    ...appRoutes(['/app/administration/roles'], RolesPage),
    ...appRoutes([
        '/app/administration/employees',
        '/app/administration/areas',
        '/app/administration/divisions',
        '/app/administration/targets',
    ], SetupStructurePage),
    ...appRoutes([
        '/app/administration/branches',
        '/app/administration/payment-modes',
        '/app/administration/party-types',
        '/app/administration/supplier-types',
        '/app/administration/data-lookup',
    ], DataLookupPage),
    ...appRoutes(['/app/settings'], SettingsPage),
};
