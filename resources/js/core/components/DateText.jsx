import React from 'react';
import { useBranding } from '../context/BrandingContext';
import { formatCalendarDate } from '../utils/calendar';

export function DateText({
    value,
    style = 'medium',
    includeTime = false,
    includeWeekday = false,
    fallback = '-',
    className,
}) {
    const { branding } = useBranding();

    return (
        <span className={className}>
            {formatCalendarDate(value, branding?.calendar_type || 'ad', {
                style,
                includeTime,
                includeWeekday,
                fallback,
            })}
        </span>
    );
}
