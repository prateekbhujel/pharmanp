export const money = new Intl.NumberFormat('en-NP', {
    style: 'currency',
    currency: 'NPR',
    maximumFractionDigits: 2,
});

export function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat('en-NP', { dateStyle: 'medium' }).format(new Date(value));
}
