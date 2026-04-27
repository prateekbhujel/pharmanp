import React, { useEffect, useState } from 'react';
import { App, Button, Card, Col, DatePicker, Empty, Row, Select, Space, Statistic, Table, Tag, Tabs } from 'antd';
import { ReloadOutlined, WarningOutlined, PlusOutlined, ShopOutlined, MedicineBoxOutlined, LineChartOutlined, AlertOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { BarChart, DonutChart, MiniBar } from '../../core/components/Charts';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useAuth } from '../../core/auth/AuthProvider';
import { appUrl } from '../../core/utils/url';

// ── Stat card with subtle trend arrow ────────────────────────────────────────
function StatCard({ title, value, suffix, tone, loading, icon }) {
    return (
        <Card 
            className="metric-card metric-card-glow glass-card" 
            loading={loading} 
            style={{ borderTop: `4px solid ${tone}` }}
        >
            <Statistic
                title={
                    <span style={{ color: '#64748b', fontWeight: 600, display: 'flex', alignItems: 'center', gap: 6 }}>
                        {icon && <span style={{ color: tone }}>{icon}</span>}
                        {title}
                    </span>
                }
                value={value ?? 0}
                suffix={suffix}
                styles={{ content: { color: tone, fontSize: 20, fontWeight: 800, marginTop: 4 } }}
            />
        </Card>
    );
}

// ── Palette for charts ────────────────────────────────────────────────────────
const TONES = {
    sales:   '#0ea5e9', // Medical Blue
    purchase:'#10b981', // Success Green
    paid:    '#10b981',
    partial: '#f59e0b',
    unpaid:  '#ef4444',
};

export function DashboardPage() {
    const { notification } = App.useApp();
    const { user, branding } = useAuth();
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [medicalRepresentativeId, setMedicalRepresentativeId] = useState(undefined);
    const [medicalRepresentatives, setMedicalRepresentatives] = useState([]);
    const [state, setState] = useState({ loading: true, data: null });

    useEffect(() => {
        if (user?.is_owner || user?.permissions?.includes('mr.view')) {
            http.get(endpoints.mrOptions)
                .then(({ data }) => setMedicalRepresentatives(data.data || []))
                .catch(() => {});
        }
    }, [user]);

    useEffect(() => { loadSummary(); }, [range, medicalRepresentativeId]);

    async function loadSummary() {
        setState((s) => ({ ...s, loading: true }));
        try {
            const { data } = await http.get(endpoints.dashboard, {
                params: {
                    from: range?.[0]?.format('YYYY-MM-DD'),
                    to:   range?.[1]?.format('YYYY-MM-DD'),
                    medical_representative_id: medicalRepresentativeId,
                },
            });
            setState({ loading: false, data: data.data });
        } catch (error) {
            notification.error({ message: 'Dashboard failed', description: error?.response?.data?.message || error.message });
            setState({ loading: false, data: null });
        }
    }

    const data  = state.data;
    const stats = data?.stats || {};
    const chart = data?.chart_data || {};
    const isMr  = data?.scope === 'medical_representative';
    const loading = state.loading;

    // ── Convert monthly_trend to BarChart format ───────────────────────────────
    const trendBars = (chart.monthly_trend || []).map((m) => ({
        label: m.month,
        bars: [
            { value: m.sales,     color: TONES.sales },
            { value: m.purchases, color: TONES.purchase },
        ],
    }));

    // ── Convert payment_breakdown to DonutChart format ────────────────────────
    const pieData = (chart.payment_breakdown || []).map((p) => ({
        label: p.label,
        value: p.value,
        color: TONES[p.label?.toLowerCase()] ?? '#94a3b8',
    }));

    // ── Top products max for MiniBar ──────────────────────────────────────────
    const topProducts = chart.top_products_chart || data?.top_products || [];
    const maxProductAmt = Math.max(...topProducts.map((p) => p.amount || 0), 1);

    const isAlertsHigh = stats.low_stock > 0 || stats.expiring_batches > 0;

    const items = [
        {
            key: 'overview',
            label: <span style={{ fontWeight: 600 }}><ShopOutlined /> Overview</span>,
            children: (
                <div className="page-stack" style={{ marginTop: 16 }}>
                    <Row gutter={[16, 16]}>
                        <Col xs={12} md={6}>
                            <StatCard title="Today's Sales" value={stats.today_sales} tone={TONES.sales} loading={loading} />
                        </Col>
                        <Col xs={12} md={6}>
                            <StatCard title="Period Sales" value={stats.period_sales} tone="#0891b2" loading={loading} />
                        </Col>
                        {isMr ? (
                            <>
                                <Col xs={12} md={6}>
                                    <StatCard title="Visits" value={stats.visits} suffix="visits" tone="#0891b2" loading={loading} />
                                </Col>
                                <Col xs={12} md={6}>
                                    <StatCard title="Monthly Target" value={stats.target} tone="#f59e0b" loading={loading} />
                                </Col>
                            </>
                        ) : (
                            <>
                                <Col xs={12} md={6}>
                                    <StatCard title="Period Purchases" value={stats.period_purchase} tone={TONES.purchase} loading={loading} />
                                </Col>
                                <Col xs={12} md={6}>
                                    <StatCard title="Receivables" value={stats.receivables} tone="#ea580c" loading={loading} />
                                </Col>
                            </>
                        )}
                        {!isMr && (
                            <>
                                <Col xs={12} md={6}>
                                    <StatCard title="Payables" value={stats.payables} tone="#9333ea" loading={loading} />
                                </Col>
                                <Col xs={12} md={6}>
                                    <StatCard title="Low Stock Items" value={stats.low_stock} suffix={stats.low_stock > 0 ? <WarningOutlined style={{ color: '#ef4444' }} /> : ''} tone={stats.low_stock > 0 ? "#ef4444" : "#64748b"} loading={loading} />
                                </Col>
                                <Col xs={12} md={6}>
                                    <StatCard title="Expiring Batches" value={stats.expiring_batches} suffix="batches" tone={stats.expiring_batches > 0 ? "#f59e0b" : "#64748b"} loading={loading} />
                                </Col>
                                <Col xs={12} md={6}>
                                    <StatCard title="Total Products" value={stats.products} suffix="items" tone="#6366f1" loading={loading} />
                                </Col>
                            </>
                        )}
                    </Row>

                    <Row gutter={[16, 16]}>
                        <Col xs={24} lg={12}>
                            <Card title="Recent Sales" loading={loading} extra={<Button type="link" href={appUrl('/app/sales/invoices')}>View All</Button>}>
                                <Table
                                    rowKey="id"
                                    size="small"
                                    scroll={{ x: 500 }}
                                    pagination={false}
                                    dataSource={data?.recent_sales?.slice(0, 5) || []}
                                    columns={[
                                        { title: 'Invoice', dataIndex: 'invoice_no', width: 120 },
                                        { title: 'Customer', dataIndex: 'customer_name', render: (v) => <strong style={{color: '#1e293b'}}>{v || 'Walk-in'}</strong> },
                                        {
                                            title: 'Status', dataIndex: 'payment_status', width: 100,
                                            render: (v) => (
                                                <Tag color={v === 'paid' ? 'success' : v === 'partial' ? 'warning' : 'error'} style={{ borderRadius: 12, padding: '0 8px' }}>
                                                    {v?.toUpperCase()}
                                                </Tag>
                                            ),
                                        },
                                        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 120, render: (v) => <Money value={v} /> },
                                    ]}
                                />
                            </Card>
                        </Col>
                        <Col xs={24} lg={12}>
                            {!isMr && (
                                <Card title="Recent Purchases" loading={loading} extra={<Button type="link" href={appUrl('/app/purchases/bills')}>View All</Button>}>
                                    <Table
                                        rowKey="id"
                                        size="small"
                                        scroll={{ x: 500 }}
                                        pagination={false}
                                        dataSource={data?.recent_purchases?.slice(0, 5) || []}
                                        columns={[
                                            { title: 'Bill', dataIndex: 'purchase_no', width: 120 },
                                            { title: 'Supplier', dataIndex: 'supplier_name', render: (v) => <strong style={{color: '#1e293b'}}>{v}</strong> },
                                            {
                                                title: 'Status', dataIndex: 'payment_status', width: 100,
                                                render: (v) => (
                                                    <Tag color={v === 'paid' ? 'success' : v === 'partial' ? 'warning' : 'error'} style={{ borderRadius: 12, padding: '0 8px' }}>
                                                        {v?.toUpperCase()}
                                                    </Tag>
                                                ),
                                            },
                                            { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 120, render: (v) => <Money value={v} /> },
                                        ]}
                                    />
                                </Card>
                            )}
                        </Col>
                    </Row>
                </div>
            )
        },
        !isMr && {
            key: 'analytics',
            label: <span style={{ fontWeight: 600 }}><LineChartOutlined /> Analytics</span>,
            children: (
                <div className="page-stack" style={{ marginTop: 16 }}>
                    <Row gutter={[16, 16]}>
                        <Col xs={24} xl={15}>
                            <Card
                                title="Sales vs Purchases (Last 6 Months)"
                                loading={loading}
                                style={{ height: '100%' }}
                            >
                                <BarChart
                                    data={trendBars}
                                    height={320}
                                    legend={['Sales', 'Purchases']}
                                    colors={[TONES.sales, TONES.purchase]}
                                />
                            </Card>
                        </Col>
                        <Col xs={24} xl={9}>
                            <Card
                                title="Invoice Payment Status"
                                loading={loading}
                                style={{ height: '100%' }}
                            >
                                <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: 320 }}>
                                    {pieData.length > 0
                                        ? <DonutChart data={pieData} size={220} />
                                        : <Empty description="No invoices in period" />
                                    }
                                </div>
                            </Card>
                        </Col>
                    </Row>
                    <Row gutter={[16, 16]}>
                        <Col xs={24}>
                            <Card title="Top Selling Products" loading={loading}>
                                <Table
                                    rowKey="id"
                                    scroll={{ x: 500 }}
                                    pagination={false}
                                    dataSource={topProducts}
                                    columns={[
                                        { title: 'Product', dataIndex: 'name', render: (v) => <strong style={{color: '#1e293b'}}>{v}</strong> },
                                        {
                                            title: 'Qty Sold',
                                            dataIndex: 'quantity',
                                            align: 'right',
                                            width: 100,
                                            render: (v) => (+v).toFixed(0),
                                        },
                                        {
                                            title: 'Revenue',
                                            dataIndex: 'amount',
                                            width: 400,
                                            render: (v) => (
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                                    <span style={{ minWidth: 100, textAlign: 'right', fontWeight: 600 }}>
                                                        <Money value={v || 0} />
                                                    </span>
                                                    <div style={{ flex: 1 }}>
                                                        <MiniBar value={v || 0} max={maxProductAmt} color={TONES.sales} />
                                                    </div>
                                                </div>
                                            ),
                                        },
                                    ]}
                                    locale={{ emptyText: <Empty description="No posted sales in this period" /> }}
                                    size="small"
                                />
                            </Card>
                        </Col>
                    </Row>
                </div>
            )
        },
        !isMr && {
            key: 'alerts',
            label: (
                <span style={{ fontWeight: 600, color: isAlertsHigh ? '#ef4444' : undefined }}>
                    <AlertOutlined /> Alerts {isAlertsHigh && <Tag color="error" style={{ marginLeft: 8, borderRadius: 12 }}>!</Tag>}
                </span>
            ),
            children: (
                <div className="page-stack" style={{ marginTop: 16 }}>
                    <Row gutter={[16, 16]}>
                        <Col xs={24} xl={12}>
                            <Card title="Low Stock Lines" loading={loading} extra={<Tag color="error" style={{ borderRadius: 12 }}>{stats.low_stock || 0} items</Tag>}>
                                <Table
                                    rowKey="id"
                                    size="small"
                                    scroll={{ x: 400 }}
                                    pagination={false}
                                    dataSource={data?.low_stock_rows || []}
                                    columns={[
                                        { title: 'Product', dataIndex: 'name', render: (v) => <strong style={{color: '#1e293b'}}>{v}</strong> },
                                        {
                                            title: 'Stock vs Reorder', dataIndex: 'stock_on_hand', width: 220,
                                            render: (v, r) => (
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                    <Tag color="error" style={{ borderRadius: 12, minWidth: 40, textAlign: 'center' }}>{(+v).toFixed(0)}</Tag>
                                                    <span style={{ color: '#64748b', fontSize: 12 }}>/ {(+r.reorder_level).toFixed(0)} min</span>
                                                </div>
                                            ),
                                        },
                                    ]}
                                    locale={{ emptyText: <Empty description="Stock levels are healthy" /> }}
                                />
                            </Card>
                        </Col>
                        <Col xs={24} xl={12}>
                            <Card title="Expiry Watch (next 90 days)" loading={loading} extra={<Tag color="warning" style={{ borderRadius: 12 }}>{stats.expiring_batches || 0} batches</Tag>}>
                                <Table
                                    rowKey="id"
                                    size="small"
                                    scroll={{ x: 400 }}
                                    pagination={false}
                                    dataSource={data?.expiry_rows || []}
                                    columns={[
                                        { title: 'Product', dataIndex: 'name', render: (v) => <strong style={{color: '#1e293b'}}>{v}</strong> },
                                        { title: 'Batch', dataIndex: 'batch_no', width: 110 },
                                        { title: 'Expires', dataIndex: 'expires_at', width: 120, render: (v) => <span style={{ color: '#f59e0b', fontWeight: 600 }}>{v}</span> },
                                        { title: 'Qty', dataIndex: 'quantity_available', align: 'right', width: 80 },
                                    ]}
                                    locale={{ emptyText: <Empty description="No batches expiring soon" /> }}
                                />
                            </Card>
                        </Col>
                    </Row>
                </div>
            )
        }
    ].filter(Boolean);

    return (
        <div className="page-stack">
            <PageHeader
                title="Dashboard"
                description={data?.period || 'Current operating period'}
                actions={(
                <Space wrap>
                    {!user?.medical_representative_id && medicalRepresentatives.length > 0 && (
                        <Select
                            allowClear
                            placeholder="All MRs"
                            style={{ minWidth: 200 }}
                            value={medicalRepresentativeId}
                            onChange={setMedicalRepresentativeId}
                            options={medicalRepresentatives.map((m) => ({ value: m.id, label: m.name }))}
                        />
                    )}
                    <DatePicker.RangePicker value={range} onChange={setRange} />
                    <Button icon={<ReloadOutlined />} onClick={loadSummary}>Refresh</Button>
                </Space>
                )}
            />

            <Space wrap>
                <Button type="primary" href={appUrl('/app/sales/pos')} icon={<PlusOutlined />}>
                    New Sale / POS
                </Button>
                {!isMr && (
                    <Button href={appUrl('/app/purchases/entry')} icon={<ShopOutlined />}>
                        Purchase Entry
                    </Button>
                )}
            </Space>

            <Card className="glass-card" styles={{ body: { padding: '8px 24px 24px' } }}>
                <Tabs 
                    defaultActiveKey="overview" 
                    items={items} 
                    size="large"
                    animated
                    tabBarStyle={{ marginBottom: 0, borderBottom: '1px solid #f1f5f9' }}
                />
            </Card>
        </div>
    );
}
