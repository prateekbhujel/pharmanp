/**
 * Tiny zero-dependency SVG chart components.
 * Bar chart, sparkline, pie/donut — all pure SVG, no extra libraries.
 */
import React from 'react';

const COLORS = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'];

// ── helpers ───────────────────────────────────────────────────────────────────
function fmt(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
    return String(Math.round(n));
}

// ── Bar Chart ─────────────────────────────────────────────────────────────────
/**
 * @param {{ data: { label: string, bars: { value: number, color?: string }[] }[] }} props
 */
export function BarChart({ data = [], height = 200, gap = 8, colors = COLORS, legend }) {
    if (!data.length) return <Empty />;

    const seriesCount  = data[0]?.bars?.length ?? 1;
    const allValues    = data.flatMap((d) => d.bars.map((b) => b.value));
    const maxVal       = Math.max(...allValues, 1);
    const totalWidth   = 480;
    const padL         = 48;
    const padB         = 28;
    const chartW       = totalWidth - padL;
    const chartH       = height - padB;
    const groupW       = chartW / data.length;
    const barW         = Math.max(4, (groupW - gap * (seriesCount + 1)) / seriesCount);

    return (
        <div>
            {legend && (
                <div style={{ display: 'flex', gap: 16, marginBottom: 8, flexWrap: 'wrap' }}>
                    {legend.map((l, i) => (
                        <span key={i} style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 12, color: '#555' }}>
                            <span style={{ display: 'inline-block', width: 10, height: 10, borderRadius: 2, background: colors[i] }} />
                            {l}
                        </span>
                    ))}
                </div>
            )}
            <svg viewBox={`0 0 ${totalWidth} ${height}`} style={{ width: '100%', height: 'auto', overflow: 'visible' }}>
                {/* Y-axis grid lines */}
                {[0, 0.25, 0.5, 0.75, 1].map((frac) => {
                    const y = padB + chartH - frac * chartH;
                    return (
                        <g key={frac}>
                            <line x1={padL} y1={y} x2={totalWidth} y2={y} stroke="#f0f0f0" strokeWidth={1} />
                            <text x={padL - 4} y={y + 4} textAnchor="end" fontSize={10} fill="#aaa">{fmt(maxVal * frac)}</text>
                        </g>
                    );
                })}
                {/* Bars */}
                {data.map((group, gi) => {
                    const groupX = padL + gi * groupW;
                    return (
                        <g key={gi}>
                            {group.bars.map((bar, bi) => {
                                const barH  = Math.max(2, (bar.value / maxVal) * chartH);
                                const x     = groupX + gap + bi * (barW + gap);
                                const y     = padB + chartH - barH;
                                const color = bar.color || colors[bi % colors.length];
                                return (
                                    <g key={bi}>
                                        <rect x={x} y={y} width={barW} height={barH} rx={3} fill={color} opacity={0.88}>
                                            <title>{group.label}: {fmt(bar.value)}</title>
                                        </rect>
                                    </g>
                                );
                            })}
                            <text
                                x={groupX + groupW / 2}
                                y={height - 6}
                                textAnchor="middle"
                                fontSize={10}
                                fill="#888"
                            >
                                {group.label}
                            </text>
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

// ── Pie / Donut Chart ─────────────────────────────────────────────────────────
export function DonutChart({ data = [], size = 180, colors = COLORS }) {
    if (!data.length) return <Empty />;

    const total = data.reduce((s, d) => s + (d.value || 0), 0);
    if (total === 0) return <Empty />;

    const cx = size / 2, cy = size / 2, r = size * 0.38, inner = size * 0.22;
    let angle = -Math.PI / 2;

    const slices = data.map((d, i) => {
        const frac  = d.value / total;
        const sweep = frac * 2 * Math.PI;
        const x1    = cx + r * Math.cos(angle);
        const y1    = cy + r * Math.sin(angle);
        const x2    = cx + r * Math.cos(angle + sweep);
        const y2    = cy + r * Math.sin(angle + sweep);
        const large = sweep > Math.PI ? 1 : 0;
        const ix1   = cx + inner * Math.cos(angle);
        const iy1   = cy + inner * Math.sin(angle);
        const ix2   = cx + inner * Math.cos(angle + sweep);
        const iy2   = cy + inner * Math.sin(angle + sweep);
        const path  = [
            `M ${x1} ${y1}`,
            `A ${r} ${r} 0 ${large} 1 ${x2} ${y2}`,
            `L ${ix2} ${iy2}`,
            `A ${inner} ${inner} 0 ${large} 0 ${ix1} ${iy1}`,
            'Z',
        ].join(' ');
        angle += sweep;
        return { path, color: d.color || colors[i % colors.length], label: d.label, value: d.value, pct: (frac * 100).toFixed(1) };
    });

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
            <svg viewBox={`0 0 ${size} ${size}`} style={{ width: size, height: size, flexShrink: 0 }}>
                {slices.map((s, i) => (
                    <path key={i} d={s.path} fill={s.color} opacity={0.9}>
                        <title>{s.label}: {fmt(s.value)} ({s.pct}%)</title>
                    </path>
                ))}
                <text x={cx} y={cy - 6} textAnchor="middle" fontSize={12} fill="#666">Total</text>
                <text x={cx} y={cy + 12} textAnchor="middle" fontSize={14} fontWeight="bold" fill="#333">{fmt(total)}</text>
            </svg>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                {slices.map((s, i) => (
                    <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
                        <span style={{ width: 10, height: 10, borderRadius: 2, background: s.color, flexShrink: 0, display: 'inline-block' }} />
                        <span style={{ color: '#555' }}>{s.label}</span>
                        <span style={{ marginLeft: 'auto', fontWeight: 600, color: '#333', paddingLeft: 12 }}>{s.pct}%</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Inline sparkline bar for table rows ───────────────────────────────────────
export function MiniBar({ value, max, color = '#2563eb', height = 8 }) {
    const pct = max > 0 ? Math.min((value / max) * 100, 100) : 0;
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <div style={{ flex: 1, background: '#f0f0f0', borderRadius: 4, height, overflow: 'hidden' }}>
                <div style={{ width: `${pct}%`, height: '100%', background: color, borderRadius: 4, transition: 'width .4s ease' }} />
            </div>
            <span style={{ fontSize: 11, color: '#888', minWidth: 32, textAlign: 'right' }}>{pct.toFixed(0)}%</span>
        </div>
    );
}

// ── Empty placeholder ─────────────────────────────────────────────────────────
function Empty() {
    return (
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: 80, color: '#ccc', fontSize: 13 }}>
            No data for this period
        </div>
    );
}
