import React from 'react';
import { DatePicker } from 'antd';
import { useBranding } from '../context/BrandingContext';
import { NepaliDatePicker } from './NepaliDatePicker';
import { NepaliDateRangePicker } from './NepaliDateRangePicker';
import { parseCalendarInput } from '../utils/calendar';

const keyboardDateFormats = [
    'YYYY-MM-DD',
    'YYYY-M-D',
    'YYYY/MM/DD',
    'YYYY/M/D',
    'YYYY.MM.DD',
    'YYYY.M.D',
];

export function SmartDatePicker(props) {
    const { branding } = useBranding();
    const isBs = branding?.calendar_type === 'bs';

    if (isBs) {
        return <NepaliDatePicker {...props} />;
    }

    const { onChange, onBlur, onKeyDown, ...rest } = props;

    function commitTypedValue(event) {
        const parsed = parseCalendarInput(event.target.value, 'ad');

        if (parsed) {
            onChange?.(parsed, parsed.format('YYYY-MM-DD'));
        }
    }

    return (
        <DatePicker
            {...rest}
            format={props.format || keyboardDateFormats}
            onChange={onChange}
            onBlur={(event) => {
                commitTypedValue(event);
                onBlur?.(event);
            }}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    commitTypedValue(event);
                }
                onKeyDown?.(event);
            }}
            className={`full-width ${props.className || ''}`}
        />
    );
}

SmartDatePicker.RangePicker = (props) => {
    const { branding } = useBranding();
    const isBs = branding?.calendar_type === 'bs';

    if (isBs) {
        return <NepaliDateRangePicker {...props} />;
    }

    const { value, className, ...rest } = props;
    const normalizedValue = Array.isArray(value) && value.length === 0 ? null : value;

    return (
        <DatePicker.RangePicker
            {...rest}
            value={normalizedValue}
            format={props.format || keyboardDateFormats}
            className={`full-width smart-date-range ${className || ''}`}
        />
    );
};
