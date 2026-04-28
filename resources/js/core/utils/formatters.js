import { formatCalendarDate } from './calendar';

export const money = new Intl.NumberFormat('en-NP', {
    style: 'currency',
    currency: 'NPR',
    maximumFractionDigits: 2,
});

export function formatDate(value, calendarType = 'ad') {
    if (!value) return '-';
    return formatCalendarDate(value, calendarType, { style: 'medium' });
}
