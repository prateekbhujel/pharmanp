import dayjs from 'dayjs';

export const bsData = {
    2075: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2076: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2077: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2078: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2079: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2080: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2081: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2082: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2083: [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
    2084: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2085: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
};

export const nepaliMonthsFull = [
    'Baisakh',
    'Jestha',
    'Ashadh',
    'Shrawan',
    'Bhadra',
    'Ashwin',
    'Kartik',
    'Mangsir',
    'Poush',
    'Magh',
    'Falgun',
    'Chaitra',
];

export const nepaliMonthsShort = [
    'Bai',
    'Jes',
    'Ash',
    'Shr',
    'Bha',
    'Asw',
    'Kar',
    'Man',
    'Pou',
    'Mag',
    'Fal',
    'Cha',
];

export const nepaliDaysShort = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const refBsYear = 2075;
const refBsMonth = 0;
const refBsDay = 1;
const refAdDate = dayjs('2018-04-14');

function pad(value) {
    return String(value).padStart(2, '0');
}

export function bsToAd(year, month, day) {
    let totalDays = 0;

    for (let currentYear = refBsYear; currentYear < year; currentYear += 1) {
        totalDays += (bsData[currentYear] || []).reduce((sum, days) => sum + days, 0);
    }

    for (let currentMonth = 0; currentMonth < month; currentMonth += 1) {
        totalDays += (bsData[year] || [])[currentMonth] || 0;
    }

    totalDays += day - refBsDay;

    return refAdDate.add(totalDays, 'day');
}

export function adToBs(value) {
    const adDate = dayjs(value);

    if (!adDate.isValid()) {
        return null;
    }

    let diff = adDate.startOf('day').diff(refAdDate, 'day');

    if (diff < 0) {
        return null;
    }

    let year = refBsYear;
    let month = refBsMonth;
    let day = refBsDay;

    while (diff > 0) {
        const daysInMonth = (bsData[year] || [])[month] || 30;
        const remainingDays = daysInMonth - day + 1;

        if (diff >= remainingDays) {
            diff -= remainingDays;
            day = 1;
            month += 1;

            if (month > 11) {
                month = 0;
                year += 1;
            }
        } else {
            day += diff;
            diff = 0;
        }
    }

    return { year, month, day };
}

export function formatBsDate(value, {
    style = 'medium',
    includeTime = false,
    includeSeconds = false,
    includeWeekday = false,
    includeEra = false,
    fallback = '-',
} = {}) {
    const date = dayjs(value);

    if (!date.isValid()) {
        return fallback;
    }

    const bs = adToBs(date);

    if (!bs) {
        return formatAdDate(value, { style, includeTime, includeSeconds, includeWeekday, fallback });
    }

    const monthLabel = style === 'compact'
        ? pad(bs.month + 1)
        : style === 'long' || style === 'medium-long'
            ? nepaliMonthsFull[bs.month]
            : nepaliMonthsShort[bs.month];
    const dayLabel = pad(bs.day);

    let label = style === 'compact'
        ? `${bs.year}-${monthLabel}-${dayLabel}`
        : `${dayLabel} ${monthLabel} ${bs.year}`;

    if (includeWeekday) {
        label = `${nepaliDaysShort[date.day()]} ${label}`;
    }

    if (includeTime) {
        label = `${label} ${date.format(includeSeconds ? 'h:mm:ss A' : 'h:mm A').toLowerCase()}`;
    }

    return label;
}

export function formatAdDate(value, {
    style = 'medium',
    includeTime = false,
    includeSeconds = false,
    includeWeekday = false,
    fallback = '-',
} = {}) {
    const date = dayjs(value);

    if (!date.isValid()) {
        return fallback;
    }

    const options = {
        year: 'numeric',
        month: style === 'long' ? 'long' : style === 'compact' ? '2-digit' : 'short',
        day: style === 'compact' ? '2-digit' : 'numeric',
    };

    if (includeWeekday) {
        options.weekday = 'short';
    }

    if (includeTime) {
        options.hour = 'numeric';
        options.minute = '2-digit';
        options.hour12 = true;

        if (includeSeconds) {
            options.second = '2-digit';
        }
    }

    return new Intl.DateTimeFormat('en-NP', options)
        .format(date.toDate())
        .replace(/\b(AM|PM)\b/g, (match) => match.toLowerCase());
}

export function formatCalendarDate(value, calendarType = 'ad', options = {}) {
    return calendarType === 'bs'
        ? formatBsDate(value, options)
        : formatAdDate(value, options);
}

export function isLikelyDateValue(key, value) {
    if (value === null || value === undefined) {
        return false;
    }

    if (dayjs.isDayjs(value) || value instanceof Date) {
        return true;
    }

    if (typeof value !== 'string') {
        return false;
    }

    return /(date|_at|starts_on|ends_on|expires_on|expires_at)$/i.test(key)
        || /^\d{4}-\d{2}-\d{2}(?:[ T].*)?$/.test(value);
}

export function isNepaliHolidayDate(year, month, day) {
    const date = bsToAd(year, month, day);

    if (!date?.isValid?.()) {
        return false;
    }

    // Nepal's official public holidays are year-specific. This marks the
    // recurring weekly public holiday without pretending to be a full gazette.
    return date.day() === 6;
}
