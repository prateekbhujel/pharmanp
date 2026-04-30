import { appUrl } from '../utils/url';

export const endpoints = {
    me: appUrl('/api/v1/me'),
    dashboard: appUrl('/api/v1/dashboard/summary'),
    search: appUrl('/api/v1/search'),
    profile: appUrl('/api/v1/profile'),
    productMeta: appUrl('/api/v1/inventory/products/meta'),
    products: appUrl('/api/v1/inventory/products'),
    productRestore: (id) => appUrl(`/api/v1/inventory/products/${id}/restore`),
    quickCompany: appUrl('/api/v1/inventory/companies/quick'),
    quickUnit: appUrl('/api/v1/inventory/units/quick'),
    quickCategory: appUrl('/api/v1/inventory/categories/quick'),
    inventoryMaster: (master) => appUrl(`/api/v1/inventory/masters/${master}`),
    inventoryMasterRestore: (master, id) => appUrl(`/api/v1/inventory/masters/${master}/${id}/restore`),
    inventoryMasterExport: (master) => appUrl(`/api/v1/exports/inventory/masters/${master}`),
    inventoryProductsExport: appUrl('/api/v1/exports/inventory/products'),
    inventoryBatchesExport: appUrl('/api/v1/exports/inventory/batches'),
    datasetExport: (dataset) => appUrl(`/api/v1/exports/${dataset}`),
    inventoryBatches: appUrl('/api/v1/inventory/batches'),
    inventoryBatchOptions: appUrl('/api/v1/inventory/batches/options'),
    stockAdjustments: appUrl('/api/v1/inventory/stock-adjustments'),
    stockMovements: appUrl('/api/v1/inventory/stock-movements'),
    suppliers: appUrl('/api/v1/suppliers'),
    supplierRestore: (id) => appUrl(`/api/v1/suppliers/${id}/restore`),
    supplierOptions: appUrl('/api/v1/suppliers/options'),
    customers: appUrl('/api/v1/customers'),
    customerRestore: (id) => appUrl(`/api/v1/customers/${id}/restore`),
    customerOptions: appUrl('/api/v1/customers/options'),
    purchaseOrders: appUrl('/api/v1/purchase/orders'),
    purchaseOrderApprove: (orderId) => appUrl(`/api/v1/purchase/orders/${orderId}/approve`),
    purchaseOrderReceive: (orderId) => appUrl(`/api/v1/purchase/orders/${orderId}/receive`),
    purchaseOrderPay: (orderId) => appUrl(`/api/v1/purchase/orders/${orderId}/pay`),
    purchases: appUrl('/api/v1/purchases'),
    purchaseReturns: appUrl('/api/v1/purchase/returns'),
    purchaseReturnPurchases: appUrl('/api/v1/purchase/returns/purchases'),
    purchaseReturnItems: (purchaseId) => appUrl(`/api/v1/purchase/returns/purchases/${purchaseId}/items`),
    purchaseReturnBatches: appUrl('/api/v1/purchase/returns/batches'),
    salesProductLookup: appUrl('/api/v1/sales/product-lookup'),
    salesInvoices: appUrl('/api/v1/sales/invoices'),
    salesInvoicePayment: (id) => appUrl(`/api/v1/sales/invoices/${id}/payment`),
    salesInvoiceItems: (id) => appUrl(`/api/v1/sales/invoices/${id}/items`),
    salesInvoiceReturns: (id) => appUrl(`/api/v1/sales/invoices/${id}/returns`),
    mrOptions: appUrl('/api/v1/mr/options'),
    mrRepresentatives: appUrl('/api/v1/mr/representatives'),
    mrVisits: appUrl('/api/v1/mr/visits'),
    mrPerformance: appUrl('/api/v1/mr/performance'),
    mrBranches: appUrl('/api/v1/mr/branches'),
    mrBranchOptions: appUrl('/api/v1/mr/branches/options'),
    mrBranchRestore: (id) => appUrl(`/api/v1/mr/branches/${id}/restore`),
    mrBranchSales: appUrl('/api/v1/mr/branch-sales'),
    vouchers: appUrl('/api/v1/accounting/vouchers'),
    reports: appUrl('/api/v1/reports'),
    reportExport: (report) => appUrl(`/api/v1/reports/${report}/export`),
    importTargets: appUrl('/api/v1/imports/targets'),
    importSample: (target) => appUrl(`/api/v1/imports/targets/${target}/sample`),
    importPreview: appUrl('/api/v1/imports/preview'),
    importConfirm: appUrl('/api/v1/imports/confirm'),
    purchaseOcrExtract: appUrl('/api/v1/imports/ocr/extract'),
    purchaseOcrDraft: appUrl('/api/v1/imports/ocr/draft-purchase'),
    featureCatalog: appUrl('/api/v1/setup/features'),
    branding: appUrl('/api/v1/setup/branding'),
    roles: appUrl('/api/v1/setup/roles'),
    users: appUrl('/api/v1/setup/users'),
    userImpersonate: (id) => appUrl(`/api/v1/setup/users/${id}/impersonate`),
    stopImpersonation: appUrl('/api/v1/setup/users/stop-impersonating'),
    setupStatus: appUrl('/setup/status'),
    setupComplete: appUrl('/setup/complete'),

    // Accounting — Expenses
    expenses: appUrl('/api/v1/accounting/expenses'),

    // Accounting — Payments
    payments: appUrl('/api/v1/accounting/payments'),
    paymentOutstandingBills: appUrl('/api/v1/accounting/payments/outstanding-bills'),

    // Sales — Returns
    salesReturns: appUrl('/api/v1/sales/returns'),
    salesReturnInvoiceOptions: appUrl('/api/v1/sales/returns/invoice-options'),
    salesReturnInvoiceItems: (invoiceId) => appUrl(`/api/v1/sales/returns/invoices/${invoiceId}/items`),

    // Customer Ledger
    customerLedger: (customerId) => appUrl(`/api/v1/customers/${customerId}/ledger`),

    // Settings — Dropdown Options
    dropdownOptions: appUrl('/api/v1/settings/dropdown-options'),

    // Settings — Admin
    settingsAdmin: appUrl('/api/v1/settings/admin'),
    settingsTestMail: appUrl('/api/v1/settings/admin/test-mail'),

    // Settings — Party Types
    partyTypes: appUrl('/api/v1/settings/party-types'),

    // Settings — Supplier Types
    supplierTypes: appUrl('/api/v1/settings/supplier-types'),
    
    // Settings - Fiscal Years
    fiscalYears: appUrl('/api/v1/settings/fiscal-years'),
};
