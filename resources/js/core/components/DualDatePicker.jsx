import React, { useState } from 'react';
import { DatePicker, Input, Space } from 'antd';
import dayjs from 'dayjs';

export function DualDatePicker({ value, onChange, className }) {
    // Basic local state for the Nepali date input to show alongside the English date
    const [bsDate, setBsDate] = useState('');

    function handleAdChange(date) {
        if (onChange) {
            onChange(date);
        }
    }

    return (
        <div className={`dual-date-picker ${className || ''}`} style={{ display: 'flex', gap: 8, width: '100%' }}>
            <DatePicker 
                value={value} 
                onChange={onChange} 
                className="full-width"
                placeholder="Select Date"
            />
        </div>
    );
}
