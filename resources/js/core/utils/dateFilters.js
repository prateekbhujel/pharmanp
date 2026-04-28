export function dateRangeParams(range) {
    const [from, to] = Array.isArray(range) ? range : [];

    return {
        ...(from ? { from: from.format('YYYY-MM-DD') } : {}),
        ...(to ? { to: to.format('YYYY-MM-DD') } : {}),
    };
}

export function applyDateRangeFilter(currentFilters, range) {
    const { from, to, ...rest } = currentFilters || {};

    return {
        ...rest,
        ...dateRangeParams(range),
    };
}
