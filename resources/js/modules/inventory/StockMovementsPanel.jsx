import React, { useEffect, useState } from 'react';
import { Button, Card, Input, Select, Statistic } from 'antd';
import { ReloadOutlined } from '@ant-design/icons';
import { DateText } from '../../core/components/DateText';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';

const movementTypes = [
    { value: 'purchase_receive', label: 'Purchase Receive' },
    { value: 'purchase_return_out', label: 'Purchase Return Out' },
    { value: 'sales_out', label: 'Sales Out' },
    { value: 'adjustment_in', label: 'Adjustment In' },
    { value: 'adjustment_out', label: 'Adjustment Out' },
    { value: 'manual_batch_in', label: 'Manual Batch In' },
    { value: 'manual_batch_out', label: 'Manual Batch Out' },
];

export function StockMovementsPanel() {
    const [products, setProducts] = useState([]);
    const [range, setRange] = useState([]);
    const table = useServerTable({
        endpoint: endpoints.stockMovements,
        defaultSort: { field: 'movement_date', order: 'desc' },
    });

    useEffect(() => {
        searchProducts('');
    }, []);

    useEffect(() => {
        table.setFilters((filters) => applyDateRangeFilter(filters, range));
    }, [range]);

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProducts(data.data || []);
    }

    const columns = [
        { title: 'Date', dataIndex: 'movement_date', field: 'movement_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Product', dataIndex: ['product', 'name'], width: 260 },
        { title: 'Batch', dataIndex: ['batch', 'batch_no'], width: 150 },
        { title: 'Movement', dataIndex: 'movement_type', field: 'movement_type', sorter: true, width: 180, render: (value) => <PharmaBadge tone={String(value || '').includes('_out') ? 'warning' : 'info'}>{String(value || '').replaceAll('_', ' ')}</PharmaBadge> },
        { title: 'In', dataIndex: 'quantity_in', field: 'quantity_in', sorter: true, align: 'right', width: 110 },
        { title: 'Out', dataIndex: 'quantity_out', field: 'quantity_out', sorter: true, align: 'right', width: 110 },
        { title: 'Reference', width: 180, render: (_, row) => row.reference_type ? `${row.reference_type} #${row.reference_id}` : '-' },
        { title: 'Notes', dataIndex: 'notes', width: 300 },
    ];

    const summary = table.extra.summary || {};

    return (
        <div className="page-stack">
            <Card title="Stock Movement Ledger">
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search movement, product, batch or note" allowClear />
                    <Select
                        allowClear
                        showSearch
                        filterOption={false}
                        onSearch={searchProducts}
                        placeholder="Product"
                        options={products.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(product_id) => table.setFilters((filters) => ({ ...filters, product_id }))}
                    />
                    <Select
                        allowClear
                        placeholder="Movement"
                        options={movementTypes}
                        onChange={(movement_type) => table.setFilters((filters) => ({ ...filters, movement_type }))}
                    />
                    <SmartDatePicker.RangePicker value={range} onChange={setRange} />
                    <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                </div>
                <ServerTable table={table} columns={columns} />
            </Card>
        </div>
    );
}
