import React, { useEffect, useState } from 'react';
import { Card, DatePicker, Input, InputNumber, Select, Table } from 'antd';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';

const reportOptions = [
    { value: 'sales', label: 'Sales report' },
    { value: 'purchase', label: 'Purchase report' },
    { value: 'stock', label: 'Stock report' },
    { value: 'low-stock', label: 'Low stock report' },
    { value: 'expiry', label: 'Expiry report' },
    { value: 'supplier-performance', label: 'Supplier performance' },
    { value: 'supplier-ledger', label: 'Supplier ledger' },
    { value: 'customer-ledger', label: 'Customer ledger' },
    { value: 'product-movement', label: 'Product movement' },
    { value: 'day-book', label: 'Day book' },
    { value: 'cash-book', label: 'Cash book' },
    { value: 'bank-book', label: 'Bank book' },
    { value: 'ledger', label: 'Account ledger' },
];

export function ReportsPage() {
    const [report, setReport] = useState('sales');
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [filters, setFilters] = useState({});
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, per_page: 20, total: 0 });
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        load(1);
    }, [report, range, filters]);

    async function load(page = 1) {
        setLoading(true);
        try {
            const { data } = await http.get(`${endpoints.reports}/${report}`, {
                params: {
                    page,
                    per_page: meta.per_page,
                    from: range?.[0]?.format('YYYY-MM-DD'),
                    to: range?.[1]?.format('YYYY-MM-DD'),
                    ...filters,
                },
            });
            setRows(data.data || []);
            setMeta(data.meta || meta);
        } finally {
            setLoading(false);
        }
    }

    const columns = Object.keys(rows[0] || {}).map((key) => ({
        title: key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()),
        dataIndex: key,
        render: (value) => typeof value === 'number' ? value.toLocaleString() : value,
    }));

    function updateFilter(name, value) {
        setFilters((current) => ({ ...current, [name]: value || undefined }));
    }

    return (
        <div className="page-stack">
            <PageHeader title="Reports" description="Server-side filtered operational reports" />
            <Card>
                <div className="table-toolbar">
                    <Select
                        value={report}
                        onChange={(value) => {
                            setReport(value);
                            setFilters({});
                        }}
                        options={reportOptions}
                    />
                    <DatePicker.RangePicker value={range} onChange={setRange} />
                    {report === 'supplier-ledger' && <InputNumber min={1} placeholder="Supplier ID" value={filters.supplier_id} onChange={(value) => updateFilter('supplier_id', value)} />}
                    {report === 'customer-ledger' && <InputNumber min={1} placeholder="Customer ID" value={filters.customer_id} onChange={(value) => updateFilter('customer_id', value)} />}
                    {report === 'product-movement' && <InputNumber min={1} placeholder="Product ID" value={filters.product_id} onChange={(value) => updateFilter('product_id', value)} />}
                    {report === 'ledger' && <Input placeholder="Account type" value={filters.account_type} onChange={(event) => updateFilter('account_type', event.target.value)} />}
                    <span />
                </div>
                <Table
                    loading={loading}
                    rowKey={(_, index) => index}
                    columns={columns}
                    dataSource={rows}
                    pagination={{
                        current: meta.current_page,
                        pageSize: meta.per_page,
                        total: meta.total,
                        onChange: load,
                    }}
                    scroll={{ x: true }}
                />
            </Card>
        </div>
    );
}
