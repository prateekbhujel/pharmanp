export const accountCatalog = [
    { value: 'cash', label: '1100 - Cash in Hand', group: 'Assets' },
    { value: 'bank', label: '1200 - Bank Account', group: 'Assets' },
    { value: 'receivable', label: '1300 - Accounts Receivable', group: 'Assets' },
    { value: 'inventory', label: '1400 - Inventory Stock', group: 'Assets' },
    { value: 'payable', label: '2100 - Accounts Payable', group: 'Liabilities' },
    { value: 'capital', label: '3100 - Capital', group: 'Equity' },
    { value: 'sales', label: '4100 - Sales Income', group: 'Income' },
    { value: 'other_income', label: '4200 - Other Income', group: 'Income' },
    { value: 'expense', label: '5100 - Operating Expense', group: 'Expenses' },
    { value: 'purchase_return', label: '5200 - Purchase Return / Adjustment', group: 'Expenses' },
];

export const paymentStatusOptions = [
    { value: 'paid', label: 'Paid' },
    { value: 'partial', label: 'Partial' },
    { value: 'unpaid', label: 'Unpaid' },
];

export const voucherTypeOptions = [
    { value: 'payment_in', label: 'Payment In' },
    { value: 'payment_out', label: 'Payment Out' },
    { value: 'journal', label: 'Journal' },
    { value: 'contra', label: 'Contra' },
];

export const mrVisitStatusOptions = [
    { value: 'planned', label: 'Planned' },
    { value: 'visited', label: 'Visited' },
    { value: 'missed', label: 'Missed' },
    { value: 'converted', label: 'Converted' },
];
