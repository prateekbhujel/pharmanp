import React, { useMemo } from 'react';
import { Button, Empty } from 'antd';
import { PrinterOutlined } from '@ant-design/icons';
import { code128Bars, renderCode128Svg } from '../utils/code128';

export function productBarcodeValue(product) {
    return product?.barcode || product?.sku || product?.product_code || '';
}

export function BarcodeLabel({ value, caption, compact = false }) {
    const barcode = useMemo(() => {
        try {
            return code128Bars(value);
        } catch {
            return null;
        }
    }, [value]);

    if (!barcode) {
        return (
            <div className="barcode-label barcode-label-empty">
                <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="No printable barcode" />
            </div>
        );
    }

    const quiet = 10;
    const moduleWidth = compact ? 1.25 : 1.5;
    const barHeight = compact ? 34 : 42;
    const height = compact ? 48 : 58;
    const width = (barcode.width + quiet * 2) * moduleWidth;

    return (
        <div className={`barcode-label ${compact ? 'barcode-label-compact' : ''}`}>
            {caption ? <span className="barcode-caption">{caption}</span> : null}
            <svg
                role="img"
                aria-label={barcode.value}
                viewBox={`0 0 ${width} ${height}`}
                width={width}
                height={height}
            >
                <rect width="100%" height="100%" fill="#fff" />
                <g fill="#0f172a">
                    {barcode.bars.map((bar, index) => (
                        <rect
                            key={`${bar.x}-${index}`}
                            x={(bar.x + quiet) * moduleWidth}
                            y={0}
                            width={bar.width * moduleWidth}
                            height={barHeight}
                        />
                    ))}
                </g>
                <text
                    x={width / 2}
                    y={barHeight + 12}
                    textAnchor="middle"
                    fontFamily="Inter, Arial, sans-serif"
                    fontSize={10}
                    fontWeight={700}
                    fill="#0f172a"
                >
                    {barcode.value}
                </text>
            </svg>
        </div>
    );
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

export function printBarcodeLabels(records, options = {}) {
    const labels = records
        .map((record) => ({
            title: record.name || record.batch_no || record.label || 'Barcode',
            subtitle: record.company?.name || record.sku || record.product_code || '',
            value: productBarcodeValue(record) || record.value || '',
        }))
        .filter((record) => record.value);

    if (!labels.length) {
        return false;
    }

    const labelMarkup = labels.map((record) => (
        `<section class="barcode-print-label">
            <strong>${escapeHtml(record.title)}</strong>
            ${record.subtitle ? `<span>${escapeHtml(record.subtitle)}</span>` : ''}
            ${renderCode128Svg(record.value, { height: 58, barHeight: 42 })}
        </section>`
    )).join('');

    const printWindow = window.open('', 'pharmanp_barcode_labels', 'width=840,height=720');

    if (!printWindow) {
        return false;
    }

    printWindow.document.write(`<!doctype html>
        <html>
            <head>
                <title>${escapeHtml(options.title || 'Barcode Labels')}</title>
                <style>
                    * { box-sizing: border-box; }
                    body { color: #0f172a; font-family: Inter, Arial, sans-serif; margin: 18px; }
                    .barcode-print-sheet { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
                    .barcode-print-label { align-items: center; border: 1px solid #dbe7e4; border-radius: 10px; display: grid; gap: 4px; justify-items: center; padding: 10px; page-break-inside: avoid; }
                    .barcode-print-label strong { font-size: 12px; line-height: 1.25; text-align: center; }
                    .barcode-print-label span { color: #64748b; font-size: 10px; }
                    svg { display: block; max-width: 100%; }
                    @media print {
                        body { margin: 8mm; }
                        .barcode-print-sheet { grid-template-columns: repeat(3, 1fr); }
                    }
                </style>
            </head>
            <body>
                <main class="barcode-print-sheet">${labelMarkup}</main>
                <script>
                    window.addEventListener('load', () => {
                        window.focus();
                        window.print();
                    });
                </script>
            </body>
        </html>`);
    printWindow.document.close();

    return true;
}

export function BarcodePrintButton({ record, children = 'Print Barcode' }) {
    return (
        <Button
            icon={<PrinterOutlined />}
            onClick={() => printBarcodeLabels([record])}
        >
            {children}
        </Button>
    );
}
