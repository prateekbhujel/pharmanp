import React from 'react';

const toneAliases = {
    active: 'success',
    available: 'success',
    approved: 'info',
    archived: 'neutral',
    archive: 'neutral',
    cancelled: 'danger',
    closed: 'neutral',
    completed: 'success',
    current: 'info',
    deleted: 'danger',
    expired: 'danger',
    inactive: 'neutral',
    open: 'success',
    ordered: 'info',
    paid: 'success',
    partial: 'warning',
    pending: 'warning',
    planned: 'info',
    processing: 'info',
    received: 'success',
    rejected: 'danger',
    success: 'success',
    unpaid: 'danger',
    warning: 'warning',
};

export function toneFromStatus(value, fallback = 'neutral') {
    const key = String(value ?? '').trim().toLowerCase().replaceAll(' ', '_');

    return toneAliases[key] || fallback;
}

export function labelFromStatus(value) {
    if (value === undefined || value === null || value === '') {
        return '-';
    }

    return String(value)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function PharmaBadge({
    children,
    className = '',
    dot = false,
    icon = null,
    pulse = false,
    title,
    tone = 'neutral',
}) {
    const normalizedTone = toneFromStatus(tone, tone);
    const classes = [
        'pharma-badge',
        `pharma-badge-${normalizedTone}`,
        dot ? 'pharma-badge-with-dot' : '',
        pulse ? 'pharma-badge-pulse' : '',
        className,
    ].filter(Boolean).join(' ');

    return (
        <span className={classes} title={title}>
            {dot && <span className="pharma-badge-dot" />}
            {icon && <span className="pharma-badge-icon">{icon}</span>}
            <span className="pharma-badge-label">{children}</span>
        </span>
    );
}

export function StatusBadge({ value, trueText = 'Active', falseText = 'Inactive', deleted = false }) {
    if (deleted) {
        return <PharmaBadge tone="deleted" dot>Deleted</PharmaBadge>;
    }

    const active = Boolean(value);

    return (
        <PharmaBadge tone={active ? 'active' : 'inactive'} dot>
            {active ? trueText : falseText}
        </PharmaBadge>
    );
}

export function PaymentStatusBadge({ value }) {
    return (
        <PharmaBadge tone={toneFromStatus(value)} dot>
            {labelFromStatus(value)}
        </PharmaBadge>
    );
}
