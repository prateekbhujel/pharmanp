import { apiUrl } from '../utils/url';

export const endpoints = {
    me: apiUrl('/api/v1/me'),
    dashboard: apiUrl('/api/v1/dashboard/summary'),
    search: apiUrl('/api/v1/search'),
    profile: apiUrl('/api/v1/profile'),
    productMeta: apiUrl('/api/v1/inventory/products/meta'),
    products: apiUrl('/api/v1/inventory/products'),
    productRestore: (id) => apiUrl(`/api/v1/inventory/products/${id}/restore`),
    quickCompany: apiUrl('/api/v1/inventory/companies/quick'),
    quickUnit: apiUrl('/api/v1/inventory/units/quick'),
    quickCategory: apiUrl('/api/v1/inventory/categories/quick'),
    inventoryMaster: (master) => apiUrl(`/api/v1/inventory/masters/${master}`),
    inventoryMasterRestore: (master, id) => apiUrl(`/api/v1/inventory/masters/${master}/${id}/restore`),
    inventoryMasterExport: (master) => apiUrl(`/api/v1/exports/inventory/masters/${master}`),
    inventoryProductsExport: apiUrl('/api/v1/exports/inventory/products'),
    inventoryBatchesExport: apiUrl('/api/v1/exports/inventory/batches'),
    datasetExport: (dataset) => apiUrl(`/api/v1/exports/${dataset}`),
    inventoryBatches: apiUrl('/api/v1/inventory/batches'),
    inventoryBatchOptions: apiUrl('/api/v1/inventory/batches/options'),
    stockAdjustments: apiUrl('/api/v1/inventory/stock-adjustments'),
    stockMovements: apiUrl('/api/v1/inventory/stock-movements'),
    suppliers: apiUrl('/api/v1/suppliers'),
    supplierRestore: (id) => apiUrl(`/api/v1/suppliers/${id}/restore`),
    supplierOptions: apiUrl('/api/v1/suppliers/options'),
    customers: apiUrl('/api/v1/customers'),
    customerRestore: (id) => apiUrl(`/api/v1/customers/${id}/restore`),
    customerOptions: apiUrl('/api/v1/customers/options'),
    purchaseOrders: apiUrl('/api/v1/purchase/orders'),
    purchaseOrderApprove: (orderId) => apiUrl(`/api/v1/purchase/orders/${orderId}/approve`),
    purchaseOrderReceive: (orderId) => apiUrl(`/api/v1/purchase/orders/${orderId}/receive`),
    purchaseOrderPay: (orderId) => apiUrl(`/api/v1/purchase/orders/${orderId}/pay`),
    purchases: apiUrl('/api/v1/purchases'),
    purchaseReturns: apiUrl('/api/v1/purchase/returns'),
    purchaseReturnPurchases: apiUrl('/api/v1/purchase/returns/purchases'),
    purchaseReturnItems: (purchaseId) => apiUrl(`/api/v1/purchase/returns/purchases/${purchaseId}/items`),
    purchaseReturnBatches: apiUrl('/api/v1/purchase/returns/batches'),
    salesProductLookup: apiUrl('/api/v1/sales/product-lookup'),
    salesInvoices: apiUrl('/api/v1/sales/invoices'),
    salesInvoicePayment: (id) => apiUrl(`/api/v1/sales/invoices/${id}/payment`),
    salesInvoiceItems: (id) => apiUrl(`/api/v1/sales/invoices/${id}/items`),
    salesInvoiceReturns: (id) => apiUrl(`/api/v1/sales/invoices/${id}/returns`),
    mrOptions: apiUrl('/api/v1/mr/options'),
    mrRepresentatives: apiUrl('/api/v1/mr/representatives'),
    mrVisits: apiUrl('/api/v1/mr/visits'),
    mrPerformance: apiUrl('/api/v1/mr/performance'),
    mrBranches: apiUrl('/api/v1/mr/branches'),
    mrBranchOptions: apiUrl('/api/v1/mr/branches/options'),
    mrBranchRestore: (id) => apiUrl(`/api/v1/mr/branches/${id}/restore`),
    mrBranchSales: apiUrl('/api/v1/mr/branch-sales'),
    setupAreas: apiUrl('/api/v1/setup/areas'),
    setupAreaOptions: apiUrl('/api/v1/setup/areas/options'),
    setupAreaRestore: (id) => apiUrl(`/api/v1/setup/areas/${id}/restore`),
    setupDivisions: apiUrl('/api/v1/setup/divisions'),
    setupDivisionOptions: apiUrl('/api/v1/setup/divisions/options'),
    setupDivisionRestore: (id) => apiUrl(`/api/v1/setup/divisions/${id}/restore`),
    setupEmployees: apiUrl('/api/v1/setup/employees'),
    setupEmployeeOptions: apiUrl('/api/v1/setup/employees/options'),
    setupEmployeeRestore: (id) => apiUrl(`/api/v1/setup/employees/${id}/restore`),
    setupTargets: apiUrl('/api/v1/setup/targets'),
    setupTargetRestore: (id) => apiUrl(`/api/v1/setup/targets/${id}/restore`),
    vouchers: apiUrl('/api/v1/accounting/vouchers'),
    reports: apiUrl('/api/v1/reports'),
    reportExport: (report) => apiUrl(`/api/v1/reports/${report}/export`),
    importTargets: apiUrl('/api/v1/imports/targets'),
    importSample: (target) => apiUrl(`/api/v1/imports/targets/${target}/sample`),
    importPreview: apiUrl('/api/v1/imports/preview'),
    importConfirm: apiUrl('/api/v1/imports/confirm'),
    purchaseOcrExtract: apiUrl('/api/v1/imports/ocr/extract'),
    purchaseOcrDraft: apiUrl('/api/v1/imports/ocr/draft-purchase'),
    featureCatalog: apiUrl('/api/v1/setup/features'),
    branding: apiUrl('/api/v1/setup/branding'),
    roles: apiUrl('/api/v1/setup/roles'),
    users: apiUrl('/api/v1/setup/users'),
    userImpersonate: (id) => apiUrl(`/api/v1/setup/users/${id}/impersonate`),
    stopImpersonation: apiUrl('/api/v1/setup/users/stop-impersonating'),
    setupStatus: apiUrl('/setup/status'),
    setupComplete: apiUrl('/setup/complete'),

    // Accounting — Expenses
    expenses: apiUrl('/api/v1/accounting/expenses'),

    // Accounting — Payments
    payments: apiUrl('/api/v1/accounting/payments'),
    paymentOutstandingBills: apiUrl('/api/v1/accounting/payments/outstanding-bills'),

    // Sales — Returns
    salesReturns: apiUrl('/api/v1/sales/returns'),
    salesReturnInvoiceOptions: apiUrl('/api/v1/sales/returns/invoice-options'),
    salesReturnInvoiceItems: (invoiceId) => apiUrl(`/api/v1/sales/returns/invoices/${invoiceId}/items`),

    // Customer Ledger
    customerLedger: (customerId) => apiUrl(`/api/v1/customers/${customerId}/ledger`),

    // Settings — Dropdown Options
    dropdownOptions: apiUrl('/api/v1/settings/dropdown-options'),

    // Settings — Admin
    settingsAdmin: apiUrl('/api/v1/settings/admin'),
    settingsTestMail: apiUrl('/api/v1/settings/admin/test-mail'),

    // Settings — Party Types
    partyTypes: apiUrl('/api/v1/settings/party-types'),

    // Settings — Supplier Types
    supplierTypes: apiUrl('/api/v1/settings/supplier-types'),
    
    // Settings - Fiscal Years
    fiscalYears: apiUrl('/api/v1/settings/fiscal-years'),
};
