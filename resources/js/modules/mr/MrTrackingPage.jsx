import React, { useEffect, useState } from 'react';
import {
    App,
    Button,
    Card,
    Col,
    Drawer,
    Form,
    Input,
    InputNumber,
    Row,
    Select,
    Space,
    Statistic,
    Switch,
    Table,
    Tooltip,
} from 'antd';
import { DeleteOutlined, EditOutlined, EnvironmentOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { PharmaBadge, StatusBadge } from '../../core/components/PharmaBadge';
import { PageHeader } from '../../core/components/PageHeader';
import { FormDrawer } from '../../core/components/FormDrawer';
import { ServerTable } from '../../core/components/ServerTable';
import { Money } from '../../core/components/Money';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { LocationSearch } from '../../core/components/LocationSearch';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useAuth } from '../../core/auth/AuthProvider';
import { can } from '../../core/utils/permissions';
import { mrVisitStatusOptions } from '../../core/utils/accountCatalog';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';

const fieldForceSections = {
    dashboard: { title: 'Dashboard' },
    performance: { title: 'Performance' },
    representatives: { title: 'Representatives' },
    visits: { title: 'Visits' },
    branches: { title: 'Branches' },
};

function currentSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    return fieldForceSections[section] ? section : 'dashboard';
}

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

export function MrTrackingPage() {
    const { notification } = App.useApp();
    const { user } = useAuth();

    const [branchOptions, setBranchOptions] = useState([]);
    const [areaOptions, setAreaOptions] = useState([]);
    const [divisionOptions, setDivisionOptions] = useState([]);
    const [mrOptions, setMrOptions] = useState([]);
    const [customers, setCustomers] = useState([]);

    const [dateRange, setDateRange] = useState([]);
    const [visitRange, setVisitRange] = useState([]);
    const [branchId, setBranchId] = useState(undefined);
    const [mrId, setMrId] = useState(undefined);

    const [perfData, setPerfData] = useState(null);
    const [perfLoading, setPerfLoading] = useState(false);

    const [salesData, setSalesData] = useState(null);
    const [salesLoading, setSalesLoading] = useState(false);

    const canManage = user?.is_owner || can(user, 'mr.manage');
    const canVisits = canManage || can(user, 'mr.visits.manage');

    const section = currentSection();

    const branchTable = useServerTable({
        endpoint: endpoints.mrBranches,
        defaultSort: { field: 'name', order: 'asc' },
        enabled: canManage && section === 'branches',
    });

    const visitTable = useServerTable({
        endpoint: endpoints.mrVisits,
        defaultSort: { field: 'visit_date', order: 'desc' },
        enabled: section === 'visits' && canVisits,
    });

    const mrTable = useServerTable({
        endpoint: endpoints.mrRepresentatives,
        defaultSort: { field: 'name', order: 'asc' },
        enabled: section === 'representatives' && canManage,
    });

    const [view, setView] = useState('list');
    const [branchDrawerOpen, setBranchDrawerOpen] = useState(false);
    const [editingMr, setEditingMr] = useState(null);
    const [editingVisit, setEditingVisit] = useState(null);
    const [editingBranch, setEditingBranch] = useState(null);
    const [mapVisit, setMapVisit] = useState(null);

    const [mrForm] = Form.useForm();
    const [visitForm] = Form.useForm();
    const [branchForm] = Form.useForm();

    const fromDate = dateRange?.[0]?.format('YYYY-MM-DD');
    const toDate = dateRange?.[1]?.format('YYYY-MM-DD');

    useEffect(() => {
        loadLookups();
    }, []);

    useEffect(() => {
        loadPerformance();
        loadBranchSales();
    }, [dateRange, branchId, mrId]);

    async function loadLookups() {
        try {
            const [{ data: br }, { data: mr }, { data: cu }, { data: areas }, { data: divisions }] = await Promise.all([
                http.get(endpoints.mrBranchOptions),
                http.get(endpoints.mrOptions),
                http.get(endpoints.customerOptions),
                http.get(endpoints.setupAreaOptions),
                http.get(endpoints.setupDivisionOptions),
            ]);

            setBranchOptions(br.data || []);
            setMrOptions(mr.data || []);
            setCustomers(cu.data || []);
            setAreaOptions(areas.data || []);
            setDivisionOptions(divisions.data || []);
        } catch {
            // silent
        }
    }

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

            setPerfData(data.data);
        } finally {
            setPerfLoading(false);
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

            setSalesData(data.data);
        } finally {
            setSalesLoading(false);
        }
    }

    function openMr(record = null) {
        setView('mr');
        setEditingMr(record);
        mrForm.resetFields();
        mrForm.setFieldsValue(record || { is_active: true, monthly_target: 0 });
    }

    async function saveMr(values) {
        try {
            if (editingMr) {
                await http.put(`${endpoints.mrRepresentatives}/${editingMr.id}`, values);
                notification.success({ message: 'MR updated' });
            } else {
                await http.post(endpoints.mrRepresentatives, values);
                notification.success({ message: 'MR created' });
            }

            setView('list');
            mrTable.reload();
            loadLookups();
            loadPerformance();
        } catch (e) {
            mrForm.setFields(
                Object.entries(validationErrors(e)).map(([name, errors]) => ({ name, errors })),
            );

            notification.error({
                message: 'Save failed',
                description: e?.response?.data?.message,
            });
        }
    }

    function deleteMr(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            onOk: async () => {
                await http.delete(`${endpoints.mrRepresentatives}/${record.id}`);
                notification.success({ message: 'MR deleted' });
                mrTable.reload();
                loadLookups();
            },
        });
    }

    function openVisit(record = null) {
        setEditingVisit(record);
        visitForm.resetFields();

        visitForm.setFieldsValue(
            record
                ? {
                    ...record,
                    medical_representative_id:
                        record.medical_representative_id ?? record.medical_representative?.id,
                    customer_id: record.customer_id ?? record.customer?.id,
                    visit_date: record.visit_date ? dayjs(record.visit_date) : dayjs(),
                }
                : {
                    visit_date: dayjs(),
                    status: 'planned',
                    order_value: 0,
                },
        );

        setView('visit');
    }

    async function saveVisit(values) {
        try {
            const payload = {
                ...values,
                visit_date: values.visit_date.format('YYYY-MM-DD'),
            };

            if (editingVisit) {
                await http.put(`${endpoints.mrVisits}/${editingVisit.id}`, payload);
                notification.success({ message: 'Visit updated' });
            } else {
                await http.post(endpoints.mrVisits, payload);
                notification.success({ message: 'Visit created' });
            }

            setView('list');
            visitTable.reload();
            loadPerformance();
        } catch (e) {
            visitForm.setFields(
                Object.entries(validationErrors(e)).map(([name, errors]) => ({ name, errors })),
            );

            notification.error({ message: 'Save failed' });
        }
    }

    function deleteVisit(record) {
        confirmDelete({
            title: 'Delete this visit?',
            onOk: async () => {
                await http.delete(`${endpoints.mrVisits}/${record.id}`);
                notification.success({ message: 'Visit deleted' });
                visitTable.reload();
            },
        });
    }

    function updateVisitRange(range) {
        setVisitRange(range || []);
        visitTable.setFilters((current) => ({
            ...applyDateRangeFilter(current, range),
        }));
    }

    function openBranch(record = null) {
        setEditingBranch(record);
        branchForm.resetFields();
        branchForm.setFieldsValue(record || { type: 'branch', is_active: true });
        setBranchDrawerOpen(true);
    }

    async function saveBranch(values) {
        try {
            if (editingBranch) {
                await http.put(`${endpoints.mrBranches}/${editingBranch.id}`, values);
                notification.success({ message: 'Branch updated' });
            } else {
                await http.post(endpoints.mrBranches, values);
                notification.success({ message: 'Branch created' });
            }

            setBranchDrawerOpen(false);
            branchTable.reload();
            loadLookups();
        } catch (e) {
            branchForm.setFields(
                Object.entries(validationErrors(e)).map(([name, errors]) => ({ name, errors })),
            );

            notification.error({ message: 'Save failed' });
        }
    }

    function deleteBranch(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            onOk: async () => {
                await http.delete(`${endpoints.mrBranches}/${record.id}`);
                notification.success({ message: 'Branch deleted' });
                branchTable.reload();
                loadLookups();
            },
        });
    }

    function captureLocation() {
        if (!navigator.geolocation) {
            notification.warning({ message: 'Geolocation is not supported by this browser' });
            return;
        }

        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                const lat = pos.coords.latitude.toFixed(7);
                const lon = pos.coords.longitude.toFixed(7);
                visitForm.setFieldsValue({ latitude: lat, longitude: lon });

                try {
                    const response = await fetch(
                        `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json&addressdetails=1`,
                        { headers: { 'Accept-Language': 'en' } },
                    );
                    const data = await response.json();
                    if (data.display_name) {
                        visitForm.setFieldValue('location_name', data.display_name);
                    }
                } catch {
                    visitForm.setFieldValue('location_name', visitForm.getFieldValue('location_name') || `${lat}, ${lon}`);
                }

                notification.success({ message: 'Location captured and resolved' });
            },
            () => notification.warning({ message: 'Location access denied' }),
        );
    }

    function handleLocationSelect(locationName, coords) {
        visitForm.setFieldValue('location_name', locationName);
        if (coords) {
            visitForm.setFieldsValue({ latitude: coords.lat, longitude: coords.lon });
        }
    }

    const branchColumns = [
        {
            title: 'Name',
            dataIndex: 'name',
            sorter: true,
            field: 'name',
        },
        {
            title: 'Code',
            dataIndex: 'code',
            width: 110,
        },
        {
            title: 'Type',
            dataIndex: 'type',
            width: 110,
            render: (value) => (
                <PharmaBadge tone={value === 'hq' ? 'info' : 'neutral'}>
                    {value?.toUpperCase()}
                </PharmaBadge>
            ),
        },
        {
            title: 'Parent HQ',
            dataIndex: ['parent', 'name'],
            render: (value) => value || '—',
        },
        {
            title: 'Address',
            dataIndex: 'address',
        },
        {
            title: 'Status',
            dataIndex: 'is_active',
            width: 120,
            render: (value) => <StatusBadge value={value} />,
        },
        canManage
            ? {
                title: 'Action',
                width: 96,
                render: (_, record) => (
                    <Space>
                        <Button
                            size="small"
                            icon={<EditOutlined />}
                            onClick={() => openBranch(record)}
                        />
                        <Button
                            size="small"
                            danger
                            icon={<DeleteOutlined />}
                            onClick={() => deleteBranch(record)}
                        />
                    </Space>
                ),
            }
            : null,
    ].filter(Boolean);

    const mrColumns = [
        {
            title: 'Name',
            dataIndex: 'name',
            sorter: true,
            field: 'name',
        },
        {
            title: 'Code',
            dataIndex: 'employee_code',
            width: 110,
        },
        {
            title: 'Branch',
            dataIndex: ['branch', 'name'],
            render: (value) => value || <span style={{ color: '#aaa' }}>—</span>,
        },
        {
            title: 'Area',
            dataIndex: ['area', 'name'],
            width: 140,
            render: (value) => value || '—',
        },
        {
            title: 'Division',
            dataIndex: ['division', 'name'],
            width: 140,
            render: (value) => value || '—',
        },
        {
            title: 'Target',
            dataIndex: 'monthly_target',
            align: 'right',
            width: 130,
            render: (value) => <Money value={value} />,
        },
        {
            title: 'Status',
            dataIndex: 'is_active',
            width: 120,
            render: (value) => <StatusBadge value={value} />,
        },
        canManage
            ? {
                title: 'Action',
                width: 96,
                render: (_, record) => (
                    <Space>
                        <Button
                            size="small"
                            icon={<EditOutlined />}
                            onClick={() => openMr(record)}
                        />
                        <Button
                            size="small"
                            danger
                            icon={<DeleteOutlined />}
                            onClick={() => deleteMr(record)}
                        />
                    </Space>
                ),
            }
            : null,
    ].filter(Boolean);

    const visitColumns = [
        {
            title: 'Date',
            dataIndex: 'visit_date',
            width: 110,
            sorter: true,
            field: 'visit_date',
            render: (value) => <DateText value={value} style="compact" />,
        },
        {
            title: 'MR',
            dataIndex: ['medical_representative', 'name'],
            render: (value) => value || '—',
        },
        {
            title: 'Customer',
            dataIndex: ['customer', 'name'],
            render: (value) => value || '—',
        },
        {
            title: 'Status',
            dataIndex: 'status',
            width: 120,
            render: (value) => (
                <PharmaBadge tone={value} dot>
                    {value}
                </PharmaBadge>
            ),
        },
        {
            title: 'Order Value',
            dataIndex: 'order_value',
            align: 'right',
            width: 130,
            render: (value) => <Money value={value} />,
        },
        {
            title: 'Location',
            width: 180,
            render: (_, record) => (
                record.location_name || record.has_coordinates ? (
                    <Tooltip title={record.location_name || 'Captured location'}>
                        <Button
                            type="link"
                            icon={<EnvironmentOutlined style={{ color: '#52c41a' }} />}
                            onClick={() => setMapVisit(record)}
                        >
                            {record.location_name || 'View location'}
                        </Button>
                    </Tooltip>
                ) : (
                    <span style={{ color: '#ccc' }}>—</span>
                )
            ),
        },
        canVisits
            ? {
                title: 'Action',
                width: 96,
                render: (_, record) => (
                    <Space>
                        <Button
                            size="small"
                            icon={<EditOutlined />}
                            onClick={() => openVisit(record)}
                        />
                        <Button
                            size="small"
                            danger
                            icon={<DeleteOutlined />}
                            onClick={() => deleteVisit(record)}
                        />
                    </Space>
                ),
            }
            : null,
    ].filter(Boolean);

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
        <div className="page-stack">
            <PageHeader
                actions={(
                    <Space wrap>
                        {section === 'branches' && canManage && (
                            <Button onClick={() => openBranch()}>
                                + Branch
                            </Button>
                        )}

                        {section === 'representatives' && canManage && (
                            <Button icon={<PlusOutlined />} onClick={() => openMr()}>
                                New MR
                            </Button>
                        )}

                        {section === 'visits' && canVisits && (
                            <Button type="primary" icon={<PlusOutlined />} onClick={() => openVisit()}>
                                New Visit
                            </Button>
                        )}
                    </Space>
                )}
            />

            {(section === 'dashboard' || section === 'performance') && (
                <Card size="small" style={{ marginBottom: 16 }}>
                    <Space wrap>
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
                    </Space>
                </Card>
            )}

            {view === 'list' ? (
                <>
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

                    {section === 'representatives' && canManage && (
                        <Card title="MR Directory">
                            <div className="table-toolbar table-toolbar-wide">
                                <Input.Search
                                    value={mrTable.search}
                                    onChange={(event) => mrTable.setSearch(event.target.value)}
                                    placeholder="Search name, code, area or phone"
                                    allowClear
                                />

                                <Select
                                    allowClear
                                    placeholder="Branch"
                                    value={mrTable.filters.branch_id}
                                    onChange={(value) => (
                                        mrTable.setFilters((current) => ({
                                            ...current,
                                            branch_id: value,
                                        }))
                                    )}
                                    options={branchOptions.map((branch) => ({
                                        value: branch.id,
                                        label: branch.name,
                                    }))}
                                    style={{ minWidth: 160 }}
                                />

                                <Select
                                    allowClear
                                    placeholder="Status"
                                    value={mrTable.filters.is_active}
                                    onChange={(value) => (
                                        mrTable.setFilters((current) => ({
                                            ...current,
                                            is_active: value,
                                        }))
                                    )}
                                    options={[
                                        { value: true, label: 'Active' },
                                        { value: false, label: 'Inactive' },
                                    ]}
                                />

                                <Button type="primary" icon={<PlusOutlined />} onClick={() => openMr(null)}>
                                    Add MR
                                </Button>
                            </div>

                            <ServerTable table={mrTable} columns={mrColumns} />
                        </Card>
                    )}

                    {section === 'visits' && canVisits && (
                        <Card title="Visit Log">
                            <div className="table-toolbar table-toolbar-wide">
                                <Input.Search
                                    value={visitTable.search}
                                    onChange={(event) => visitTable.setSearch(event.target.value)}
                                    placeholder="Search MR or customer"
                                    allowClear
                                />

                                <Select
                                    allowClear
                                    placeholder="MR"
                                    value={visitTable.filters.medical_representative_id}
                                    onChange={(value) => (
                                        visitTable.setFilters((current) => ({
                                            ...current,
                                            medical_representative_id: value,
                                        }))
                                    )}
                                    options={mrOptions.map((mr) => ({
                                        value: mr.id,
                                        label: mr.name,
                                    }))}
                                    style={{ minWidth: 160 }}
                                />

                                <Select
                                    allowClear
                                    placeholder="Status"
                                    value={visitTable.filters.status}
                                    onChange={(value) => (
                                        visitTable.setFilters((current) => ({
                                            ...current,
                                            status: value,
                                        }))
                                    )}
                                    options={mrVisitStatusOptions}
                                />

                                <SmartDatePicker.RangePicker
                                    value={visitRange}
                                    onChange={updateVisitRange}
                                    placeholder={['Visit from', 'Visit to']}
                                />

                                <Button type="primary" icon={<PlusOutlined />} onClick={() => openVisit(null)}>
                                    Add Visit
                                </Button>
                            </div>

                            <ServerTable table={visitTable} columns={visitColumns} />
                        </Card>
                    )}

                    {section === 'branches' && canManage && (
                        <Card title="Branch Management">
                            <div className="table-toolbar table-toolbar-wide">
                                <Input.Search
                                    value={branchTable.search}
                                    onChange={(event) => branchTable.setSearch(event.target.value)}
                                    placeholder="Search branch name or code"
                                    allowClear
                                />

                                <Select
                                    allowClear
                                    placeholder="Type"
                                    value={branchTable.filters.type}
                                    onChange={(value) => (
                                        branchTable.setFilters((current) => ({
                                            ...current,
                                            type: value,
                                        }))
                                    )}
                                    options={[
                                        { value: 'hq', label: 'HQ' },
                                        { value: 'branch', label: 'Branch' },
                                    ]}
                                    style={{ minWidth: 120 }}
                                />

                                <Button type="primary" icon={<PlusOutlined />} onClick={() => openBranch(null)}>
                                    Add Branch
                                </Button>
                            </div>

                            <ServerTable table={branchTable} columns={branchColumns} />
                        </Card>
                    )}
                </>
            ) : view === 'mr' ? (
                <Card
                    title={editingMr ? `Edit MR: ${editingMr.name}` : 'New Medical Representative'}
                    extra={<Button onClick={() => setView('list')}>Cancel</Button>}
                >
                    <Form form={mrForm} layout="vertical" onFinish={saveMr}>
                        <Form.Item name="name" label="Full Name" rules={[{ required: true }]}>
                            <Input />
                        </Form.Item>

                        <div className="form-grid">
                            <Form.Item name="employee_code" label="Employee Code">
                                <Input />
                            </Form.Item>

                            <Form.Item name="branch_id" label="Branch">
                                <Select
                                    allowClear
                                    options={branchOptions.map((branch) => ({
                                        value: branch.id,
                                        label: branch.name,
                                    }))}
                                />
                            </Form.Item>
                        </div>

                        <div className="form-grid">
                            <Form.Item name="phone" label="Phone">
                                <Input />
                            </Form.Item>

                            <Form.Item name="email" label="Email">
                                <Input />
                            </Form.Item>
                        </div>

                        <div className="form-grid">
                            <Form.Item name="area_id" label="Area">
                                <Select
                                    allowClear
                                    showSearch
                                    optionFilterProp="label"
                                    options={areaOptions.map((area) => ({
                                        value: area.id,
                                        label: area.code ? `${area.name} (${area.code})` : area.name,
                                    }))}
                                />
                            </Form.Item>

                            <Form.Item name="division_id" label="Division">
                                <Select
                                    allowClear
                                    showSearch
                                    optionFilterProp="label"
                                    options={divisionOptions.map((division) => ({
                                        value: division.id,
                                        label: division.code ? `${division.name} (${division.code})` : division.name,
                                    }))}
                                />
                            </Form.Item>
                        </div>

                        <Form.Item name="monthly_target" label="Monthly Target (NPR)">
                            <InputNumber min={0} className="full-width" />
                        </Form.Item>

                        <Form.Item name="is_active" label="Active" valuePropName="checked">
                            <Switch />
                        </Form.Item>

                        <Button type="primary" htmlType="submit">
                            Save MR
                        </Button>
                    </Form>
                </Card>
            ) : view === 'visit' ? (
                <Card
                    title={editingVisit ? 'Edit Visit' : 'New Visit'}
                    extra={<Button onClick={() => setView('list')}>Cancel</Button>}
                >
                    <Form form={visitForm} layout="vertical" onFinish={saveVisit}>
                        <Form.Item
                            name="medical_representative_id"
                            label="MR"
                            rules={[{ required: true }]}
                        >
                            <Select
                                options={mrOptions.map((mr) => ({
                                    value: mr.id,
                                    label: mr.name,
                                }))}
                            />
                        </Form.Item>

                        <Form.Item name="customer_id" label="Customer">
                            <Select
                                allowClear
                                options={customers.map((customer) => ({
                                    value: customer.id,
                                    label: customer.name,
                                }))}
                            />
                        </Form.Item>

                        <div className="form-grid">
                            <Form.Item
                                name="visit_date"
                                label="Visit Date"
                                rules={[{ required: true }]}
                            >
                                <SmartDatePicker className="full-width" />
                            </Form.Item>

                            <Form.Item
                                name="status"
                                label="Status"
                                rules={[{ required: true }]}
                            >
                                <Select options={mrVisitStatusOptions} />
                            </Form.Item>
                        </div>

                        <div className="form-grid">
                            <Form.Item name="visit_time" label="Visit Time">
                                <Input type="time" />
                            </Form.Item>

                            <Form.Item name="order_value" label="Order Value">
                                <InputNumber min={0} className="full-width" />
                            </Form.Item>
                        </div>

                        <Form.Item name="location_name" label="Location">
                            <LocationSearch
                                countrycodes="np"
                                placeholder="Search location (city, ward, area, street)"
                                onChange={handleLocationSelect}
                            />
                        </Form.Item>

                        <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                            <Button
                                icon={<EnvironmentOutlined />}
                                onClick={captureLocation}
                            >
                                Use My Current Location
                            </Button>
                        </div>

                        <Form.Item name="latitude" hidden><Input /></Form.Item>
                        <Form.Item name="longitude" hidden><Input /></Form.Item>

                        <Form.Item name="purpose" label="Purpose">
                            <Input />
                        </Form.Item>

                        <Form.Item name="notes" label="Notes">
                            <Input.TextArea rows={2} />
                        </Form.Item>

                        <Button type="primary" htmlType="submit">
                            Save Visit
                        </Button>
                    </Form>
                </Card>
            ) : null}

            <FormDrawer
                title={editingBranch ? `Edit Branch: ${editingBranch.name}` : 'New Branch'}
                open={branchDrawerOpen}
                onClose={() => setBranchDrawerOpen(false)}
            >
                <Form form={branchForm} layout="vertical" onFinish={saveBranch}>
                    <Form.Item name="name" label="Branch Name" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>

                    <div className="form-grid">
                        <Form.Item name="code" label="Code">
                            <Input />
                        </Form.Item>

                        <Form.Item name="type" label="Type" rules={[{ required: true }]}>
                            <Select
                                options={[
                                    { value: 'hq', label: 'HQ' },
                                    { value: 'branch', label: 'Branch' },
                                ]}
                            />
                        </Form.Item>
                    </div>

                    <Form.Item name="parent_id" label="Parent HQ (leave empty if this IS the HQ)">
                        <Select
                            allowClear
                            options={branchOptions
                                .filter((branch) => branch.type === 'hq')
                                .map((branch) => ({
                                    value: branch.id,
                                    label: branch.name,
                                }))}
                        />
                    </Form.Item>

                    <Form.Item name="address" label="Address">
                        <Input.TextArea rows={2} />
                    </Form.Item>

                    <Form.Item name="phone" label="Phone">
                        <Input />
                    </Form.Item>

                    <Form.Item name="is_active" label="Active" valuePropName="checked">
                        <Switch />
                    </Form.Item>

                    <Button type="primary" htmlType="submit">
                        Save Branch
                    </Button>
                </Form>
            </FormDrawer>

            <Drawer
                title={mapVisit ? `Visit Location — ${mapVisit.medical_representative?.name ?? ''}` : ''}
                open={!!mapVisit}
                onClose={() => setMapVisit(null)}
                size="large"
            >
                {mapVisit && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        {mapVisit.location_name && (
                            <p style={{ margin: 0 }}>
                                <strong>Location:</strong> {mapVisit.location_name}
                            </p>
                        )}

                        {mapVisit.map_embed_url ? (
                            <>
                                <iframe
                                    title="Visit Map"
                                    width="100%"
                                    height="380"
                                    style={{ border: 0, borderRadius: 8 }}
                                    loading="lazy"
                                    src={mapVisit.map_embed_url}
                                />

                                <a
                                    href={mapVisit.map_view_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    style={{ fontSize: 12 }}
                                >
                                    Open in OpenStreetMap
                                </a>
                            </>
                        ) : mapVisit.location_name ? (
                            <a
                                href={`https://www.openstreetmap.org/search?query=${encodeURIComponent(mapVisit.location_name)}`}
                                target="_blank"
                                rel="noreferrer"
                                style={{ fontSize: 12 }}
                            >
                                Search this location in OpenStreetMap
                            </a>
                        ) : null}
                    </div>
                )}
            </Drawer>
        </div>
    );
}
