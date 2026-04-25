import React from 'react';
import { Card, Col, Empty, Row, Statistic, Table, Tag } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { endpoints } from '../../core/api/endpoints';
import { useApi } from '../../core/hooks/useApi';
import { money } from '../../core/utils/formatters';

function StatCard({ title, value, suffix, tone }) {
    return (
        <Card className="metric-card">
            <Statistic title={title} value={value || 0} suffix={suffix} valueStyle={{ color: tone }} />
        </Card>
    );
}

export function DashboardPage() {
    const { data, loading } = useApi(endpoints.dashboard);
    const stats = data?.stats || {};

    return (
        <div className="page-stack">
            <PageHeader
                title="Operations Dashboard"
                description={`Sales, stock, expiry and working capital snapshot for ${data?.period || 'current period'}`}
            />

            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} xl={6}><StatCard title="Today Sales" value={stats.today_sales} tone="#0f766e" /></Col>
                <Col xs={24} sm={12} xl={6}><StatCard title="Month Sales" value={stats.month_sales} tone="#2563eb" /></Col>
                <Col xs={24} sm={12} xl={6}><StatCard title="Low Stock" value={stats.low_stock} suffix="items" tone="#dc2626" /></Col>
                <Col xs={24} sm={12} xl={6}><StatCard title="Expiring Batches" value={stats.expiring_batches} suffix="batches" tone="#ca8a04" /></Col>
            </Row>

            <Row gutter={[16, 16]}>
                <Col xs={24} xl={16}>
                    <Card title="Top Selling Products" loading={loading}>
                        <Table
                            rowKey="id"
                            pagination={false}
                            dataSource={data?.top_products || []}
                            columns={[
                                { title: 'Product', dataIndex: 'name' },
                                { title: 'Qty', dataIndex: 'quantity', align: 'right', width: 100 },
                                { title: 'Amount', dataIndex: 'amount', align: 'right', render: (value) => money.format(value || 0), width: 150 },
                            ]}
                            locale={{ emptyText: <Empty description="No sales posted yet" /> }}
                        />
                    </Card>
                </Col>
                <Col xs={24} xl={8}>
                    <Card title="Working Capital">
                        <div className="finance-stack">
                            <div><span>Receivables</span><strong><Money value={stats.receivables} /></strong></div>
                            <div><span>Payables</span><strong><Money value={stats.payables} /></strong></div>
                            <div><span>Month Purchase</span><strong><Money value={stats.month_purchase} /></strong></div>
                            <div><span>MR Month Orders</span><strong><Money value={data?.mr?.month_orders} /></strong></div>
                        </div>
                    </Card>
                </Col>
            </Row>

            <Row gutter={[16, 16]}>
                <Col xs={24} lg={12}>
                    <Card title="Recent Sales" loading={loading}>
                        <Table
                            rowKey="id"
                            size="small"
                            pagination={false}
                            dataSource={data?.recent_sales || []}
                            columns={[
                                { title: 'Invoice', dataIndex: 'invoice_no' },
                                { title: 'Customer', dataIndex: 'customer_name', render: (value) => value || 'Walk-in' },
                                { title: 'Total', dataIndex: 'grand_total', align: 'right', render: (value) => <Money value={value} /> },
                            ]}
                        />
                    </Card>
                </Col>
                <Col xs={24} lg={12}>
                    <Card title="Recent Purchases" loading={loading}>
                        <Table
                            rowKey="id"
                            size="small"
                            pagination={false}
                            dataSource={data?.recent_purchases || []}
                            columns={[
                                { title: 'Bill', dataIndex: 'purchase_no' },
                                { title: 'Supplier', dataIndex: 'supplier_name' },
                                { title: 'Total', dataIndex: 'grand_total', align: 'right', render: (value) => <Money value={value} /> },
                            ]}
                        />
                    </Card>
                </Col>
            </Row>

            <Card>
                <Tag color="blue">Server-side tables</Tag>
                <Tag color="green">Session API</Tag>
                <Tag color="gold">Shared-hosting ready</Tag>
                <Tag color="purple">Company/store scoped</Tag>
            </Card>
        </div>
    );
}
