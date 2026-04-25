import React, { useEffect, useState } from 'react';
import { Card, DatePicker, Select, Table } from 'antd';
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
];

export function ReportsPage() {
    const [report, setReport] = useState('sales');
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, per_page: 20, total: 0 });
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        load(1);
    }, [report, range]);

    async function load(page = 1) {
        setLoading(true);
        try {
            const { data } = await http.get(`${endpoints.reports}/${report}`, {
                params: {
                    page,
                    per_page: meta.per_page,
                    from: range?.[0]?.format('YYYY-MM-DD'),
                    to: range?.[1]?.format('YYYY-MM-DD'),
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

    return (
        <div className="page-stack">
            <PageHeader title="Reports" description="Server-side filtered operational reports" />
            <Card>
                <div className="table-toolbar">
                    <Select value={report} onChange={setReport} options={reportOptions} />
                    <DatePicker.RangePicker value={range} onChange={setRange} />
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
