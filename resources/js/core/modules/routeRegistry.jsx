import { accountingFrontendModule, accountingRoutes } from '../../modules/accounting/routes';
import { dashboardFrontendModule, dashboardRoutes } from '../../modules/dashboard/routes';
import { developerFrontendModule, developerRoutes } from '../../modules/developer/routes';
import { importsFrontendModule, importRoutes, ocrFrontendModule } from '../../modules/imports/routes';
import { inventoryFrontendModule, inventoryRoutes } from '../../modules/inventory/routes';
import { fieldForceFrontendModule, fieldForceRoutes } from '../../modules/mr/routes';
import { partyFrontendModule, partyRoutes } from '../../modules/party/routes';
import { purchaseFrontendModule, purchaseRoutes } from '../../modules/purchases/routes';
import { reportsFrontendModule, reportRoutes } from '../../modules/reports/routes';
import { salesFrontendModule, salesRoutes } from '../../modules/sales/routes';
import {
    dataLookupFrontendModule,
    rolesFrontendModule,
    settingsFrontendModule,
    settingsRoutes,
    usersFrontendModule,
} from '../../modules/settings/routes';

export const frontendModules = [
    dashboardFrontendModule,
    inventoryFrontendModule,
    purchaseFrontendModule,
    salesFrontendModule,
    partyFrontendModule,
    accountingFrontendModule,
    fieldForceFrontendModule,
    importsFrontendModule,
    ocrFrontendModule,
    reportsFrontendModule,
    usersFrontendModule,
    rolesFrontendModule,
    dataLookupFrontendModule,
    settingsFrontendModule,
    developerFrontendModule,
];

export const routes = {
    ...dashboardRoutes,
    ...inventoryRoutes,
    ...purchaseRoutes,
    ...salesRoutes,
    ...importRoutes,
    ...fieldForceRoutes,
    ...accountingRoutes,
    ...partyRoutes,
    ...reportRoutes,
    ...settingsRoutes,
    ...developerRoutes,
};
