import React, { useState } from 'react';
import { Switch, App } from 'antd';
import { http } from '../api/http';

export function StatusToggle({ value, id, endpoint, onChange }) {
    const [loading, setLoading] = useState(false);
    const [currentValue, setCurrentValue] = useState(Boolean(value));
    const { notification } = App.useApp();

    const handleToggle = async (checked) => {
        setLoading(true);
        try {
            await http.patch(`${endpoint}/${id}/status`, { is_active: checked });
            setCurrentValue(checked);
            notification.success({ 
                message: 'Status Updated', 
                description: `Record is now ${checked ? 'active' : 'inactive'}` 
            });
            if (onChange) onChange(checked);
        } catch (error) {
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
                backgroundColor: currentValue ? '#10b981' : '#cbd5e1',
                boxShadow: currentValue ? '0 0 8px rgba(16, 185, 129, 0.3)' : 'none'
            }}
        />
    );
}
