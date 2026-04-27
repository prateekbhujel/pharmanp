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
        // In a full implementation with `nepali-date-converter`, you would:
        // const converted = new NepaliDate(date.toDate()).format('YYYY-MM-DD');
        // setBsDate(converted);
    }

    return (
        <Space.Compact className={className} style={{ width: '100%' }}>
            <DatePicker 
                value={value} 
                onChange={handleAdChange} 
                style={{ width: '60%' }} 
                placeholder="AD Date" 
            />
            <Input 
                value={bsDate} 
                onChange={(e) => setBsDate(e.target.value)} 
                style={{ width: '40%' }} 
                placeholder="BS (YYYY-MM-DD)" 
                title="Bikram Sambat Date"
            />
        </Space.Compact>
    );
}
