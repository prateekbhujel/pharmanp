import React, { useEffect, useMemo, useState } from 'react';
import { Button, Card, Col, Empty, Row, Segmented, Select, Space, Statistic, Table, Tabs } from 'antd';
import { DateText } from '../../core/components/DateText';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { BarChart } from '../../core/components/Charts';
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
    { value: 'expiry-buckets', label: 'Expiry buckets' },
    { value: 'dumping', label: 'Dumping / slow moving' },
    { value: 'smart-inventory', label: 'Smart inventory signals' },
    { value: 'supplier-performance', label: 'Supplier performance' },
    { value: 'supplier-aging', label: 'Supplier aging' },
    { value: 'customer-aging', label: 'Customer aging' },
    { value: 'supplier-ledger', label: 'Supplier ledger' },
    { value: 'customer-ledger', label: 'Customer ledger' },
    { value: 'product-movement', label: 'Product movement' },
    { value: 'mr-performance', label: 'MR performance' },
    { value: 'mr-vs-product', label: 'MR vs product' },
    { value: 'mr-vs-division', label: 'MR vs division' },
    { value: 'mr-vs-sales', label: 'MR vs sales' },
    { value: 'company-vs-customer', label: 'Company vs customer' },
    { value: 'target-achievement', label: 'Target achievement' },
    { value: 'day-book', label: 'Day book' },
    { value: 'cash-book', label: 'Cash book' },
    { value: 'bank-book', label: 'Bank book' },
    { value: 'ledger', label: 'Account ledger' },
    { value: 'account-tree', label: 'Account tree' },
    { value: 'trial-balance', label: 'Trial balance' },
    { value: 'profit-loss', label: 'Profit & loss' },
];

const reportGroups = {
    sales: ['sales', 'purchase', 'supplier-performance', 'supplier-aging', 'customer-aging', 'company-vs-customer'],
    inventory: ['stock', 'low-stock', 'expiry', 'expiry-buckets', 'dumping', 'smart-inventory', 'product-movement'],
    accounting: ['day-book', 'cash-book', 'bank-book', 'ledger', 'account-tree', 'trial-balance', 'profit-loss', 'supplier-ledger', 'customer-ledger'],
    mr: ['mr-performance', 'mr-vs-product', 'mr-vs-division', 'mr-vs-sales', 'target-achievement'],
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

function reportRowKey(record) {
    const key = record.id
        ?? record.code
        ?? record.invoice_no
        ?? record.purchase_no
        ?? record.reference
        ?? record.voucher_no
        ?? record.account_key
        ?? [
            record.section,
            record.account,
            record.product,
            record.customer,
            record.supplier,
            record.date,
            record.movement_date,
        ].filter(Boolean).join('-');

    return key || JSON.stringify(record).slice(0, 80);
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
    const [meta, setMeta] = useState({ current_page: 1, per_page: 15, total: 0 });
    const [loading, setLoading] = useState(false);
    const [reportView, setReportView] = useState('table');
    const [productOptions, setProductOptions] = useState([]);
    const [lookups, setLookups] = useState({ suppliers: [], customers: [], medicalRepresentatives: [], companies: [], categories: [], divisions: [], areas: [] });

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
            const [{ data: supplierData }, { data: customerData }, { data: mrData }, { data: productMeta }, { data: divisionData }, { data: areaData }] = await Promise.all([
                http.get(endpoints.supplierOptions),
                http.get(endpoints.customerOptions),
                http.get(endpoints.mrOptions),
                http.get(endpoints.productMeta),
                http.get(endpoints.setupDivisionOptions),
                http.get(endpoints.setupAreaOptions),
            ]);

            setLookups({
                suppliers: supplierData.data || [],
                customers: customerData.data || [],
                medicalRepresentatives: mrData.data || [],
                companies: productMeta.data?.companies || [],
                categories: productMeta.data?.categories || [],
                divisions: divisionData.data || [],
                areas: areaData.data || [],
            });
        } catch {
            setLookups({ suppliers: [], customers: [], medicalRepresentatives: [], companies: [], categories: [], divisions: [], areas: [] });
        }
    }

    async function load(page = 1, pageSize = meta.per_page) {
        setLoading(true);
        try {
            const { data } = await http.get(`${endpoints.reports}/${report}`, {
                params: {
                    page,
                    per_page: pageSize,
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
        align: typeof rows[0]?.[key] === 'number' ? 'right' : undefined,
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

    function renderFilters() {
        return (
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
                {report === 'company-vs-customer' && (
                    <Select {...searchableSelectProps} allowClear placeholder="Company / Manufacturer" value={filters.manufacturer_id} onChange={(value) => updateFilter('manufacturer_id', value)} options={lookups.companies.map((item) => ({ value: item.id, label: item.name }))} />
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
                {report === 'target-achievement' && (
                    <>
                        <Select {...searchableSelectProps} allowClear placeholder="Target type" value={filters.target_type} onChange={(value) => updateFilter('target_type', value)} options={[
                            { value: 'primary', label: 'Primary' },
                            { value: 'secondary', label: 'Secondary' },
                        ]} />
                        <Select {...searchableSelectProps} allowClear placeholder="Target period" value={filters.target_period} onChange={(value) => updateFilter('target_period', value)} options={[
                            { value: 'monthly', label: 'Monthly' },
                            { value: 'quarterly', label: 'Quarterly' },
                            { value: 'annual', label: 'Annual' },
                        ]} />
                        <Select {...searchableSelectProps} allowClear placeholder="Target level" value={filters.target_level} onChange={(value) => updateFilter('target_level', value)} options={[
                            { value: 'company', label: 'Company' },
                            { value: 'division', label: 'Division' },
                            { value: 'area', label: 'Area' },
                            { value: 'employee', label: 'MR / Employee' },
                            { value: 'product', label: 'Product' },
                        ]} />
                    </>
                )}
                {['supplier-ledger', 'supplier-aging', 'expiry-buckets'].includes(report) && <Select {...searchableSelectProps} allowClear placeholder="Supplier" value={filters.supplier_id} onChange={(value) => updateFilter('supplier_id', value)} options={lookups.suppliers.map((item) => ({ value: item.id, label: item.name }))} />}
                {['customer-ledger', 'customer-aging', 'company-vs-customer'].includes(report) && <Select {...searchableSelectProps} allowClear placeholder="Customer" value={filters.customer_id} onChange={(value) => updateFilter('customer_id', value)} options={lookups.customers.map((item) => ({ value: item.id, label: item.name }))} />}
                {['expiry-buckets', 'dumping', 'mr-vs-division', 'target-achievement'].includes(report) && <Select {...searchableSelectProps} allowClear placeholder="Division" value={filters.division_id} onChange={(value) => updateFilter('division_id', value)} options={lookups.divisions.map((item) => ({ value: item.id, label: item.code ? `${item.name} (${item.code})` : item.name }))} />}
                {['mr-vs-sales', 'target-achievement'].includes(report) && <Select {...searchableSelectProps} allowClear placeholder="Area" value={filters.area_id} onChange={(value) => updateFilter('area_id', value)} options={lookups.areas.map((item) => ({ value: item.id, label: item.code ? `${item.name} (${item.code})` : item.name }))} />}
                {['expiry-buckets'].includes(report) && (
                    <Select
                        {...searchableSelectProps}
                        allowClear
                        placeholder="Expiry bucket"
                        value={filters.bucket}
                        onChange={(value) => updateFilter('bucket', value)}
                        options={[
                            { value: 'expired', label: 'Expired' },
                            { value: '30', label: 'Within 30 days' },
                            { value: '60', label: '31 to 60 days' },
                            { value: '90', label: '61 to 90 days' },
                        ]}
                    />
                )}
                {['mr-vs-product', 'mr-vs-sales', 'mr-performance'].includes(report) && <Select {...searchableSelectProps} allowClear placeholder="MR" value={filters.medical_representative_id} onChange={(value) => updateFilter('medical_representative_id', value)} options={lookups.medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))} />}
                {['product-movement', 'expiry-buckets', 'dumping', 'mr-vs-product', 'target-achievement'].includes(report) && (
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
        );
    }

    function renderSummaryCards() {
        if (!summary) {
            return null;
        }

        return (
            <Row gutter={[16, 16]}>
                {Object.entries(summary).map(([key, value]) => (
                    <Col xs={24} sm={12} xl={6} key={key}>
                        <Card size="small"><Statistic title={labelForKey(key)} value={value || 0} /></Card>
                    </Col>
                ))}
            </Row>
        );
    }

    function renderCharts() {
        return (
            <Row gutter={[16, 16]}>
                <Col xs={24}>
                    <Card title="Trend and comparison">
                        {chartData ? (
                            <BarChart
                                data={chartData}
                                height={320}
                                legend={chartData[0]?.bars?.length > 1 ? ['Primary', 'Secondary'] : undefined}
                                colors={['#0891b2', '#10b981']}
                            />
                        ) : (
                            <Empty description="No chartable rows for this report yet" />
                        )}
                    </Card>
                </Col>
            </Row>
        );
    }

    function renderTable() {
        const serialColumn = {
            title: 'SN',
            key: '__serial',
            width: 68,
            align: 'center',
            className: 'table-serial-cell',
            render: (_, __, index) => ((meta.current_page - 1) * meta.per_page) + index + 1,
        };

        return (
            <Table
                loading={loading}
                rowKey={reportRowKey}
                columns={[serialColumn, ...columns]}
                dataSource={rows}
                pagination={{
                    current: meta.current_page,
                    pageSize: meta.per_page,
                    total: meta.total,
                    showSizeChanger: true,
                    pageSizeOptions: ['10', '15', '20', '25', '50', '100'],
                    onChange: load,
                }}
                scroll={{ x: true }}
            />
        );
    }

    return (
        <div className="page-stack">
            <PageHeader
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

            <Card className="report-view-card">
                <Tabs
                    activeKey={reportView}
                    onChange={setReportView}
                    items={[
                        {
                            key: 'table',
                            label: 'Table',
                            children: (
                                <div className="report-pane">
                                    {renderFilters()}
                                    {renderTable()}
                                </div>
                            ),
                        },
                        {
                            key: 'charts',
                            label: 'Analysis',
                            children: (
                                <div className="report-pane">
                                    {renderSummaryCards()}
                                    {renderCharts()}
                                </div>
                            ),
                        },
                    ]}
                />
            </Card>
        </div>
    );
}
