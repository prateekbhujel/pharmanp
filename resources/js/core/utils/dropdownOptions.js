export const paymentModeTypeOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank', label: 'Bank / Transfer' },
    { value: 'qr', label: 'QR Payment' },
    { value: 'wallet', label: 'Digital Wallet' },
    { value: 'card', label: 'Card' },
];

export const fallbackDropdownAliases = {
    product_status: { label: 'Product Status', supports_data: false },
    formulation: { label: 'Formulation', supports_data: false },
    sales_type: { label: 'Sales Type', supports_data: false },
    payment_mode: { label: 'Payment Mode', supports_data: true },
    expense_category: { label: 'Expense Category', supports_data: true },
};

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

    return null;
}
