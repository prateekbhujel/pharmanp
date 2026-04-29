import React from 'react';
import { DatePicker } from 'antd';
import { useBranding } from '../context/BrandingContext';
import { NepaliDatePicker } from './NepaliDatePicker';
import { NepaliDateRangePicker } from './NepaliDateRangePicker';

export function SmartDatePicker(props) {
    const { branding } = useBranding();
    const isBs = branding?.calendar_type === 'bs';

    if (isBs) {
        return <NepaliDatePicker {...props} />;
    }

    return <DatePicker {...props} className={`full-width ${props.className || ''}`} />;
}

SmartDatePicker.RangePicker = (props) => {
    const { branding } = useBranding();
    const isBs = branding?.calendar_type === 'bs';

    if (isBs) {
        return <NepaliDateRangePicker {...props} />;
    }

    const { value, className, ...rest } = props;
    const normalizedValue = Array.isArray(value) && value.length === 0 ? null : value;

    return <DatePicker.RangePicker {...rest} value={normalizedValue} className={`full-width smart-date-range ${className || ''}`} />;
};
