import React, { useEffect, useState } from 'react';
import { App, Button, Card, Col, DatePicker, Empty, Row, Select, Space, Statistic, Table, Tag, Tooltip } from 'antd';
import { ArrowUpOutlined, ReloadOutlined, WarningOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { BarChart, DonutChart, MiniBar } from '../../core/components/Charts';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useAuth } from '../../core/auth/AuthProvider';

// ── Stat card with subtle trend arrow ────────────────────────────────────────
function StatCard({ title, value, suffix, tone, loading }) {
    return (
        <Card className="metric-card" loading={loading} style={{ borderTop: `3px solid ${tone}` }}>
            <Statistic
                title={title}
                value={value ?? 0}
                suffix={suffix}
                styles={{ content: { color: tone, fontSize: 22, fontWeight: 700 } }}
            />
        </Card>
    );
}

// ── Palette for charts ────────────────────────────────────────────────────────
const TONES = {
    sales:   '#2563eb',
    purchase:'#10b981',
    paid:    '#22c55e',
    partial: '#f59e0b',
    unpaid:  '#ef4444',
};

export function DashboardPage() {
    const { notification } = App.useApp();
    const { user } = useAuth();
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

    return (
        <div className="page-stack">
            <PageHeader
                title={isMr ? 'MR Dashboard' : 'Dashboard'}
                description={
                    isMr
                        ? `Visits, invoices and target tracking for ${data?.period || 'this period'}`
                        : `Sales, stock, purchases and MR overview for ${data?.period || 'this period'}`
                }
                actions={
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
                }
            />

            {/* ── KPI row ──────────────────────────────────────────────────── */}
            <Row gutter={[16, 16]}>
                <Col xs={12} md={6}>
                    <StatCard title="Today Sales" value={stats.today_sales} tone={TONES.sales} loading={loading} />
                </Col>
                <Col xs={12} md={6}>
                    <StatCard title="Period Sales" value={stats.period_sales} tone="#7c3aed" loading={loading} />
                </Col>
                {isMr ? (
                    <>
                        <Col xs={12} md={6}>
                            <StatCard title="Visits" value={stats.visits} suffix="visits" tone="#0891b2" loading={loading} />
                        </Col>
                        <Col xs={12} md={6}>
                            <StatCard title="Monthly Target" value={stats.target} tone="#ca8a04" loading={loading} />
                        </Col>
                    </>
                ) : (
                    <>
                        <Col xs={12} md={6}>
                            <StatCard title="Low Stock Items" value={stats.low_stock} suffix={<WarningOutlined style={{ color: '#ef4444' }} />} tone="#ef4444" loading={loading} />
                        </Col>
                        <Col xs={12} md={6}>
                            <StatCard title="Expiring Batches" value={stats.expiring_batches} suffix="batches" tone="#ca8a04" loading={loading} />
                        </Col>
                    </>
                )}
            </Row>

            {!isMr && (
                <Row gutter={[16, 16]}>
                    <Col xs={12} md={6}>
                        <StatCard title="Period Purchases" value={stats.period_purchase} tone={TONES.purchase} loading={loading} />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="Receivables" value={stats.receivables} tone="#ea580c" loading={loading} />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="Payables" value={stats.payables} tone="#9333ea" loading={loading} />
                    </Col>
                    <Col xs={12} md={6}>
                        <StatCard title="Total Products" value={stats.products} suffix="items" tone="#0891b2" loading={loading} />
                    </Col>
                </Row>
            )}

            {/* ── Chart row ─────────────────────────────────────────────────── */}
            {!isMr && (
                <Row gutter={[16, 16]}>
                    {/* Monthly trend bar chart */}
                    <Col xs={24} xl={15}>
                        <Card
                            title="Sales vs Purchases — Last 6 Months"
                            loading={loading}
                            style={{ height: '100%' }}
                        >
                            <BarChart
                                data={trendBars}
                                height={220}
                                legend={['Sales', 'Purchases']}
                                colors={[TONES.sales, TONES.purchase]}
                            />
                        </Card>
                    </Col>

                    {/* Payment status donut */}
                    <Col xs={24} xl={9}>
                        <Card
                            title="Invoice Payment Status"
                            loading={loading}
                            style={{ height: '100%' }}
                        >
                            {pieData.length > 0
                                ? <DonutChart data={pieData} size={160} />
                                : <Empty description="No invoices in period" />
                            }
                        </Card>
                    </Col>
                </Row>
            )}

            {/* ── Top products with mini-bar ────────────────────────────────── */}
            <Row gutter={[16, 16]}>
                <Col xs={24} xl={16}>
                    <Card title="Top Selling Products" loading={loading}>
                        <Table
                            rowKey="id"
                            pagination={false}
                            dataSource={topProducts}
                            columns={[
                                { title: 'Product', dataIndex: 'name' },
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
                                    width: 300,
                                    render: (v) => (
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                            <span style={{ minWidth: 90, textAlign: 'right' }}>
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
                <Col xs={24} xl={8}>
                    <Card
                        title={isMr ? 'MR Snapshot' : 'Operations Snapshot'}
                        loading={loading}
                        style={{ height: '100%' }}
                    >
                        <div className="finance-stack">
                            {isMr ? (
                                <>
                                    <div><span>Visit Orders</span><strong><Money value={stats.visit_orders} /></strong></div>
                                    <div><span>Invoices</span><strong>{stats.invoices || 0}</strong></div>
                                    <div><span>Target</span><strong><Money value={stats.target} /></strong></div>
                                </>
                            ) : (
                                <>
                                    <div><span>Sales Invoices</span><strong>{stats.sales_invoices || 0}</strong></div>
                                    <div><span>Purchase Bills</span><strong>{stats.purchase_bills || 0}</strong></div>
                                    <div><span>Active MRs</span><strong>{data?.mr?.active || 0}</strong></div>
                                    <div><span>MR Month Orders</span><strong><Money value={data?.mr?.month_orders} /></strong></div>
                                </>
                            )}
                        </div>
                    </Card>
                </Col>
            </Row>

            {/* ── Recent transactions ───────────────────────────────────────── */}
            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <Card title="Recent Sales" loading={loading}>
                        <Table
                            rowKey="id"
                            size="small"
                            pagination={false}
                            dataSource={data?.recent_sales || []}
                            columns={[
                                { title: 'Invoice', dataIndex: 'invoice_no', width: 120 },
                                { title: 'Customer', dataIndex: 'customer_name', render: (v) => v || 'Walk-in' },
                                !isMr ? { title: 'MR', dataIndex: 'mr_name', render: (v) => v || '-', width: 120 } : null,
                                {
                                    title: 'Status', dataIndex: 'payment_status', width: 100,
                                    render: (v) => (
                                        <Tag color={v === 'paid' ? 'success' : v === 'partial' ? 'warning' : 'error'}>
                                            {v}
                                        </Tag>
                                    ),
                                },
                                { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 120, render: (v) => <Money value={v} /> },
                            ].filter(Boolean)}
                        />
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    {isMr ? (
                        <Card title="Recent Visits" loading={loading}>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={data?.recent_visits || []}
                                columns={[
                                    { title: 'Date', dataIndex: 'visit_date', width: 110 },
                                    { title: 'Customer', dataIndex: 'customer_name', render: (v) => v || '-' },
                                    { title: 'Status', dataIndex: 'status', width: 110 },
                                    { title: 'Order', dataIndex: 'order_value', align: 'right', width: 120, render: (v) => <Money value={v} /> },
                                ]}
                            />
                        </Card>
                    ) : (
                        <Card title="Recent Purchases" loading={loading}>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={data?.recent_purchases || []}
                                columns={[
                                    { title: 'Bill', dataIndex: 'purchase_no', width: 120 },
                                    { title: 'Supplier', dataIndex: 'supplier_name' },
                                    {
                                        title: 'Status', dataIndex: 'payment_status', width: 100,
                                        render: (v) => (
                                            <Tag color={v === 'paid' ? 'success' : v === 'partial' ? 'warning' : 'error'}>
                                                {v}
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

            {/* ── Low stock + expiry (admin only) ──────────────────────────── */}
            {!isMr && (
                <Row gutter={[16, 16]}>
                    <Col xs={24} xl={12}>
                        <Card title="Low Stock Lines" loading={loading} extra={<Tag color="error">{stats.low_stock || 0} items</Tag>}>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={data?.low_stock_rows || []}
                                columns={[
                                    { title: 'Product', dataIndex: 'name' },
                                    {
                                        title: 'Stock vs Reorder', dataIndex: 'stock_on_hand', width: 220,
                                        render: (v, r) => (
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                                <span style={{ minWidth: 36, textAlign: 'right', color: '#ef4444', fontWeight: 600 }}>{(+v).toFixed(0)}</span>
                                                <span style={{ color: '#aaa', fontSize: 11 }}>/ {(+r.reorder_level).toFixed(0)} min</span>
                                            </div>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    </Col>
                    <Col xs={24} xl={12}>
                        <Card title="Expiry Watch (next 90 days)" loading={loading} extra={<Tag color="warning">{stats.expiring_batches || 0} batches</Tag>}>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={data?.expiry_rows || []}
                                columns={[
                                    { title: 'Product', dataIndex: 'name' },
                                    { title: 'Batch', dataIndex: 'batch_no', width: 110 },
                                    { title: 'Expires', dataIndex: 'expires_at', width: 110 },
                                    { title: 'Qty', dataIndex: 'quantity_available', align: 'right', width: 80 },
                                ]}
                            />
                        </Card>
                    </Col>
                </Row>
            )}

            {/* ── Top MR performance ────────────────────────────────────────── */}
            {!isMr && (
                <Card title="Top MR Performance" loading={loading}>
                    <Table
                        rowKey="id"
                        pagination={false}
                        dataSource={data?.top_representatives || []}
                        columns={[
                            { title: 'MR', dataIndex: 'name' },
                            { title: 'Territory', dataIndex: 'territory', width: 160 },
                            { title: 'Invoices', dataIndex: 'invoices', align: 'right', width: 90 },
                            {
                                title: 'Sales',
                                dataIndex: 'amount',
                                align: 'right',
                                width: 280,
                                render: (v) => (
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <span style={{ minWidth: 100, textAlign: 'right' }}><Money value={v || 0} /></span>
                                        <div style={{ flex: 1 }}>
                                            <MiniBar
                                                value={v || 0}
                                                max={Math.max(...(data?.top_representatives || []).map((r) => r.amount || 0), 1)}
                                                color="#7c3aed"
                                            />
                                        </div>
                                    </div>
                                ),
                            },
                        ]}
                        size="small"
                    />
                </Card>
            )}
        </div>
    );
}
