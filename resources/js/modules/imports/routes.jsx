import { appUrl } from '../../core/utils/url';
import { appRoutes, lazyNamed } from '../../core/modules/moduleRouteHelpers';

export const ImportWizardPage = lazyNamed(() => import('./ImportWizardPage'), 'ImportWizardPage');
export const OcrImportPage = lazyNamed(() => import('./OcrImportPage'), 'OcrImportPage');

export const importsFrontendModule = {
    key: 'imports',
    label: 'Import Center',
    routes: [appUrl('/app/imports')],
    component: ImportWizardPage,
};

export const ocrFrontendModule = {
    key: 'ocr',
    label: 'OCR Purchase',
    routes: [appUrl('/app/sales/ocr')],
    component: OcrImportPage,
};

export const importRoutes = {
    ...appRoutes(['/app/imports'], ImportWizardPage),
    ...appRoutes(['/app/sales/ocr'], OcrImportPage),
};
