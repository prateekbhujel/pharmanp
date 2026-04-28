import React, { useEffect, useMemo, useState } from 'react';
import { Button, Card, Col, Empty, Row, Segmented, Select, Space, Statistic, Table } from 'antd';
import { DateText } from '../../core/components/DateText';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { BarChart, DonutChart } from '../../core/components/Charts';
import { ExportButtons } from '../../core/components/ListToolbarActions';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { accountCatalog, paymentStatusOptions } from '../../core/utils/accountCatalog';
import { isLikelyDateValue } from '../../core/utils/calendar';
import { dateRangeParams } from '../../core/utils/dateFilters';
import { appUrl } from '../../core/utils/url';

const reportOptions = [
    { value: 'sales', label: 'Sales report' },
    { value: 'purchase', label: 'Purchase report' },
    { value: 'stock', label: 'Stock report' },
    { value: 'low-stock', label: 'Low stock report' },
    { value: 'expiry', label: 'Expiry report' },
    { value: 'smart-inventory', label: 'Smart inventory signals' },
    { value: 'supplier-performance', label: 'Supplier performance' },
    { value: 'supplier-ledger', label: 'Supplier ledger' },
    { value: 'customer-ledger', label: 'Customer ledger' },
    { value: 'product-movement', label: 'Product movement' },
    { value: 'mr-performance', label: 'MR performance' },
    { value: 'day-book', label: 'Day book' },
    { value: 'cash-book', label: 'Cash book' },
    { value: 'bank-book', label: 'Bank book' },
    { value: 'ledger', label: 'Account ledger' },
    { value: 'account-tree', label: 'Account tree' },
    { value: 'trial-balance', label: 'Trial balance' },
];

const reportGroups = {
    sales: ['sales', 'purchase', 'supplier-performance'],
    inventory: ['stock', 'low-stock', 'expiry', 'smart-inventory', 'product-movement'],
    accounting: ['day-book', 'cash-book', 'bank-book', 'ledger', 'account-tree', 'trial-balance', 'supplier-ledger', 'customer-ledger'],
    mr: ['mr-performance'],
};

const searchableSelectProps = {
    showSearch: true,
    optionFilterProp: 'label',
};

function reportFromPath() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    if (reportOptions.some((item) => item.value === section)) {
        return section;
    }

    return ({
        sales: 'sales',
        purchase: 'purchase',
        stock: 'stock',
        expiry: 'expiry',
        accounting: 'day-book',
        inventory: 'stock',
    })[section] || 'sales';
}

function routeForReport(report) {
    return appUrl(`/app/reports/${report}`);
}

function inferGroup(report) {
    return Object.entries(reportGroups).find(([, values]) => values.includes(report))?.[0] || 'sales';
}

function labelForKey(key) {
    return key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function inferChart(rows, report) {
    if (!rows.length) {
        return null;
    }

    const first = rows[0];
    const keys = Object.keys(first);
    const numericKeys = keys.filter((key) => typeof first[key] === 'number');
    const labelKey = ['name', 'product', 'customer', 'supplier', 'reference', 'invoice_no', 'purchase_no', 'movement_date', 'date']
        .find((key) => key in first) || keys.find((key) => typeof first[key] === 'string');

    if (!labelKey || !numericKeys.length) {
        return null;
    }

    const selectedKeys = report === 'product-movement'
        ? numericKeys.filter((key) => ['quantity_in', 'quantity_out'].includes(key)).slice(0, 2)
        : numericKeys.slice(0, 2);

    return rows.slice(0, 8).map((row) => ({
        label: String(row[labelKey] ?? '-').slice(0, 16),
        bars: selectedKeys.map((key, index) => ({
            value: Number(row[key] || 0),
            color: index === 0 ? '#0891b2' : '#10b981',
        })),
    }));
}

export function ReportsPage() {
    const initialReport = reportFromPath();
    const [report, setReport] = useState(initialReport);
    const [group, setGroup] = useState(inferGroup(initialReport));
    const [range, setRange] = useState([]);
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

    useEffect(() => {
        const nextGroup = inferGroup(report);
        if (nextGroup !== group) {
            setGroup(nextGroup);
        }
    }, [group, report]);

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
                    ...dateRangeParams(range),
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
        title: labelForKey(key),
        dataIndex: key,
        render: (value) => {
            if (typeof value === 'number') {
                return value.toLocaleString();
            }

            if (isLikelyDateValue(key, value)) {
                return <DateText value={value} style="compact" />;
            }

            return value;
        },
    })), [rows]);
    const visibleReportOptions = useMemo(
        () => reportOptions.filter((item) => (reportGroups[group] || []).includes(item.value)),
        [group],
    );
    const chartData = useMemo(() => inferChart(rows, report), [report, rows]);
    const summaryChartData = useMemo(() => {
        if (!summary) return [];

        return Object.entries(summary)
            .filter(([, value]) => typeof value === 'number' && Number(value) > 0)
            .slice(0, 5)
            .map(([key, value], index) => ({
                label: labelForKey(key),
                value: Number(value || 0),
                color: ['#0891b2', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'][index],
            }));
    }, [summary]);

    function updateFilter(name, value) {
        setFilters((current) => ({ ...current, [name]: value || undefined }));
    }

    function switchReport(nextReport) {
        setReport(nextReport);
        setFilters({});
        window.history.pushState({}, '', routeForReport(nextReport));
        window.dispatchEvent(new PopStateEvent('popstate'));
    }

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProductOptions((data.data || []).map((item) => ({ value: item.id, label: item.name })));
    }

    return (
        <div className="page-stack">
            <PageHeader
                title="Reports"
                actions={
                    <Space wrap>
                        <ExportButtons basePath={endpoints.reportExport(report)} params={{ ...dateRangeParams(range), ...filters }} />
                        {report === 'day-book' && (
                            <Button type="primary" onClick={() => window.location.href = appUrl('/app/accounting/vouchers')}>New Voucher</Button>
                        )}
                    </Space>
                }
            />

            <Card>
                <div className="report-workspace-toolbar">
                    <div className="reports-pill-tabs">
                        <Segmented
                            value={group}
                            onChange={(value) => {
                                const nextReport = reportGroups[value][0];
                                setGroup(value);
                                switchReport(nextReport);
                            }}
                            options={[
                                { label: 'Sales & Purchase', value: 'sales' },
                                { label: 'Inventory', value: 'inventory' },
                                { label: 'Accounting', value: 'accounting' },
                                { label: 'MR', value: 'mr' },
                            ]}
                        />
                    </div>
                    <Select
                        {...searchableSelectProps}
                        value={report}
                        onChange={switchReport}
                        options={visibleReportOptions}
                        style={{ minWidth: 220 }}
                    />
                    <SmartDatePicker.RangePicker value={range} onChange={setRange} />
                </div>
            </Card>

            {summary && (
                <Row gutter={[16, 16]}>
                    {Object.entries(summary).map(([key, value]) => (
                        <Col xs={24} sm={12} xl={6} key={key}>
                            <Card size="small"><Statistic title={labelForKey(key)} value={value || 0} /></Card>
                        </Col>
                    ))}
                </Row>
            )}

            <Row gutter={[16, 16]}>
                <Col xs={24} xl={14}>
                    <Card title="Report Snapshot">
                        {chartData ? (
                            <BarChart
                                data={chartData}
                                height={300}
                                legend={chartData[0]?.bars?.length > 1 ? ['Primary', 'Secondary'] : undefined}
                                colors={['#0891b2', '#10b981']}
                            />
                        ) : (
                            <Empty description="No chartable rows for this report yet" />
                        )}
                    </Card>
                </Col>
                <Col xs={24} xl={10}>
                    <Card title="Summary Mix">
                        {summaryChartData.length ? (
                            <DonutChart data={summaryChartData} size={220} />
                        ) : (
                            <Empty description="Summary totals will appear here" />
                        )}
                    </Card>
                </Col>
            </Row>

            <Card>
                <div className="report-filter-grid">
                    {report === 'sales' && (
                        <>
                            <Select {...searchableSelectProps} allowClear placeholder="Customer" value={filters.customer_id} onChange={(value) => updateFilter('customer_id', value)} options={lookups.customers.map((item) => ({ value: item.id, label: item.name }))} />
                            <Select {...searchableSelectProps} allowClear placeholder="Payment" value={filters.payment_status} onChange={(value) => updateFilter('payment_status', value)} options={paymentStatusOptions} />
                            <Select {...searchableSelectProps} allowClear placeholder="MR" value={filters.medical_representative_id} onChange={(value) => updateFilter('medical_representative_id', value)} options={lookups.medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))} />
                        </>
                    )}
                    {report === 'purchase' && (
                        <>
                            <Select {...searchableSelectProps} allowClear placeholder="Supplier" value={filters.supplier_id} onChange={(value) => updateFilter('supplier_id', value)} options={lookups.suppliers.map((item) => ({ value: item.id, label: item.name }))} />
                            <Select {...searchableSelectProps} allowClear placeholder="Payment" value={filters.payment_status} onChange={(value) => updateFilter('payment_status', value)} options={paymentStatusOptions} />
                        </>
                    )}
                    {['stock', 'low-stock', 'smart-inventory'].includes(report) && (
                        <>
                            <Select {...searchableSelectProps} allowClear placeholder="Company" value={filters.company_id} onChange={(value) => updateFilter('company_id', value)} options={lookups.companies.map((item) => ({ value: item.id, label: item.name }))} />
                            <Select {...searchableSelectProps} allowClear placeholder="Category" value={filters.category_id} onChange={(value) => updateFilter('category_id', value)} options={lookups.categories.map((item) => ({ value: item.id, label: item.name }))} />
                        </>
                    )}
                    {report === 'smart-inventory' && (
                        <Select
                            {...searchableSelectProps}
                            allowClear
                            placeholder="Signal"
                            value={filters.signal}
                            onChange={(value) => updateFilter('signal', value)}
                            options={[
                                { value: 'urgent_reorder', label: 'Urgent reorder' },
                                { value: 'reorder_soon', label: 'Reorder soon' },
                                { value: 'overstock', label: 'Overstock' },
                                { value: 'expiry_urgent', label: 'Expiry urgent' },
                                { value: 'expiry_watch', label: 'Expiry watch' },
                                { value: 'fast_moving', label: 'Fast moving' },
                                { value: 'slow_moving', label: 'Slow moving' },
                            ]}
                        />
                    )}
                    {report === 'supplier-ledger' && <Select {...searchableSelectProps} allowClear placeholder="Supplier" value={filters.supplier_id} onChange={(value) => updateFilter('supplier_id', value)} options={lookups.suppliers.map((item) => ({ value: item.id, label: item.name }))} />}
                    {report === 'customer-ledger' && <Select {...searchableSelectProps} allowClear placeholder="Customer" value={filters.customer_id} onChange={(value) => updateFilter('customer_id', value)} options={lookups.customers.map((item) => ({ value: item.id, label: item.name }))} />}
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
                            <Select {...searchableSelectProps} allowClear placeholder="Account" value={filters.account_type} onChange={(value) => updateFilter('account_type', value)} options={accountCatalog} />
                            <Select {...searchableSelectProps} allowClear placeholder="Party Type" value={filters.party_type} onChange={(value) => updateFilter('party_type', value)} options={[
                                { value: 'customer', label: 'Customer' },
                                { value: 'supplier', label: 'Supplier' },
                            ]} />
                            {filters.party_type === 'customer' && <Select {...searchableSelectProps} allowClear placeholder="Customer" value={filters.party_id} onChange={(value) => updateFilter('party_id', value)} options={lookups.customers.map((item) => ({ value: item.id, label: item.name }))} />}
                            {filters.party_type === 'supplier' && <Select {...searchableSelectProps} allowClear placeholder="Supplier" value={filters.party_id} onChange={(value) => updateFilter('party_id', value)} options={lookups.suppliers.map((item) => ({ value: item.id, label: item.name }))} />}
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
