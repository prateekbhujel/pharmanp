import React from 'react';
import { Button, Space } from 'antd';
import { BarcodeOutlined, DeleteOutlined, EditOutlined, HistoryOutlined, UndoOutlined } from '@ant-design/icons';
import { DateText } from '../../core/components/DateText';
import { Money } from '../../core/components/Money';
import { StatusTag } from '../../core/components/StatusTag';
import { StatusToggle } from '../../core/components/StatusToggle';
import { endpoints } from '../../core/api/endpoints';

/**
 * Column definitions for the main product table.
 *
 * @param {object} handlers - Action handlers from the parent page.
 * @param {Function} handlers.onHistory - Open history drawer for a record.
 * @param {Function} handlers.onEdit - Open edit drawer for a record.
 * @param {Function} handlers.onDelete - Soft-delete a record.
 * @param {Function} handlers.onRestore - Restore a soft-deleted record.
 * @param {Function} handlers.onPrintBarcode - Print barcode label for a record.
 * @returns {Array} - Ant Design table column configs.
 */
export function productColumns({ onHistory, onEdit, onDelete, onRestore, onPrintBarcode, actionId = null }) {
    return [
        {
            title: 'Product Name',
            dataIndex: 'name',
            field: 'name',
            sorter: true,
            width: 300,
            render: (value, row) => (
                <div className="product-cell">
                    {row.image_url ? <img src={row.image_url} alt="" /> : <span className="product-cell-fallback">{value?.slice(0, 1)}</span>}
                    <div>
                        <strong>{value}</strong>
                        <small>{row.generic_name || row.product_code || row.sku}</small>
                    </div>
                </div>
            ),
        },
        { title: 'Code', dataIndex: 'product_code', field: 'product_code', sorter: true, width: 130, render: (value, row) => value || row.sku || '-' },
        { title: 'HS Code', dataIndex: 'hs_code', width: 120, render: (value) => value || '-' },
        { title: 'Company', dataIndex: ['company', 'name'], width: 170, render: (value) => value || '-' },
        { title: 'Division', dataIndex: ['division', 'name'], width: 150, render: (value) => value || '-' },
        { title: 'Packaging', dataIndex: 'packaging_type', width: 140, render: (value) => value || '-' },
        { title: 'Unit', dataIndex: ['unit', 'name'], width: 100 },
        { title: 'Reorder Level', dataIndex: 'reorder_level', field: 'reorder_level', sorter: true, align: 'right', width: 130 },
        { title: 'Stock Qty', dataIndex: 'stock_on_hand', field: 'stock_on_hand', sorter: true, align: 'right', width: 120 },
        { title: 'MRP', dataIndex: 'mrp', field: 'mrp', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
        { title: 'CC Rate', dataIndex: 'cc_rate', align: 'right', width: 110, render: (value) => `${Number(value || 0).toFixed(2)}%` },
        { title: 'Status', dataIndex: 'is_active', width: 150, render: (value, row) => row.deleted_at ? <StatusTag active={false} falseText="Deleted" /> : <StatusToggle value={value} id={row.id} endpoint={endpoints.products} /> },
        {
            title: 'Action',
            key: 'actions',
            fixed: 'right',
            width: 190,
            render: (_, record) => (
                record.deleted_at ? (
                    <Button aria-label="Restore" loading={actionId === record.id} disabled={actionId === record.id} icon={<UndoOutlined />} onClick={() => onRestore(record)}>Restore</Button>
                ) : (
                    <Space>
                        <Button aria-label="History" icon={<HistoryOutlined />} onClick={() => onHistory(record)} />
                        <Button aria-label="Print Barcode" icon={<BarcodeOutlined />} onClick={() => onPrintBarcode(record)} />
                        <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => onEdit(record)} />
                        <Button aria-label="Delete" danger loading={actionId === record.id} disabled={actionId === record.id} icon={<DeleteOutlined />} onClick={() => onDelete(record)} />
                    </Space>
                )
            ),
        },
    ];
}

/**
 * Expanded row render for the product table showing additional detail.
 *
 * @param {object} record - Product record.
 * @returns {JSX.Element}
 */
export function ProductExpandedRow({ record }) {
    const stock = Number(record.stock_on_hand || 0);
    const reorder = Number(record.reorder_level || 0);
    const margin = Number(record.selling_price || 0) - Number(record.purchase_price || 0);

    return (
        <div className="expanded-summary-grid">
            <div>
                <span>Division</span>
                <strong>{record.division?.name || 'Unassigned'}</strong>
                <small>{record.division?.code || 'No division code'}</small>
            </div>
            <div>
                <span>Manufacturer</span>
                <strong>{record.manufacturer_name || record.company?.name || '-'}</strong>
                <small>{record.group_name || record.generic_name || 'No group/generic set'}</small>
            </div>
            <div>
                <span>HS / Packaging</span>
                <strong>{record.hs_code || '-'}</strong>
                <small>{record.packaging_type || '-'}</small>
            </div>
            <div>
                <span>Stock Health</span>
                <strong>{stock.toLocaleString()} available</strong>
                <small>{stock <= reorder ? 'Below or near reorder level' : 'Healthy against reorder level'}</small>
            </div>
            <div>
                <span>Pricing</span>
                <strong><Money value={record.selling_price} /></strong>
                <small>Margin <Money value={margin} /></small>
            </div>
            <div>
                <span>Notes</span>
                <strong>{record.notes || 'No notes'}</strong>
                <small>{record.group_name || record.generic_name || 'No group set'}</small>
            </div>
        </div>
    );
}
