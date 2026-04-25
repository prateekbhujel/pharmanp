import React from 'react';
import { money } from '../utils/formatters';

export function Money({ value }) {
    return <span className="tabular">{money.format(Number(value || 0))}</span>;
}
