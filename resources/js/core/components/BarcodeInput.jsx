import React from 'react';
import { Input } from 'antd';
import { BarcodeOutlined } from '@ant-design/icons';

export function BarcodeInput({ value, onChange, onScan, placeholder = 'Scan or type barcode', id }) {
    function handlePressEnter(event) {
        const nextValue = event.target.value?.trim();
        if (nextValue && onScan) {
            onScan(nextValue);
        }
    }

    return (
        <Input
            value={value}
            onChange={(event) => onChange?.(event.target.value)}
            onPressEnter={handlePressEnter}
            prefix={<BarcodeOutlined />}
            placeholder={placeholder}
            allowClear
            id={id}
        />
    );
}
