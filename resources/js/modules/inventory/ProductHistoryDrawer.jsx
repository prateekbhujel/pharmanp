import React from 'react';
import { Button, Drawer, Tabs } from 'antd';
import { PrinterOutlined } from '@ant-design/icons';
import { BarcodeLabel, printBarcodeLabels, productBarcodeValue } from '../../core/components/BarcodeLabel';
import { DateText } from '../../core/components/DateText';
import { Money } from '../../core/components/Money';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { endpoints } from '../../core/api/endpoints';
import { useServerTable } from '../../core/hooks/useServerTable';

const batchColumns = [
    { title: 'Batch', dataIndex: 'batch_no', field: 'batch_no', sorter: true, width: 150 },
    { title: 'Supplier', dataIndex: ['supplier', 'name'], width: 180 },
    { title: 'Expiry', dataIndex: 'expires_at', field: 'expires_at', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
    { title: 'Available', dataIndex: 'quantity_available', field: 'quantity_available', sorter: true, align: 'right', width: 120 },
    { title: 'Received', dataIndex: 'quantity_received', align: 'right', width: 120 },
    { title: 'Purchase', dataIndex: 'purchase_price', field: 'purchase_price', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
    { title: 'MRP', dataIndex: 'mrp', field: 'mrp', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
    { title: 'Status', dataIndex: 'expiry_status', width: 130, render: (value) => <PharmaBadge tone={value === 'expired' ? 'danger' : value?.startsWith('expiring') ? 'warning' : 'success'}>{String(value || 'valid').replaceAll('_', ' ')}</PharmaBadge> },
];

const movementColumns = [
    { title: 'Date', dataIndex: 'movement_date', field: 'movement_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
    { title: 'Batch', dataIndex: ['batch', 'batch_no'], width: 150 },
    { title: 'Movement', dataIndex: 'movement_type', field: 'movement_type', sorter: true, width: 180, render: (value) => <PharmaBadge tone={String(value || '').includes('_out') ? 'warning' : 'info'}>{String(value || '').replaceAll('_', ' ')}</PharmaBadge> },
    { title: 'In', dataIndex: 'quantity_in', field: 'quantity_in', sorter: true, align: 'right', width: 100 },
    { title: 'Out', dataIndex: 'quantity_out', field: 'quantity_out', sorter: true, align: 'right', width: 100 },
    { title: 'Reference', width: 180, render: (_, row) => row.reference_type ? `${row.reference_type} #${row.reference_id}` : '-' },
    { title: 'Notes', dataIndex: 'notes', width: 280 },
];

/**
 * Drawer showing batch history and stock movement history for a product.
 *
 * @param {object} props
 * @param {object} props.product - Product record to show history for.
 * @param {Function} props.onClose - Callback when the drawer is closed.
 */
export function ProductHistoryDrawer({ product, onClose }) {
    const batchTable = useServerTable({
        endpoint: endpoints.inventoryBatches,
        defaultSort: { field: 'expires_at', order: 'asc' },
        defaultFilters: { product_id: product.id },
    });
    const movementTable = useServerTable({
        endpoint: endpoints.stockMovements,
        defaultSort: { field: 'movement_date', order: 'desc' },
        defaultFilters: { product_id: product.id },
    });

    return (
        <Drawer
            title={product.name}
            open
            onClose={onClose}
            width={980}
            destroyOnHidden
            className="product-history-drawer"
        >
            <div className="product-history-summary">
                <div>
                    <span>SKU</span>
                    <strong>{product.sku || product.product_code || '-'}</strong>
                </div>
                <div>
                    <span>Company</span>
                    <strong>{product.company?.name || '-'}</strong>
                </div>
                <div>
                    <span>Stock</span>
                    <strong>{Number(product.stock_on_hand || 0).toLocaleString()}</strong>
                </div>
                <div>
                    <span>MRP</span>
                    <strong><Money value={product.mrp} /></strong>
                </div>
                <div>
                    <span>Barcode</span>
                    <strong>{productBarcodeValue(product) || '-'}</strong>
                </div>
            </div>
            <div className="barcode-history-preview">
                <BarcodeLabel value={productBarcodeValue(product)} caption={product.name} compact />
                <Button icon={<PrinterOutlined />} onClick={() => printBarcodeLabels([product])}>Print Label</Button>
            </div>
            <Tabs
                items={[
                    {
                        key: 'batches',
                        label: 'Batch History',
                        children: <ServerTable table={batchTable} columns={batchColumns} />,
                    },
                    {
                        key: 'movements',
                        label: 'Stock Movement',
                        children: <ServerTable table={movementTable} columns={movementColumns} />,
                    },
                ]}
            />
        </Drawer>
    );
}
