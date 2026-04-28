import React from 'react';
import { StatusBadge } from './PharmaBadge';

export function StatusTag({ active, trueText = 'Active', falseText = 'Inactive' }) {
    const deleted = !active && String(falseText).toLowerCase() === 'deleted';

    return <StatusBadge value={active} trueText={trueText} falseText={falseText} deleted={deleted} />;
}
