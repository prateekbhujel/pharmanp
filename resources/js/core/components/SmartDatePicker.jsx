import React from 'react';
import { Button, DatePicker } from 'antd';
import { CloseCircleOutlined } from '@ant-design/icons';
import { useBranding } from '../context/BrandingContext';
import { NepaliDatePicker } from './NepaliDatePicker';

export function SmartDatePicker(props) {
    const { branding } = useBranding();
    const isBs = branding?.calendar_type === 'bs';

    if (isBs) {
        return <NepaliDatePicker {...props} />;
    }

    return <DatePicker {...props} className={`full-width ${props.className || ''}`} />;
}

function BsRangePicker({
    value,
    onChange,
    placeholder = ['Start date', 'End date'],
    className,
    allowClear = true,
    disabled = false,
}) {
    const [start, end] = Array.isArray(value) ? value : [];
    const disabledStart = Array.isArray(disabled) ? disabled[0] : disabled;
    const disabledEnd = Array.isArray(disabled) ? disabled[1] : disabled;

    function push(nextStart, nextEnd) {
        if (!nextStart && !nextEnd) {
            onChange?.([]);
            return;
        }

        onChange?.([nextStart, nextEnd]);
    }

    return (
        <div
            className={className}
            style={{
                display: 'grid',
                gridTemplateColumns: allowClear ? 'minmax(0, 1fr) auto minmax(0, 1fr) auto' : 'minmax(0, 1fr) auto minmax(0, 1fr)',
                gap: 8,
                alignItems: 'center',
                width: '100%',
            }}
        >
            <NepaliDatePicker
                value={start}
                onChange={(nextStart) => push(nextStart, end)}
                placeholder={placeholder?.[0] || 'Start date'}
                disabled={disabledStart}
            />
            <span style={{ color: '#64748b', fontSize: 12 }}>to</span>
            <NepaliDatePicker
                value={end}
                onChange={(nextEnd) => push(start, nextEnd)}
                placeholder={placeholder?.[1] || 'End date'}
                disabled={disabledEnd}
            />
            {allowClear && !disabled && (start || end) ? (
                <Button
                    type="text"
                    icon={<CloseCircleOutlined />}
                    onClick={() => push(null, null)}
                    aria-label="Clear date range"
                />
            ) : null}
        </div>
    );
}

SmartDatePicker.RangePicker = (props) => {
    const { branding } = useBranding();
    const isBs = branding?.calendar_type === 'bs';

    if (isBs) {
        return <BsRangePicker {...props} />;
    }

    return <DatePicker.RangePicker {...props} className={`full-width ${props.className || ''}`} />;
};
