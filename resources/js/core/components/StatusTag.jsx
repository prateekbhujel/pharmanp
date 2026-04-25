import React from 'react';
import { Tag } from 'antd';

export function StatusTag({ active, trueText = 'Active', falseText = 'Inactive' }) {
    return <Tag color={active ? 'green' : 'default'}>{active ? trueText : falseText}</Tag>;
}
