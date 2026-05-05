import React from 'react';
import { useBranding } from '../context/BrandingContext';

export function Money({ value }) {
    const { branding } = useBranding();
    const symbol = branding?.currency_symbol || 'Rs.';
    
    const formatted = new Intl.NumberFormat('en-NP', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

    return <span className="tabular">{symbol} {formatted}</span>;
}
