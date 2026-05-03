import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const AccountingPage = lazyNamed(() => import('./AccountingPage'), 'AccountingPage');
export const ReportsPage = lazyNamed(() => import('../reports/ReportsPage'), 'ReportsPage');

export const accountingFrontendModule = {
    key: 'accounting',
    label: 'Accounting & Finance',
    root: appUrl('/app/accounting'),
    component: AccountingPage,
};

export const accountingRoutes = {
    ...appRoutes([
        '/app/accounting',
        '/app/accounting/vouchers',
        '/app/accounting/payments',
        '/app/accounting/expenses',
    ], AccountingPage),
    ...appRoutes([
        '/app/accounting/day-book',
        '/app/accounting/cash-book',
        '/app/accounting/bank-book',
        '/app/accounting/ledgers',
        '/app/accounting/ledger',
        '/app/accounting/account-tree',
        '/app/accounting/trial-balance',
        '/app/accounting/profit-loss',
    ], ReportsPage),
};
