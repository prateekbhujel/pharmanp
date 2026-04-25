import React from 'react';
import { Card, Col, Progress, Row, Statistic, Table } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { endpoints } from '../../core/api/endpoints';
import { useApi } from '../../core/hooks/useApi';

export function MrPerformancePage() {
    const { data, loading } = useApi(endpoints.mrPerformance);
    const totals = data?.totals || {};

    return (
        <div className="page-stack">
            <PageHeader
                title="MR Performance"
                description={`Target, visit, order and invoiced value tracking for ${data?.period || 'current month'}`}
            />

            <Row gutter={[16, 16]}>
                <Col xs={24} md={6}><Card><Statistic title="Active MRs" value={totals.active_mrs || 0} /></Card></Col>
                <Col xs={24} md={6}><Card><Statistic title="Visits" value={totals.visits || 0} /></Card></Col>
                <Col xs={24} md={6}><Card><Statistic title="Visit Order Value" value={totals.visit_order_value || 0} prefix="Rs." precision={2} /></Card></Col>
                <Col xs={24} md={6}><Card><Statistic title="Invoiced Value" value={totals.invoiced_value || 0} prefix="Rs." precision={2} /></Card></Col>
            </Row>

            <Card>
                <Table
                    loading={loading}
                    rowKey="id"
                    dataSource={data?.rows || []}
                    pagination={{ pageSize: 10 }}
                    columns={[
                        { title: 'MR', dataIndex: 'name' },
                        { title: 'Territory', dataIndex: 'territory' },
                        { title: 'Visits', dataIndex: 'visits', align: 'right' },
                        { title: 'Orders', dataIndex: 'visit_order_value', align: 'right', render: (value) => <Money value={value} /> },
                        { title: 'Invoices', dataIndex: 'invoices', align: 'right' },
                        { title: 'Sales', dataIndex: 'invoiced_value', align: 'right', render: (value) => <Money value={value} /> },
                        { title: 'Target', dataIndex: 'monthly_target', align: 'right', render: (value) => <Money value={value} /> },
                        { title: 'Achievement', dataIndex: 'achievement_percent', width: 160, render: (value) => <Progress percent={Math.min(value || 0, 100)} size="small" /> },
                    ]}
                />
            </Card>
        </div>
    );
}
