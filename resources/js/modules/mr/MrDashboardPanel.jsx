import React, { useEffect, useState } from 'react';
import { Card, Col, Row, Select, Statistic, Table } from 'antd';
import { Money } from '../../core/components/Money';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { PharmaBadge } from '../../core/components/PharmaBadge';

function MiniBar({ value = 0, max = 1, color = '#10b981' }) {
    const numericValue = Number(value) || 0;
    const numericMax = Math.max(Number(max) || 1, 1);
    const percent = Math.max(0, Math.min((numericValue / numericMax) * 100, 100));

    return (
        <div
            style={{
                width: '100%',
                height: 8,
                borderRadius: 999,
                background: '#e5e7eb',
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    width: `${percent}%`,
                    height: '100%',
                    borderRadius: 999,
                    background: color,
                    transition: 'width 0.2s ease',
                }}
            />
        </div>
    );
}

export function MrDashboardPanel({ section, branchOptions, mrOptions }) {
    const [dateRange, setDateRange] = useState([]);
    const [branchId, setBranchId] = useState(undefined);
    const [mrId, setMrId] = useState(undefined);

    const [perfData, setPerfData] = useState(null);
    const [perfLoading, setPerfLoading] = useState(false);

    const [salesData, setSalesData] = useState(null);
    const [salesLoading, setSalesLoading] = useState(false);

    const fromDate = dateRange?.[0]?.format('YYYY-MM-DD');
    const toDate = dateRange?.[1]?.format('YYYY-MM-DD');

    useEffect(() => {
        let active = true;

        async function loadPerformance() {
            setPerfLoading(true);
            try {
                const { data } = await http.get(endpoints.mrPerformance, {
                    params: {
                        ...(fromDate ? { from: fromDate } : {}),
                        ...(toDate ? { to: toDate } : {}),
                        medical_representative_id: mrId,
                    },
                });
                if (active) setPerfData(data.data);
            } finally {
                if (active) setPerfLoading(false);
            }
        }

        async function loadBranchSales() {
            setSalesLoading(true);
            try {
                const { data } = await http.get(endpoints.mrBranchSales, {
                    params: {
                        ...(fromDate ? { from: fromDate } : {}),
                        ...(toDate ? { to: toDate } : {}),
                        branch_id: branchId,
                        mr_id: mrId,
                    },
                });
                if (active) setSalesData(data.data);
            } finally {
                if (active) setSalesLoading(false);
            }
        }

        loadPerformance();
        loadBranchSales();

        return () => { active = false; };
    }, [dateRange, branchId, mrId, fromDate, toDate]);

    const branchSalesRows = salesData?.rows || [];
    const maxSalesVal = Math.max(...branchSalesRows.map((row) => row.total_value || 0), 1);

    const branchSalesColumns = [
        {
            title: 'SN',
            key: '__serial',
            width: 68,
            align: 'center',
            className: 'table-serial-cell',
            render: (_, __, index) => index + 1,
        },
        {
            title: 'Branch',
            dataIndex: 'branch_name',
            width: 140,
        },
        {
            title: 'MR',
            dataIndex: 'mr_name',
            width: 130,
        },
        {
            title: 'Product',
            dataIndex: 'product_name',
        },
        {
            title: 'Qty',
            dataIndex: 'total_qty',
            align: 'right',
            width: 80,
            render: (value) => Number(value || 0).toFixed(0),
        },
        {
            title: 'Value',
            dataIndex: 'total_value',
            align: 'right',
            width: 260,
            render: (value) => (
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ minWidth: 90, textAlign: 'right' }}>
                        <Money value={value} />
                    </span>
                    <div style={{ flex: 1 }}>
                        <MiniBar value={value} max={maxSalesVal} color="#10b981" />
                    </div>
                </div>
            ),
        },
    ];

    const totals = perfData?.totals || {};

    return (
        <>
            <Card size="small" style={{ marginBottom: 16 }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    <SmartDatePicker.RangePicker value={dateRange} onChange={setDateRange} />

                    <Select
                        allowClear
                        placeholder="All Branches"
                        value={branchId}
                        onChange={setBranchId}
                        style={{ minWidth: 180 }}
                        options={branchOptions.map((branch) => ({
                            value: branch.id,
                            label: branch.name,
                        }))}
                    />

                    <Select
                        allowClear
                        placeholder="All MRs"
                        value={mrId}
                        onChange={setMrId}
                        style={{ minWidth: 160 }}
                        options={mrOptions.map((mr) => ({
                            value: mr.id,
                            label: mr.name,
                        }))}
                    />
                </div>
            </Card>

            {section === 'dashboard' && (
                <>
                    <Row gutter={[16, 16]}>
                        <Col xs={12} md={6}>
                            <Card
                                className="metric-card metric-card-glow glass-card"
                                loading={perfLoading}
                                style={{ borderTop: '4px solid #2563eb' }}
                            >
                                <Statistic
                                    title={<span style={{ fontWeight: 600 }}>Active MRs</span>}
                                    value={totals.active_mrs ?? '—'}
                                />
                            </Card>
                        </Col>

                        <Col xs={12} md={6}>
                            <Card
                                className="metric-card metric-card-glow glass-card"
                                loading={perfLoading}
                                style={{ borderTop: '4px solid #0891b2' }}
                            >
                                <Statistic
                                    title={<span style={{ fontWeight: 600 }}>Visits</span>}
                                    value={totals.visits ?? '—'}
                                />
                            </Card>
                        </Col>

                        <Col xs={12} md={6}>
                            <Card
                                className="metric-card metric-card-glow glass-card"
                                loading={perfLoading}
                                style={{ borderTop: '4px solid #7c3aed' }}
                            >
                                <Statistic
                                    title={<span style={{ fontWeight: 600 }}>Total Sales</span>}
                                    value={totals.invoiced_value ?? 0}
                                    prefix="NPR"
                                    precision={2}
                                />
                            </Card>
                        </Col>

                        <Col xs={12} md={6}>
                            <Card
                                className="metric-card metric-card-glow glass-card"
                                loading={salesLoading}
                                style={{ borderTop: '4px solid #10b981' }}
                            >
                                <Statistic
                                    title={<span style={{ fontWeight: 600 }}>Grand Total (Products)</span>}
                                    value={salesData?.grand_total ?? 0}
                                    prefix="NPR"
                                    precision={2}
                                />
                            </Card>
                        </Col>
                    </Row>

                    <Card
                        title="Product Sales by Branch & MR"
                        loading={salesLoading}
                        extra={<PharmaBadge tone="info">{salesData?.period}</PharmaBadge>}
                    >
                        <Table
                            rowKey={(record) => (
                                `${record.branch_id || 'branch'}-${record.mr_id || 'mr'}-${record.product_id || record.product_name}`
                            )}
                            dataSource={salesData?.rows || []}
                            columns={branchSalesColumns}
                            pagination={{
                                pageSize: 15,
                                showSizeChanger: true,
                                pageSizeOptions: ['10', '15', '20', '25', '50', '100'],
                            }}
                            scroll={{ x: 700 }}
                            size="small"
                        />
                    </Card>
                </>
            )}

            {section === 'performance' && (
                <Card title="MR Performance" loading={perfLoading}>
                    <Table
                        rowKey="id"
                        dataSource={perfData?.rows || []}
                        columns={[
                            {
                                title: 'SN',
                                key: '__serial',
                                width: 68,
                                align: 'center',
                                className: 'table-serial-cell',
                                render: (_, __, index) => index + 1,
                            },
                            {
                                title: 'MR',
                                dataIndex: 'name',
                            },
                            {
                                title: 'Area',
                                dataIndex: 'area',
                                render: (value) => value || '—',
                            },
                            {
                                title: 'Division',
                                dataIndex: 'division',
                                render: (value) => value || '—',
                            },
                            {
                                title: 'Visits',
                                dataIndex: 'visits',
                                align: 'right',
                                width: 80,
                            },
                            {
                                title: 'Orders',
                                dataIndex: 'visit_order_value',
                                align: 'right',
                                width: 130,
                                render: (value) => <Money value={value} />,
                            },
                            {
                                title: 'Invoiced',
                                dataIndex: 'invoiced_value',
                                align: 'right',
                                width: 130,
                                render: (value) => <Money value={value} />,
                            },
                            {
                                title: 'Target',
                                dataIndex: 'monthly_target',
                                align: 'right',
                                width: 130,
                                render: (value) => <Money value={value} />,
                            },
                            {
                                title: 'Achievement',
                                dataIndex: 'achievement_percent',
                                width: 110,
                                align: 'right',
                                render: (value) => {
                                    const pct = Math.min(value || 0, 100);
                                    const color = pct >= 100
                                        ? '#52c41a'
                                        : pct >= 70
                                            ? '#faad14'
                                            : '#ff4d4f';

                                    return (
                                        <span style={{ color, fontWeight: 600 }}>
                                            {pct.toFixed(1)}%
                                        </span>
                                    );
                                },
                            },
                        ]}
                        scroll={{ x: 800 }}
                        pagination={{
                            pageSize: 15,
                            showSizeChanger: true,
                            pageSizeOptions: ['10', '15', '20', '25', '50', '100'],
                        }}
                        size="small"
                    />
                </Card>
            )}
        </>
    );
}
