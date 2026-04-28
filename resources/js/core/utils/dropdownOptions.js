export const paymentModeTypeOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank', label: 'Bank / QR / Wallet' },
];

export const fallbackDropdownAliases = {
    product_status: { label: 'Product Status', supports_data: false },
    formulation: { label: 'Formulation', supports_data: false },
    sales_type: { label: 'Sales Type', supports_data: false },
    payment_mode: { label: 'Payment Mode', supports_data: true },
    payment_type: { label: 'Payment Type', supports_data: false },
    adjustment_type: { label: 'Stock Adjustment Type', supports_data: true },
    expense_category: { label: 'Expense Category', supports_data: true },
};

export const stockEffectOptions = [
    { value: 'in', label: 'Adds stock' },
    { value: 'out', label: 'Reduces stock' },
];

export function dropdownAliasOptions(aliases = fallbackDropdownAliases) {
    return Object.entries(aliases).map(([value, meta]) => ({
        value,
        label: meta.label || value.replaceAll('_', ' '),
    }));
}

export function dropdownDataField(alias) {
    if (alias === 'payment_mode') {
        return {
            label: 'Settlement Type',
            options: paymentModeTypeOptions,
            placeholder: 'Choose settlement type',
        };
    }

    if (alias === 'expense_category') {
        return {
            label: 'Group Tag',
            placeholder: 'Optional internal grouping',
        };
    }

    if (alias === 'adjustment_type') {
        return {
            label: 'Stock Effect',
            options: stockEffectOptions,
            placeholder: 'Choose stock effect',
        };
    }

    return null;
}
