import React, { useEffect, useState } from 'react';
import { Switch, App } from 'antd';
import { http } from '../api/http';

export function StatusToggle({ value, id, endpoint, onChange }) {
    const [loading, setLoading] = useState(false);
    const [currentValue, setCurrentValue] = useState(Boolean(value));
    const { notification } = App.useApp();

    useEffect(() => {
        setCurrentValue(Boolean(value));
    }, [value]);

    const handleToggle = async (checked) => {
        const previous = currentValue;
        setCurrentValue(checked);
        setLoading(true);
        try {
            await http.patch(`${endpoint}/${id}/status`, { is_active: checked });
            notification.success({ 
                message: 'Status Updated', 
                description: `Record is now ${checked ? 'active' : 'inactive'}` 
            });
            if (onChange) onChange(checked);
        } catch (error) {
            setCurrentValue(previous);
            notification.error({ 
                message: 'Update Failed', 
                description: error.response?.data?.message || 'Could not update status' 
            });
        } finally {
            setLoading(false);
        }
    };

    return (
        <Switch 
            size="small" 
            checked={currentValue} 
            loading={loading} 
            onChange={handleToggle}
            style={{ 
                backgroundColor: currentValue ? 'var(--primary-color)' : '#cbd5e1',
            }}
        />
    );
}
