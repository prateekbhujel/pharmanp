import React, { useEffect, useState } from 'react';
import { App, Button, Card, Col, DatePicker, Empty, Row, Select, Space, Statistic, Table, Tag } from 'antd';
import dayjs from 'dayjs';
import { ReloadOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useAuth } from '../../core/auth/AuthProvider';

function StatCard({ title, value, suffix, tone }) {
    return (
        <Card className="metric-card">
            <Statistic title={title} value={value || 0} suffix={suffix} valueStyle={{ color: tone }} />
        </Card>
    );
}

export function DashboardPage() {
    const { notification } = App.useApp();
    const { user } = useAuth();
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
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

    return (
        <div className="page-stack">
            <PageHeader
                title={representativeScope ? 'MR Dashboard' : 'Dashboard'}
                description={representativeScope
                    ? `Visits, invoices and target tracking for ${data?.period || 'current period'}`
                    : `Overview of stock, purchases, alerts and quick admin actions for ${data?.period || 'current period'}`}
                actions={(
                    <Space wrap>
                        {!user?.medical_representative_id && medicalRepresentatives.length > 0 && (
                            <Select
                                allowClear
                                placeholder="All MRs"
                                style={{ minWidth: 220 }}
                                value={medicalRepresentativeId}
                                onChange={setMedicalRepresentativeId}
                                options={medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))}
                            />
                        )}
                        <DatePicker.RangePicker value={range} onChange={setRange} />
                        <Button icon={<ReloadOutlined />} onClick={loadSummary}>Refresh</Button>
                    </Space>
                )}
            />

            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} xl={6}><StatCard title="Today Sales" value={stats.today_sales} tone="#0f766e" /></Col>
                <Col xs={24} sm={12} xl={6}><StatCard title="This Month Sales" value={stats.period_sales} tone="#2563eb" /></Col>
                {representativeScope ? (
                    <>
                        <Col xs={24} sm={12} xl={6}><StatCard title="Visits" value={stats.visits} suffix="visits" tone="#7c3aed" /></Col>
                        <Col xs={24} sm={12} xl={6}><StatCard title="Target" value={stats.target} tone="#ca8a04" /></Col>
                    </>
                ) : (
                    <>
                        <Col xs={24} sm={12} xl={6}><StatCard title="Low Stock" value={stats.low_stock} suffix="items" tone="#dc2626" /></Col>
                        <Col xs={24} sm={12} xl={6}><StatCard title="Expiring Batches" value={stats.expiring_batches} suffix="batches" tone="#ca8a04" /></Col>
                    </>
                )}
            </Row>

            {!representativeScope && (
                <Row gutter={[16, 16]}>
                    <Col xs={24} sm={12} xl={6}><StatCard title="This Month Purchase" value={stats.period_purchase} tone="#0f766e" /></Col>
                    <Col xs={24} sm={12} xl={6}><StatCard title="Receivables" value={stats.receivables} tone="#ea580c" /></Col>
                    <Col xs={24} sm={12} xl={6}><StatCard title="Payables" value={stats.payables} tone="#9333ea" /></Col>
                    <Col xs={24} sm={12} xl={6}><StatCard title="Products" value={stats.products} suffix="items" tone="#0891b2" /></Col>
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
                                    { title: 'Status', dataIndex: 'status', width: 120 },
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
