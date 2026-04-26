export function moneyNumber(value) {
    return Number(Number(value || 0).toFixed(2));
}

export function itemGross(row, rateField = 'unit_price') {
    return moneyNumber((Number(row.quantity) || 0) * (Number(row[rateField]) || 0));
}

export function itemDiscount(row, rateField = 'unit_price') {
    return moneyNumber(itemGross(row, rateField) * (Number(row.discount_percent || 0) / 100));
}

export function itemFreeGoodsValue(row) {
    return moneyNumber((Number(row.free_quantity || 0) || 0) * ((Number(row.mrp || 0) || 0) * (Number(row.cc_rate || 0) / 100)));
}

export function itemNet(row, rateField = 'unit_price') {
    return moneyNumber(itemGross(row, rateField) - itemDiscount(row, rateField));
}

export function summarizeItems(rows, rateField = 'unit_price') {
    return rows.reduce((summary, row) => ({
        subtotal: moneyNumber(summary.subtotal + itemGross(row, rateField)),
        discount: moneyNumber(summary.discount + itemDiscount(row, rateField)),
        freeGoods: moneyNumber(summary.freeGoods + itemFreeGoodsValue(row)),
        tax: moneyNumber(summary.tax + Number(row.tax_amount || 0)),
        grandTotal: moneyNumber(summary.grandTotal + itemNet(row, rateField) + Number(row.tax_amount || 0)),
    }), {
        subtotal: 0,
        discount: 0,
        freeGoods: 0,
        tax: 0,
        grandTotal: 0,
    });
}

export function validationErrorsByLine(errors, collection = 'items') {
    return Object.entries(errors || {}).reduce((grouped, [field, messages]) => {
        const match = field.match(new RegExp(`^${collection}\\.(\\d+)`));

        if (!match) {
            return grouped;
        }

        const index = Number(match[1]);
        grouped[index] = [...(grouped[index] || []), ...messages];

        return grouped;
    }, {});
}
