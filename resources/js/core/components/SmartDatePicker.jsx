import React from 'react';
import { DatePicker } from 'antd';
import { useBranding } from '../context/BrandingContext';
import { NepaliDatePicker } from './NepaliDatePicker';
import dayjs from 'dayjs';

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

    // For now, BS RangePicker is simplified to standard AD if not implemented
    // But we can implement a BS RangePicker later
    if (isBs) {
        // Return a simplified version or just AD for now to avoid breaking
        // Better: implement a basic BS range picker or just use AD for range
        return <DatePicker.RangePicker {...props} />;
    }

    return <DatePicker.RangePicker {...props} />;
};
