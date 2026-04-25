import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Col, DatePicker, Empty, Row, Select, Space, Statistic, Table, Tag, Typography } from 'antd';
import dayjs from 'dayjs';
import {
    AlertOutlined,
    DollarCircleOutlined,
    FallOutlined,
    LineChartOutlined,
    ReloadOutlined,
    RiseOutlined,
    ShoppingCartOutlined,
    TeamOutlined,
} from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useAuth } from '../../core/auth/AuthProvider';
import { can } from '../../core/utils/permissions';
import { appUrl } from '../../core/utils/url';

function StatCard({ title, value, suffix, toneClass, hint, icon }) {
    return (
        <Card className={`metric-card dashboard-stat-card ${toneClass}`}>
            <div className="dashboard-stat-head">
                <span className="dashboard-stat-icon">{icon}</span>
                <div>
                    <Typography.Text className="dashboard-stat-label">{title}</Typography.Text>
                    <Statistic value={value || 0} suffix={suffix} />
                </div>
            </div>
            {hint ? <Typography.Text className="dashboard-stat-hint">{hint}</Typography.Text> : null}
        </Card>
    );
}

export function DashboardPage() {
    const { notification } = App.useApp();
    const { user } = useAuth();
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [rangePreset, setRangePreset] = useState('month');
    const [medicalRepresentativeId, setMedicalRepresentativeId] = useState(undefined);
    const [medicalRepresentatives, setMedicalRepresentatives] = useState([]);
    const [state, setState] = useState({ loading: true, data: null });

    useEffect(() => {
        if (user?.is_owner || user?.permissions?.includes('mr.view')) {
            http.get(endpoints.mrOptions).then(({ data }) => setMedicalRepresentatives(data.data || [])).catch(() => {});
        }
    }, [user]);

    useEffect(() => {
        loadSummary();
    }, [range, medicalRepresentativeId]);

    async function loadSummary() {
        setState((current) => ({ ...current, loading: true }));

        try {
            const { data } = await http.get(endpoints.dashboard, {
                params: {
                    from: range?.[0]?.format('YYYY-MM-DD'),
                    to: range?.[1]?.format('YYYY-MM-DD'),
                    medical_representative_id: medicalRepresentativeId,
                },
            });
            setState({ loading: false, data: data.data });
        } catch (error) {
            notification.error({
                message: 'Dashboard failed',
                description: error?.response?.data?.message || error.message,
            });
            setState({ loading: false, data: null });
        }
    }

    const data = state.data;
    const stats = data?.stats || {};
    const representativeScope = data?.scope === 'medical_representative';
    const quickLinks = useMemo(() => ([
        { key: 'inventory', label: 'Inventory', href: appUrl('/app/inventory/products'), visible: can(user, 'inventory.products.view') },
        { key: 'purchase', label: 'Purchase Entry', href: appUrl('/app/purchases'), visible: can(user, 'purchase.entries.view') || can(user, 'purchase.entries.create') },
        { key: 'sales', label: 'Sales / POS', href: appUrl('/app/sales/pos'), visible: can(user, 'sales.invoices.view') || can(user, 'sales.pos.use') },
        { key: 'mr', label: 'MR Tracking', href: appUrl('/app/mr/performance'), visible: can(user, 'mr.view') || can(user, 'mr.visits.manage') },
        { key: 'reports', label: 'Reports', href: appUrl('/app/reports'), visible: can(user, 'reports.view') || can(user, 'accounting.books.view') || can(user, 'accounting.trial_balance.view') },
        { key: 'setup', label: 'Setup', href: appUrl('/app/settings'), visible: user?.is_owner || can(user, 'settings.manage') || can(user, 'users.manage') || can(user, 'roles.manage') },
    ].filter((item) => item.visible)), [user]);
    const attentionItems = useMemo(() => representativeScope ? [
        { key: 'visits', label: 'Visits in range', value: stats.visits || 0, tone: 'purple', note: 'Logged field calls in the selected period.' },
        { key: 'visit-orders', label: 'Visit order value', value: stats.visit_orders || 0, money: true, tone: 'blue', note: 'Orders captured before invoice posting.' },
        { key: 'invoices', label: 'Invoices posted', value: stats.invoices || 0, tone: 'green', note: 'Sales invoices credited to this MR.' },
    ] : [
        { key: 'low-stock', label: 'Low stock lines', value: stats.low_stock || 0, tone: 'red', note: 'Products at or below reorder level.' },
        { key: 'expiry', label: 'Expiry watch', value: stats.expiring_batches || 0, tone: 'orange', note: 'Batches expiring inside three months.' },
        { key: 'receivables', label: 'Receivables', value: stats.receivables || 0, money: true, tone: 'blue', note: 'Customer balances still open.' },
        { key: 'payables', label: 'Payables', value: stats.payables || 0, money: true, tone: 'purple', note: 'Supplier balances still unpaid.' },
    ], [representativeScope, stats]);

    const statCards = useMemo(() => {
        if (representativeScope) {
            return [
                {
                    key: 'today-sales',
                    title: 'Today Sales',
                    value: stats.today_sales,
                    hint: 'Invoices credited today',
                    icon: <DollarCircleOutlined />,
                    toneClass: 'dashboard-stat-success',
                },
                {
                    key: 'period-sales',
                    title: 'Period Sales',
                    value: stats.period_sales,
                    hint: 'Sales in selected range',
                    icon: <RiseOutlined />,
                    toneClass: 'dashboard-stat-primary',
                },
                {
                    key: 'visits',
                    title: 'Visits',
                    value: stats.visits,
                    suffix: 'visits',
                    hint: 'Tracked field visits',
                    icon: <TeamOutlined />,
                    toneClass: 'dashboard-stat-purple',
                },
                {
                    key: 'target',
                    title: 'Target',
                    value: stats.target,
                    hint: 'Assigned monthly target',
                    icon: <LineChartOutlined />,
                    toneClass: 'dashboard-stat-warning',
                },
            ];
        }

        return [
            {
                key: 'today-sales',
                title: 'Today Sales',
                value: stats.today_sales,
                hint: 'Confirmed invoice total for today',
                icon: <DollarCircleOutlined />,
                toneClass: 'dashboard-stat-success',
            },
            {
                key: 'period-sales',
                title: 'Period Sales',
                value: stats.period_sales,
                hint: 'Confirmed sales in selected range',
                icon: <RiseOutlined />,
                toneClass: 'dashboard-stat-primary',
            },
            {
                key: 'low-stock',
                title: 'Low Stock',
                value: stats.low_stock,
                suffix: 'items',
                hint: 'Products under reorder level',
                icon: <AlertOutlined />,
                toneClass: 'dashboard-stat-danger',
            },
            {
                key: 'expiry',
                title: 'Expiring Batches',
                value: stats.expiring_batches,
                suffix: 'batches',
                hint: 'Expiring within 3 months',
                icon: <FallOutlined />,
                toneClass: 'dashboard-stat-warning',
            },
        ];
    }, [representativeScope, stats]);

    function applyRangePreset(preset) {
        const today = dayjs();
        const quarterStartMonth = Math.floor(today.month() / 3) * 3;

        setRangePreset(preset);

        switch (preset) {
        case 'today':
            setRange([today.startOf('day'), today.endOf('day')]);
            break;
        case 'week':
            setRange([today.subtract(6, 'day').startOf('day'), today.endOf('day')]);
            break;
        case 'quarter':
            setRange([dayjs(new Date(today.year(), quarterStartMonth, 1)).startOf('day'), today.endOf('day')]);
            break;
        case 'month':
        default:
            setRange([today.startOf('month'), today.endOf('day')]);
            break;
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                title={representativeScope ? 'MR Dashboard' : 'Operations Dashboard'}
                description={representativeScope
                    ? `Visits, sales and target movement for ${data?.period || 'current period'}`
                    : `Sales, purchase, stock, expiry and collection signals for ${data?.period || 'current period'}`}
                actions={(
                    <Space wrap>
                        {!user?.medical_representative_id && medicalRepresentatives.length > 0 && (
                            <Select
                                allowClear
                                placeholder="All MRs"
                                className="dashboard-filter-control"
                                value={medicalRepresentativeId}
                                onChange={setMedicalRepresentativeId}
                                options={medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))}
                            />
                        )}
                        <Space.Compact>
                            <Button type={rangePreset === 'today' ? 'primary' : 'default'} onClick={() => applyRangePreset('today')}>Today</Button>
                            <Button type={rangePreset === 'week' ? 'primary' : 'default'} onClick={() => applyRangePreset('week')}>This Week</Button>
                            <Button type={rangePreset === 'month' ? 'primary' : 'default'} onClick={() => applyRangePreset('month')}>This Month</Button>
                            <Button type={rangePreset === 'quarter' ? 'primary' : 'default'} onClick={() => applyRangePreset('quarter')}>This Quarter</Button>
                        </Space.Compact>
                        <DatePicker.RangePicker value={range} onChange={(value) => { setRange(value); setRangePreset(null); }} />
                        <Button icon={<ReloadOutlined />} onClick={loadSummary}>Refresh</Button>
                    </Space>
                )}
            />

            <Card className="dashboard-hero-card" loading={state.loading}>
                <div className="dashboard-hero-grid">
                    <div className="dashboard-hero-copy">
                        <Tag color="cyan">{representativeScope ? 'MR view' : 'Operations view'}</Tag>
                        <Typography.Title level={3}>
                            {representativeScope ? 'Field performance and order momentum' : 'Keep billing, stock and balances in one view'}
                        </Typography.Title>
                        <Typography.Paragraph>
                            {representativeScope
                                ? 'Track visits, attributed sales and target movement without leaving the main workspace.'
                                : 'This screen is meant for day-start review: sales pace, supplier load, stock risks and outstanding balances.'}
                        </Typography.Paragraph>
                        <div className="dashboard-hero-metrics">
                            {representativeScope ? (
                                <>
                                    <div>
                                        <span>Visit Order Value</span>
                                        <strong><Money value={stats.visit_orders} /></strong>
                                    </div>
                                    <div>
                                        <span>Invoices Posted</span>
                                        <strong>{stats.invoices || 0}</strong>
                                    </div>
                                    <div>
                                        <span>Target Value</span>
                                        <strong><Money value={stats.target} /></strong>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div>
                                        <span>Purchase Volume</span>
                                        <strong><Money value={stats.period_purchase} /></strong>
                                    </div>
                                    <div>
                                        <span>Receivables</span>
                                        <strong><Money value={stats.receivables} /></strong>
                                    </div>
                                    <div>
                                        <span>Payables</span>
                                        <strong><Money value={stats.payables} /></strong>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                    <div className="dashboard-hero-panel">
                        <div className="dashboard-hero-panel-head">
                            <span>{data?.period || 'Current period'}</span>
                            <Tag color={representativeScope ? 'purple' : 'green'}>
                                {representativeScope ? `${stats.visits || 0} visits` : `${stats.sales_invoices || 0} invoices`}
                            </Tag>
                        </div>
                        <div className="dashboard-hero-panel-body">
                            <div>
                                <small>{representativeScope ? 'Attributed sales' : 'Period sales'}</small>
                                <strong><Money value={stats.period_sales} /></strong>
                            </div>
                            <div>
                                <small>{representativeScope ? 'Target progress' : 'Purchase bills'}</small>
                                <strong>
                                    {representativeScope
                                        ? `${stats.target ? Math.min(100, Math.round(((stats.period_sales || 0) / stats.target) * 100)) : 0}%`
                                        : stats.purchase_bills || 0}
                                </strong>
                            </div>
                        </div>
                        {!representativeScope && (
                            <div className="dashboard-hero-alerts">
                                <Tag color="red">{stats.low_stock || 0} low stock</Tag>
                                <Tag color="orange">{stats.expiring_batches || 0} expiry watch</Tag>
                                <Tag color="blue">{data?.mr?.active || 0} active MR</Tag>
                            </div>
                        )}
                    </div>
                </div>
            </Card>

            <Row gutter={[16, 16]}>
                <Col xs={24} xl={14}>
                    <Card title="Quick Actions" className="dashboard-action-card" loading={state.loading}>
                        {quickLinks.length ? (
                            <div className="dashboard-action-grid">
                                {quickLinks.map((link) => (
                                    <Button key={link.key} onClick={() => { window.location.href = link.href; }}>
                                        {link.label}
                                    </Button>
                                ))}
                            </div>
                        ) : (
                            <Typography.Text type="secondary">No additional modules are assigned to this login yet.</Typography.Text>
                        )}
                    </Card>
                </Col>
                <Col xs={24} xl={10}>
                    <Card title="Attention Queue" className="dashboard-attention-card" loading={state.loading}>
                        <div className="dashboard-attention-list">
                            {attentionItems.map((item) => (
                                <div key={item.key} className={`dashboard-attention-item tone-${item.tone}`}>
                                    <div>
                                        <strong>{item.label}</strong>
                                        <small>{item.note}</small>
                                    </div>
                                    <span>{item.money ? <Money value={item.value} /> : item.value}</span>
                                </div>
                            ))}
                        </div>
                    </Card>
                </Col>
            </Row>

            <Row gutter={[16, 16]}>
                {statCards.map((card) => (
                    <Col key={card.key} xs={24} sm={12} xl={6}>
                        <StatCard {...card} />
                    </Col>
                ))}
            </Row>

            {!representativeScope && (
                <Row gutter={[16, 16]}>
                    <Col xs={24} sm={12} xl={6}>
                        <StatCard title="Products" value={stats.products} suffix="items" hint="Catalog entries available for billing" icon={<ShoppingCartOutlined />} toneClass="dashboard-stat-slate" />
                    </Col>
                    <Col xs={24} sm={12} xl={6}>
                        <StatCard title="Receivables" value={stats.receivables} hint="Customer balances still open" icon={<RiseOutlined />} toneClass="dashboard-stat-amber" />
                    </Col>
                    <Col xs={24} sm={12} xl={6}>
                        <StatCard title="Payables" value={stats.payables} hint="Supplier balances still due" icon={<FallOutlined />} toneClass="dashboard-stat-purple" />
                    </Col>
                    <Col xs={24} sm={12} xl={6}>
                        <StatCard title="Purchase Bills" value={stats.purchase_bills} suffix="bills" hint="Received purchase documents in range" icon={<ShoppingCartOutlined />} toneClass="dashboard-stat-success" />
                    </Col>
                </Row>
            )}

            <Row gutter={[16, 16]}>
                <Col xs={24} xl={16}>
                    <Card title="Top Selling Products" loading={state.loading}>
                        <Table
                            rowKey="id"
                            pagination={false}
                            dataSource={data?.top_products || []}
                            columns={[
                                { title: 'Product', dataIndex: 'name' },
                                { title: 'Qty', dataIndex: 'quantity', align: 'right', width: 110 },
                                { title: 'Amount', dataIndex: 'amount', align: 'right', render: (value) => <Money value={value || 0} />, width: 150 },
                            ]}
                            locale={{ emptyText: <Empty description="No posted sales in this period" /> }}
                        />
                    </Card>
                </Col>
                <Col xs={24} xl={8}>
                    <Card title={representativeScope ? 'MR Snapshot' : 'Operations Snapshot'} loading={state.loading}>
                        <div className="finance-stack">
                            {representativeScope ? (
                                <>
                                    <div><span>Visit Order Value</span><strong><Money value={stats.visit_orders} /></strong></div>
                                    <div><span>Invoices</span><strong>{stats.invoices || 0}</strong></div>
                                    <div><span>Target Value</span><strong><Money value={stats.target} /></strong></div>
                                </>
                            ) : (
                                <>
                                    <div><span>Sales Invoices</span><strong>{stats.sales_invoices || 0}</strong></div>
                                    <div><span>Purchase Bills</span><strong>{stats.purchase_bills || 0}</strong></div>
                                    <div><span>MR Month Orders</span><strong><Money value={data?.mr?.month_orders} /></strong></div>
                                    <div><span>Active MRs</span><strong>{data?.mr?.active || 0}</strong></div>
                                </>
                            )}
                        </div>
                    </Card>
                </Col>
            </Row>

            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <Card title="Recent Sales" loading={state.loading}>
                        <Table
                            rowKey="id"
                            size="small"
                            pagination={false}
                            dataSource={data?.recent_sales || []}
                            columns={[
                                { title: 'Invoice', dataIndex: 'invoice_no' },
                                { title: 'Customer', dataIndex: 'customer_name', render: (value) => value || 'Walk-in' },
                                !representativeScope ? { title: 'MR', dataIndex: 'mr_name', render: (value) => value || '-' } : null,
                                { title: 'Payment', dataIndex: 'payment_status', width: 120, render: (value) => <Tag color={value === 'paid' ? 'green' : value === 'partial' ? 'gold' : 'red'}>{value}</Tag> },
                                { title: 'Total', dataIndex: 'grand_total', align: 'right', render: (value) => <Money value={value} /> },
                            ].filter(Boolean)}
                        />
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    {representativeScope ? (
                        <Card title="Recent Visits" loading={state.loading}>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={data?.recent_visits || []}
                                columns={[
                                    { title: 'Date', dataIndex: 'visit_date', width: 120 },
                                    { title: 'Customer', dataIndex: 'customer_name', render: (value) => value || '-' },
                                    { title: 'Status', dataIndex: 'status', width: 120, render: (value) => <Tag>{value}</Tag> },
                                    { title: 'Order Value', dataIndex: 'order_value', align: 'right', render: (value) => <Money value={value} /> },
                                ]}
                            />
                        </Card>
                    ) : (
                        <Card title="Recent Purchases" loading={state.loading}>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={data?.recent_purchases || []}
                                columns={[
                                    { title: 'Bill', dataIndex: 'purchase_no' },
                                    { title: 'Supplier', dataIndex: 'supplier_name' },
                                    { title: 'Payment', dataIndex: 'payment_status', width: 120, render: (value) => <Tag color={value === 'paid' ? 'green' : value === 'partial' ? 'gold' : 'red'}>{value}</Tag> },
                                    { title: 'Total', dataIndex: 'grand_total', align: 'right', render: (value) => <Money value={value} /> },
                                ]}
                            />
                        </Card>
                    )}
                </Col>
            </Row>

            {!representativeScope && (
                <Row gutter={[16, 16]}>
                    <Col xs={24} xl={12}>
                        <Card title="Low Stock Lines" loading={state.loading}>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={data?.low_stock_rows || []}
                                columns={[
                                    { title: 'Product', dataIndex: 'name' },
                                    { title: 'Stock', dataIndex: 'stock_on_hand', align: 'right', width: 100 },
                                    { title: 'Reorder', dataIndex: 'reorder_level', align: 'right', width: 100 },
                                ]}
                            />
                        </Card>
                    </Col>
                    <Col xs={24} xl={12}>
                        <Card title="Expiry Watch" loading={state.loading}>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={data?.expiry_rows || []}
                                columns={[
                                    { title: 'Product', dataIndex: 'name' },
                                    { title: 'Batch', dataIndex: 'batch_no', width: 120 },
                                    { title: 'Expiry', dataIndex: 'expires_at', width: 120 },
                                    {
                                        title: 'Window',
                                        dataIndex: 'days_to_expiry',
                                        width: 120,
                                        render: (value) => <Tag color={value <= 30 ? 'red' : value <= 60 ? 'orange' : 'gold'}>{value} days</Tag>,
                                    },
                                    { title: 'Qty', dataIndex: 'quantity_available', align: 'right', width: 100 },
                                ]}
                            />
                        </Card>
                    </Col>
                </Row>
            )}

            {!representativeScope && (
                <Card title="Top MR Performance" loading={state.loading}>
                    <Table
                        rowKey="id"
                        pagination={false}
                        dataSource={data?.top_representatives || []}
                        columns={[
                            { title: 'MR', dataIndex: 'name' },
                            { title: 'Territory', dataIndex: 'territory' },
                            { title: 'Invoices', dataIndex: 'invoices', align: 'right', width: 100 },
                            { title: 'Sales', dataIndex: 'amount', align: 'right', render: (value) => <Money value={value} />, width: 140 },
                        ]}
                    />
                </Card>
            )}
        </div>
    );
}
