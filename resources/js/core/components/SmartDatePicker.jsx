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

    return <DatePicker.RangePicker {...props} className={`full-width ${props.className || ''}`} />;
};
