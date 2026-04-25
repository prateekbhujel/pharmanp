import React, { useEffect, useMemo, useState } from 'react';
import { Card, Col, DatePicker, Input, Row, Select, Statistic, Table } from 'antd';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { accountCatalog, paymentStatusOptions } from '../../core/utils/accountCatalog';

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
    { value: 'mr-performance', label: 'MR performance' },
    { value: 'day-book', label: 'Day book' },
    { value: 'cash-book', label: 'Cash book' },
    { value: 'bank-book', label: 'Bank book' },
    { value: 'ledger', label: 'Account ledger' },
    { value: 'trial-balance', label: 'Trial balance' },
];

export function ReportsPage() {
    const [report, setReport] = useState('sales');
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [filters, setFilters] = useState({});
    const [rows, setRows] = useState([]);
    const [summary, setSummary] = useState(null);
    const [meta, setMeta] = useState({ current_page: 1, per_page: 20, total: 0 });
    const [loading, setLoading] = useState(false);
    const [productOptions, setProductOptions] = useState([]);
    const [lookups, setLookups] = useState({ suppliers: [], customers: [], medicalRepresentatives: [], companies: [], categories: [] });

    useEffect(() => {
        loadLookups();
    }, []);

    useEffect(() => {
        load(1);
    }, [report, range, filters]);

    async function loadLookups() {
        try {
            const [{ data: supplierData }, { data: customerData }, { data: mrData }, { data: productMeta }] = await Promise.all([
                http.get(endpoints.supplierOptions),
                http.get(endpoints.customerOptions),
                http.get(endpoints.mrOptions),
                http.get(endpoints.productMeta),
            ]);

            setLookups({
                suppliers: supplierData.data || [],
                customers: customerData.data || [],
                medicalRepresentatives: mrData.data || [],
                companies: productMeta.data?.companies || [],
                categories: productMeta.data?.categories || [],
            });
        } catch {
            setLookups({ suppliers: [], customers: [], medicalRepresentatives: [], companies: [], categories: [] });
        }
    }

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
            setSummary(data.summary || null);
        } finally {
            setLoading(false);
        }
    }

    const columns = useMemo(() => Object.keys(rows[0] || {}).map((key) => ({
        title: key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()),
        dataIndex: key,
        render: (value) => typeof value === 'number' ? value.toLocaleString() : value,
    })), [rows]);

    function updateFilter(name, value) {
        setFilters((current) => ({ ...current, [name]: value || undefined }));
    }

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProductOptions((data.data || []).map((item) => ({ value: item.id, label: item.name })));
    }

    return (
        <div className="page-stack">
            <PageHeader title="Reports" description="Server-side filtered operational, accounting and MR reports" />

            {summary && (
                <Row gutter={[16, 16]}>
                    {Object.entries(summary).map(([key, value]) => (
                        <Col xs={24} sm={12} xl={6} key={key}>
                            <Card><Statistic title={key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())} value={value || 0} /></Card>
                        </Col>
                    ))}
                </Row>
            )}

            <Card>
                <div className="report-filter-grid">
                    <Select
                        value={report}
                        onChange={(value) => {
                            setReport(value);
                            setFilters({});
                        }}
                        options={reportOptions}
                    />
                    <DatePicker.RangePicker value={range} onChange={setRange} />
                    {report === 'sales' && (
                        <>
                            <Select allowClear placeholder="Customer" value={filters.customer_id} onChange={(value) => updateFilter('customer_id', value)} options={lookups.customers.map((item) => ({ value: item.id, label: item.name }))} />
                            <Select allowClear placeholder="Payment" value={filters.payment_status} onChange={(value) => updateFilter('payment_status', value)} options={paymentStatusOptions} />
                            <Select allowClear placeholder="MR" value={filters.medical_representative_id} onChange={(value) => updateFilter('medical_representative_id', value)} options={lookups.medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))} />
                        </>
                    )}
                    {report === 'purchase' && (
                        <>
                            <Select allowClear placeholder="Supplier" value={filters.supplier_id} onChange={(value) => updateFilter('supplier_id', value)} options={lookups.suppliers.map((item) => ({ value: item.id, label: item.name }))} />
                            <Select allowClear placeholder="Payment" value={filters.payment_status} onChange={(value) => updateFilter('payment_status', value)} options={paymentStatusOptions} />
                        </>
                    )}
                    {(report === 'stock' || report === 'low-stock') && (
                        <>
                            <Select allowClear placeholder="Company" value={filters.company_id} onChange={(value) => updateFilter('company_id', value)} options={lookups.companies.map((item) => ({ value: item.id, label: item.name }))} />
                            <Select allowClear placeholder="Category" value={filters.category_id} onChange={(value) => updateFilter('category_id', value)} options={lookups.categories.map((item) => ({ value: item.id, label: item.name }))} />
                        </>
                    )}
                    {report === 'supplier-ledger' && <Select allowClear placeholder="Supplier" value={filters.supplier_id} onChange={(value) => updateFilter('supplier_id', value)} options={lookups.suppliers.map((item) => ({ value: item.id, label: item.name }))} />}
                    {report === 'customer-ledger' && <Select allowClear placeholder="Customer" value={filters.customer_id} onChange={(value) => updateFilter('customer_id', value)} options={lookups.customers.map((item) => ({ value: item.id, label: item.name }))} />}
                    {report === 'product-movement' && (
                        <Select
                            showSearch
                            allowClear
                            filterOption={false}
                            placeholder="Product"
                            value={filters.product_id}
                            onSearch={searchProducts}
                            onFocus={() => searchProducts('')}
                            onChange={(value) => updateFilter('product_id', value)}
                            options={productOptions}
                        />
                    )}
                    {report === 'ledger' && (
                        <>
                            <Select allowClear placeholder="Account" value={filters.account_type} onChange={(value) => updateFilter('account_type', value)} options={accountCatalog} />
                            <Select allowClear placeholder="Party Type" value={filters.party_type} onChange={(value) => updateFilter('party_type', value)} options={[
                                { value: 'customer', label: 'Customer' },
                                { value: 'supplier', label: 'Supplier' },
                            ]} />
                            {filters.party_type === 'customer' && <Select allowClear placeholder="Customer" value={filters.party_id} onChange={(value) => updateFilter('party_id', value)} options={lookups.customers.map((item) => ({ value: item.id, label: item.name }))} />}
                            {filters.party_type === 'supplier' && <Select allowClear placeholder="Supplier" value={filters.party_id} onChange={(value) => updateFilter('party_id', value)} options={lookups.suppliers.map((item) => ({ value: item.id, label: item.name }))} />}
                        </>
                    )}
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
