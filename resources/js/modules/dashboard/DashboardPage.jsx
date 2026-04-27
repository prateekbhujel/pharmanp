import React, { useEffect, useState } from 'react';
import { App, Button, Card, Col, DatePicker, Empty, Row, Select, Space, Statistic, Table, Tag, Tabs, Segmented } from 'antd';
import { ReloadOutlined, WarningOutlined, PlusOutlined, ShopOutlined, MedicineBoxOutlined, StockOutlined, LineChartOutlined, AlertOutlined, RiseOutlined, FallOutlined, WalletOutlined, ShoppingCartOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { BarChart, DonutChart, MiniBar } from '../../core/components/Charts';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useAuth } from '../../core/auth/AuthProvider';
import { appUrl } from '../../core/utils/url';

function StatCard({ title, value, suffix, tone, loading, icon, trend }) {
    return (
        <Card 
            className="dashboard-mini-card glass-card" 
            loading={loading}
        >
            <div className="mini-card-content">
                <div className="mini-card-icon" style={{ backgroundColor: `${tone}15`, color: tone }}>
                    {icon}
                </div>
                <div className="mini-card-info">
                    <Typography.Text type="secondary" className="mini-card-title">{title}</Typography.Text>
                    <div className="mini-card-value-row">
                        <span className="mini-card-value">{value ?? 0}{suffix && <span className="mini-card-suffix">{suffix}</span>}</span>
                        {trend && (
                            <span className={`mini-card-trend ${trend > 0 ? 'up' : 'down'}`}>
                                {trend > 0 ? <RiseOutlined /> : <FallOutlined />} {Math.abs(trend)}%
                            </span>
                        )}
                    </div>
                </div>
            </div>
        </Card>
    );
}

const TONES = {
    sales:   '#0ea5e9',
    purchase:'#10b981',
    paid:    '#10b981',
    partial: '#f59e0b',
    unpaid:  '#ef4444',
};

import { Typography } from 'antd';

export function DashboardPage() {
    const { notification } = App.useApp();
    const { user, branding } = useAuth();
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [medicalRepresentativeId, setMedicalRepresentativeId] = useState(undefined);
    const [medicalRepresentatives, setMedicalRepresentatives] = useState([]);
    const [state, setState] = useState({ loading: true, data: null });
    const [heroToggle, setHeroToggle] = useState('Sales');

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
    const appName = branding?.app_name || 'PharmaNP';

    const trendBars = (chart.monthly_trend || []).map((m) => ({
        label: m.month,
        bars: [
            { value: m.sales,     color: TONES.sales },
            { value: m.purchases, color: TONES.purchase },
        ],
    }));

    const pieData = (chart.payment_breakdown || []).map((p) => ({
        label: p.label,
        value: p.value,
        color: TONES[p.label?.toLowerCase()] ?? '#94a3b8',
    }));

    const topProducts = chart.top_products_chart || data?.top_products || [];
    const maxProductAmt = Math.max(...topProducts.map((p) => p.amount || 0), 1);
    const isAlertsHigh = stats.low_stock > 0 || stats.expiring_batches > 0;

    const items = [
        {
            key: 'overview',
            label: <span style={{ fontWeight: 600 }}>Overview</span>,
            children: (
                <div className="page-stack" style={{ marginTop: 16 }}>
                    <Row gutter={[16, 16]}>
                        <Col xs={24} sm={12} md={6}>
                            <StatCard title="Today's Sales" value={<Money value={stats.today_sales} />} tone={TONES.sales} loading={loading} icon={<ShoppingCartOutlined />} />
                        </Col>
                        <Col xs={24} sm={12} md={6}>
                            <StatCard title="Period Sales" value={<Money value={stats.period_sales} />} tone="#0891b2" loading={loading} icon={<LineChartOutlined />} />
                        </Col>
                        {isMr ? (
                            <>
                                <Col xs={24} sm={12} md={6}>
                                    <StatCard title="Visits" value={stats.visits} suffix=" visits" tone="#0891b2" loading={loading} icon={<ShopOutlined />} />
                                </Col>
                                <Col xs={24} sm={12} md={6}>
                                    <StatCard title="Monthly Target" value={<Money value={stats.target} />} tone="#f59e0b" loading={loading} icon={<WalletOutlined />} />
                                </Col>
                            </>
                        ) : (
                            <>
                                <Col xs={24} sm={12} md={6}>
                                    <StatCard title="Period Purchases" value={<Money value={stats.period_purchase} />} tone={TONES.purchase} loading={loading} icon={<MedicineBoxOutlined />} />
                                </Col>
                                <Col xs={24} sm={12} md={6}>
                                    <StatCard title="Receivables" value={<Money value={stats.receivables} />} tone="#ea580c" loading={loading} icon={<WalletOutlined />} />
                                </Col>
                            </>
                        )}
                    </Row>
                    
                    {!isMr && (
                    <Row gutter={[16, 16]}>
                         <Col xs={24} sm={12} md={6}>
                            <StatCard title="Payables" value={<Money value={stats.payables} />} tone="#9333ea" loading={loading} icon={<WalletOutlined />} />
                        </Col>
                        <Col xs={24} sm={12} md={6}>
                            <StatCard title="Low Stock" value={stats.low_stock} tone={stats.low_stock > 0 ? "#ef4444" : "#64748b"} loading={loading} icon={<WarningOutlined />} />
                        </Col>
                        <Col xs={24} sm={12} md={6}>
                            <StatCard title="Expiring Soon" value={stats.expiring_batches} tone={stats.expiring_batches > 0 ? "#f59e0b" : "#64748b"} loading={loading} icon={<AlertOutlined />} />
                        </Col>
                        <Col xs={24} sm={12} md={6}>
                            <StatCard title="Active Products" value={stats.products} tone="#6366f1" loading={loading} icon={<MedicineBoxOutlined />} />
                        </Col>
                    </Row>
                    )}

                    <Row gutter={[16, 16]}>
                        <Col xs={24} lg={12}>
                            <Card title="Recent Sales" size="small" loading={loading} extra={<Button type="link" size="small" href={appUrl('/app/sales/invoices')}>View All</Button>}>
                                <Table
                                    rowKey="id"
                                    size="small"
                                    scroll={{ x: 400 }}
                                    pagination={false}
                                    dataSource={data?.recent_sales?.slice(0, 5) || []}
                                    columns={[
                                        { title: 'Invoice', dataIndex: 'invoice_no', width: 100 },
                                        { title: 'Customer', dataIndex: 'customer_name', render: (v) => <span style={{fontWeight: 500}}>{v || 'Walk-in'}</span> },
                                        {
                                            title: 'Status', dataIndex: 'payment_status', width: 90,
                                            render: (v) => (
                                                <Tag color={v === 'paid' ? 'success' : v === 'partial' ? 'warning' : 'error'} style={{ borderRadius: 10, fontSize: 10 }}>
                                                    {v?.toUpperCase()}
                                                </Tag>
                                            ),
                                        },
                                        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 100, render: (v) => <Money value={v} /> },
                                    ]}
                                />
                            </Card>
                        </Col>
                        <Col xs={24} lg={12}>
                            {!isMr && (
                                <Card title="Recent Purchases" size="small" loading={loading} extra={<Button type="link" size="small" href={appUrl('/app/purchases/bills')}>View All</Button>}>
                                    <Table
                                        rowKey="id"
                                        size="small"
                                        scroll={{ x: 400 }}
                                        pagination={false}
                                        dataSource={data?.recent_purchases?.slice(0, 5) || []}
                                        columns={[
                                            { title: 'Bill', dataIndex: 'purchase_no', width: 100 },
                                            { title: 'Supplier', dataIndex: 'supplier_name', render: (v) => <span style={{fontWeight: 500}}>{v}</span> },
                                            {
                                                title: 'Status', dataIndex: 'payment_status', width: 90,
                                                render: (v) => (
                                                    <Tag color={v === 'paid' ? 'success' : v === 'partial' ? 'warning' : 'error'} style={{ borderRadius: 10, fontSize: 10 }}>
                                                        {v?.toUpperCase()}
                                                    </Tag>
                                                ),
                                            },
                                            { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 100, render: (v) => <Money value={v} /> },
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
            label: <span style={{ fontWeight: 600 }}>Analytics</span>,
            children: (
                <div className="page-stack" style={{ marginTop: 16 }}>
                    <Row gutter={[16, 16]}>
                        <Col xs={24} xl={15}>
                            <Card
                                title="Sales vs Purchases"
                                size="small"
                                loading={loading}
                            >
                                <BarChart
                                    data={trendBars}
                                    height={280}
                                    legend={['Sales', 'Purchases']}
                                    colors={[TONES.sales, TONES.purchase]}
                                />
                            </Card>
                        </Col>
                        <Col xs={24} xl={9}>
                            <Card
                                title="Payment Distribution"
                                size="small"
                                loading={loading}
                            >
                                <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: 280 }}>
                                    {pieData.length > 0
                                        ? <DonutChart data={pieData} size={180} />
                                        : <Empty description="No data" />
                                    }
                                </div>
                            </Card>
                        </Col>
                    </Row>
                    <Row gutter={[16, 16]}>
                        <Col xs={24}>
                            <Card title="Top Performing Products" size="small" loading={loading}>
                                <Table
                                    rowKey="id"
                                    scroll={{ x: 500 }}
                                    pagination={false}
                                    dataSource={topProducts}
                                    columns={[
                                        { title: 'Product', dataIndex: 'name', render: (v) => <span style={{fontWeight: 500}}>{v}</span> },
                                        {
                                            title: 'Qty',
                                            dataIndex: 'quantity',
                                            align: 'right',
                                            width: 80,
                                            render: (v) => (+v).toFixed(0),
                                        },
                                        {
                                            title: 'Revenue Distribution',
                                            dataIndex: 'amount',
                                            render: (v) => (
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                                    <span style={{ minWidth: 90, textAlign: 'right', fontWeight: 600 }}>
                                                        <Money value={v || 0} />
                                                    </span>
                                                    <div style={{ flex: 1 }}>
                                                        <MiniBar value={v || 0} max={maxProductAmt} color={TONES.sales} />
                                                    </div>
                                                </div>
                                            ),
                                        },
                                    ]}
                                    locale={{ emptyText: <Empty description="No sales data" /> }}
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
                    Alerts {isAlertsHigh && <Badge count={stats.low_stock + stats.expiring_batches} size="small" style={{ marginLeft: 4 }} />}
                </span>
            ),
            children: (
                <div className="page-stack" style={{ marginTop: 16 }}>
                    <Row gutter={[16, 16]}>
                        <Col xs={24} xl={12}>
                            <Card title="Low Stock Items" size="small" loading={loading}>
                                <Table
                                    rowKey="id"
                                    size="small"
                                    scroll={{ x: 300 }}
                                    pagination={false}
                                    dataSource={data?.low_stock_rows || []}
                                    columns={[
                                        { title: 'Product', dataIndex: 'name', render: (v) => <span style={{fontWeight: 500}}>{v}</span> },
                                        {
                                            title: 'Stock / Min', dataIndex: 'stock_on_hand', width: 140,
                                            render: (v, r) => (
                                                <Space>
                                                    <Tag color="error" style={{ borderRadius: 6 }}>{(+v).toFixed(0)}</Tag>
                                                    <span style={{ color: '#94a3b8' }}>/ {(+r.reorder_level).toFixed(0)}</span>
                                                </Space>
                                            ),
                                        },
                                    ]}
                                />
                            </Card>
                        </Col>
                        <Col xs={24} xl={12}>
                            <Card title="Expiry Watch" size="small" loading={loading}>
                                <Table
                                    rowKey="id"
                                    size="small"
                                    scroll={{ x: 300 }}
                                    pagination={false}
                                    dataSource={data?.expiry_rows || []}
                                    columns={[
                                        { title: 'Product', dataIndex: 'name', render: (v) => <span style={{fontWeight: 500}}>{v}</span> },
                                        { title: 'Batch', dataIndex: 'batch_no', width: 90 },
                                        { title: 'Expires', dataIndex: 'expires_at', width: 100, render: (v) => <span style={{ color: '#f59e0b', fontWeight: 600 }}>{v}</span> },
                                    ]}
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
            <div className="dashboard-header-modern">
                <div>
                    <Typography.Title level={4} style={{ margin: 0 }}>Dashboard</Typography.Title>
                    <Typography.Text type="secondary">Welcome back to {appName} management portal</Typography.Text>
                </div>
                <Space wrap>
                    {!user?.medical_representative_id && medicalRepresentatives.length > 0 && (
                        <Select
                            allowClear
                            placeholder="All MRs"
                            size="small"
                            style={{ minWidth: 160 }}
                            value={medicalRepresentativeId}
                            onChange={setMedicalRepresentativeId}
                            options={medicalRepresentatives.map((m) => ({ value: m.id, label: m.name }))}
                        />
                    )}
                    <DatePicker.RangePicker size="small" value={range} onChange={setRange} />
                    <Button size="small" icon={<ReloadOutlined />} onClick={loadSummary}>Sync</Button>
                </Space>
            </div>

            <Card className="dashboard-hero-compact glass-card">
                <Row gutter={[24, 24]} align="middle">
                    <Col xs={24} md={14}>
                        <div className="hero-welcome">
                            <Tag color="blue" style={{ borderRadius: 4, marginBottom: 8 }}>Operational Summary</Tag>
                            <Typography.Title level={3} style={{ marginTop: 0, marginBottom: 8 }}>Pharmacy Insights</Typography.Title>
                            <Typography.Text style={{ opacity: 0.8 }}>
                                Track your inventory health, sales performance and financial status in real-time.
                            </Typography.Text>
                            <div style={{ marginTop: 20 }}>
                                <Space>
                                    <Button type="primary" href={appUrl('/app/sales/pos')} icon={<PlusOutlined />}>POS</Button>
                                    <Button href={appUrl('/app/inventory/products')}>Inventory</Button>
                                </Space>
                            </div>
                        </div>
                    </Col>
                    <Col xs={24} md={10}>
                        <div className="hero-quick-stats">
                            <div className="quick-stat-item">
                                <span className="label">Period Revenue</span>
                                <span className="value"><Money value={stats.period_sales} /></span>
                            </div>
                            <div className="quick-stat-divider" />
                            <div className="quick-stat-item">
                                <span className="label">Outstanding</span>
                                <span className="value" style={{ color: '#ef4444' }}><Money value={stats.receivables} /></span>
                            </div>
                        </div>
                    </Col>
                </Row>
            </Card>

            <Tabs 
                defaultActiveKey="overview" 
                items={items} 
                className="dashboard-tabs"
                animated
            />
        </div>
    );
}

