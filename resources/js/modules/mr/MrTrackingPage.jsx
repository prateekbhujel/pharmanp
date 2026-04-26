import React, { useCallback, useEffect, useState } from 'react';
import { App, Badge, Button, Card, Col, DatePicker, Drawer, Form, Input, InputNumber, Row, Select, Space, Statistic, Switch, Table, Tag, Tooltip } from 'antd';
import { DeleteOutlined, EditOutlined, EnvironmentOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { FormDrawer } from '../../core/components/FormDrawer';
import { ServerTable } from '../../core/components/ServerTable';
import { Money } from '../../core/components/Money';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useAuth } from '../../core/auth/AuthProvider';
import { can } from '../../core/utils/permissions';
import { mrVisitStatusOptions } from '../../core/utils/accountCatalog';

// ─── tiny status badge ───────────────────────────────────────────────────────
function StatusBadge({ active }) {
    return <Badge status={active ? 'success' : 'default'} text={active ? 'Active' : 'Inactive'} />;
}

export function MrTrackingPage() {
    const { notification } = App.useApp();
    const { user } = useAuth();

    // ── lookups ───────────────────────────────────────────────────────────────
    const [branchOptions, setBranchOptions]   = useState([]);
    const [mrOptions, setMrOptions]           = useState([]);
    const [customers, setCustomers]           = useState([]);

    // ── filter bar state ─────────────────────────────────────────────────────
    const [dateRange, setDateRange]   = useState([dayjs().startOf('month'), dayjs()]);
    const [branchId, setBranchId]     = useState(undefined);
    const [mrId, setMrId]             = useState(undefined);

    // ── performance summary ───────────────────────────────────────────────────
    const [perfData, setPerfData]     = useState(null);
    const [perfLoading, setPerfLoading] = useState(false);

    // ── branch-sales breakdown ────────────────────────────────────────────────
    const [salesData, setSalesData]   = useState(null);
    const [salesLoading, setSalesLoading] = useState(false);

    // ── visits table ──────────────────────────────────────────────────────────
    const visitTable = useServerTable({
        endpoint: endpoints.mrVisits,
        defaultSort: { field: 'visit_date', order: 'desc' },
    });

    // ── MR master table ───────────────────────────────────────────────────────
    const mrTable = useServerTable({
        endpoint: endpoints.mrRepresentatives,
        defaultSort: { field: 'name', order: 'asc' },
    });

    // ── form state ────────────────────────────────────────────────────────────
    const [mrDrawerOpen, setMrDrawerOpen]     = useState(false);
    const [visitDrawerOpen, setVisitDrawerOpen] = useState(false);
    const [branchDrawerOpen, setBranchDrawerOpen] = useState(false);
    const [editingMr, setEditingMr]           = useState(null);
    const [editingVisit, setEditingVisit]     = useState(null);
    const [editingBranch, setEditingBranch]   = useState(null);
    const [mapVisit, setMapVisit]             = useState(null);

    const [mrForm]     = Form.useForm();
    const [visitForm]  = Form.useForm();
    const [branchForm] = Form.useForm();

    const canManage = user?.is_owner || can(user, 'mr.manage');
    const canVisits = canManage || can(user, 'mr.visits.manage');

    const fromDate = dateRange?.[0]?.format('YYYY-MM-DD');
    const toDate   = dateRange?.[1]?.format('YYYY-MM-DD');

    // ── load lookups once ─────────────────────────────────────────────────────
    useEffect(() => {
        loadLookups();
    }, []);

    // ── reload on filter change ───────────────────────────────────────────────
    useEffect(() => {
        loadPerformance();
        loadBranchSales();
    }, [dateRange, branchId, mrId]);

    async function loadLookups() {
        try {
            const [{ data: br }, { data: mr }, { data: cu }] = await Promise.all([
                http.get(endpoints.mrBranchOptions),
                http.get(endpoints.mrOptions),
                http.get(endpoints.customerOptions),
            ]);
            setBranchOptions(br.data || []);
            setMrOptions(mr.data || []);
            setCustomers(cu.data || []);
        } catch { /* silent */ }
    }

    async function loadPerformance() {
        setPerfLoading(true);
        try {
            const { data } = await http.get(endpoints.mrPerformance, {
                params: { from: fromDate, to: toDate, medical_representative_id: mrId },
            });
            setPerfData(data.data);
        } finally { setPerfLoading(false); }
    }

    async function loadBranchSales() {
        setSalesLoading(true);
        try {
            const { data } = await http.get(endpoints.mrBranchSales, {
                params: { from: fromDate, to: toDate, branch_id: branchId, mr_id: mrId },
            });
            setSalesData(data.data);
        } finally { setSalesLoading(false); }
    }

    // ── MR CRUD ───────────────────────────────────────────────────────────────
    function openMr(record = null) {
        setEditingMr(record);
        mrForm.resetFields();
        mrForm.setFieldsValue(record || { is_active: true, monthly_target: 0 });
        setMrDrawerOpen(true);
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
            setMrDrawerOpen(false);
            mrTable.reload();
            loadLookups();
            loadPerformance();
        } catch (e) {
            mrForm.setFields(Object.entries(validationErrors(e)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Save failed', description: e?.response?.data?.message });
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

    // ── Visit CRUD ────────────────────────────────────────────────────────────
    function openVisit(record = null) {
        setEditingVisit(record);
        visitForm.resetFields();
        visitForm.setFieldsValue(record ? {
            ...record,
            medical_representative_id: record.medical_representative_id ?? record.medical_representative?.id,
            customer_id: record.customer_id ?? record.customer?.id,
            visit_date: record.visit_date ? dayjs(record.visit_date) : dayjs(),
        } : { visit_date: dayjs(), status: 'planned', order_value: 0 });
        setVisitDrawerOpen(true);
    }

    async function saveVisit(values) {
        try {
            const payload = { ...values, visit_date: values.visit_date.format('YYYY-MM-DD') };
            if (editingVisit) {
                await http.put(`${endpoints.mrVisits}/${editingVisit.id}`, payload);
                notification.success({ message: 'Visit updated' });
            } else {
                await http.post(endpoints.mrVisits, payload);
                notification.success({ message: 'Visit created' });
            }
            setVisitDrawerOpen(false);
            visitTable.reload();
            loadPerformance();
        } catch (e) {
            visitForm.setFields(Object.entries(validationErrors(e)).map(([name, errors]) => ({ name, errors })));
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

    // ── Branch CRUD ───────────────────────────────────────────────────────────
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
            loadLookups();
        } catch (e) {
            branchForm.setFields(Object.entries(validationErrors(e)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Save failed' });
        }
    }

    // ── get GPS from browser ──────────────────────────────────────────────────
    function captureLocation() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((pos) => {
            visitForm.setFieldsValue({
                latitude: pos.coords.latitude.toFixed(7),
                longitude: pos.coords.longitude.toFixed(7),
            });
            notification.success({ message: 'Location captured' });
        }, () => notification.warning({ message: 'Location access denied' }));
    }

    // ── column definitions ────────────────────────────────────────────────────
    const mrColumns = [
        { title: 'Name', dataIndex: 'name', sorter: true, field: 'name' },
        { title: 'Code', dataIndex: 'employee_code', width: 110 },
        { title: 'Branch', dataIndex: ['branch', 'name'], render: (v) => v || <span style={{ color: '#aaa' }}>—</span> },
        { title: 'Territory', dataIndex: 'territory', width: 140 },
        { title: 'Target', dataIndex: 'monthly_target', align: 'right', width: 130, render: (v) => <Money value={v} /> },
        { title: 'Status', dataIndex: 'is_active', width: 100, render: (v) => <StatusBadge active={v} /> },
        canManage ? {
            title: '', width: 96,
            render: (_, r) => (
                <Space>
                    <Button size="small" icon={<EditOutlined />} onClick={() => openMr(r)} />
                    <Button size="small" danger icon={<DeleteOutlined />} onClick={() => deleteMr(r)} />
                </Space>
            ),
        } : null,
    ].filter(Boolean);

    const visitColumns = [
        { title: 'Date', dataIndex: 'visit_date', width: 110, sorter: true, field: 'visit_date' },
        { title: 'MR', dataIndex: ['medical_representative', 'name'], render: (v) => v || '—' },
        { title: 'Customer', dataIndex: ['customer', 'name'], render: (v) => v || '—' },
        { title: 'Status', dataIndex: 'status', width: 110, render: (v) => <Tag>{v}</Tag> },
        { title: 'Order Value', dataIndex: 'order_value', align: 'right', width: 130, render: (v) => <Money value={v} /> },
        {
            title: 'Location', width: 90, align: 'center',
            render: (_, r) => r.latitude ? (
                <Tooltip title={r.location_name || `${r.latitude}, ${r.longitude}`}>
                    <Button
                        type="link"
                        icon={<EnvironmentOutlined style={{ color: '#52c41a' }} />}
                        onClick={() => setMapVisit(r)}
                    />
                </Tooltip>
            ) : <span style={{ color: '#ccc' }}>—</span>,
        },
        canVisits ? {
            title: '', width: 96,
            render: (_, r) => (
                <Space>
                    <Button size="small" icon={<EditOutlined />} onClick={() => openVisit(r)} />
                    <Button size="small" danger icon={<DeleteOutlined />} onClick={() => deleteVisit(r)} />
                </Space>
            ),
        } : null,
    ].filter(Boolean);

    const branchSalesColumns = [
        { title: 'Branch', dataIndex: 'branch_name' },
        { title: 'MR', dataIndex: 'mr_name' },
        { title: 'Product', dataIndex: 'product_name' },
        { title: 'Qty', dataIndex: 'total_qty', align: 'right', width: 90, render: (v) => (+v).toFixed(2) },
        { title: 'Value', dataIndex: 'total_value', align: 'right', width: 130, render: (v) => <Money value={v} /> },
    ];

    const totals = perfData?.totals || {};

    return (
        <div className="page-stack">
            <PageHeader
                title="MR Tracking"
                description="Branch hierarchy, visit tracking, product sales per MR and per branch"
                actions={
                    <Space wrap>
                        {canManage && <Button onClick={() => openBranch()}>+ Branch</Button>}
                        {canManage && <Button icon={<PlusOutlined />} onClick={() => openMr()}>New MR</Button>}
                        {canVisits && <Button type="primary" icon={<PlusOutlined />} onClick={() => openVisit()}>New Visit</Button>}
                    </Space>
                }
            />

            {/* ── Global filter bar ─────────────────────────────────────────── */}
            <Card size="small">
                <Space wrap>
                    <DatePicker.RangePicker value={dateRange} onChange={setDateRange} />
                    <Select
                        allowClear placeholder="All Branches" value={branchId}
                        onChange={setBranchId} style={{ minWidth: 180 }}
                        options={branchOptions.map((b) => ({ value: b.id, label: b.name }))}
                    />
                    <Select
                        allowClear placeholder="All MRs" value={mrId}
                        onChange={setMrId} style={{ minWidth: 160 }}
                        options={mrOptions.map((m) => ({ value: m.id, label: m.name }))}
                    />
                </Space>
            </Card>

            {/* ── KPI cards ─────────────────────────────────────────────────── */}
            <Row gutter={[16, 16]}>
                <Col xs={12} md={6}>
                    <Card loading={perfLoading}>
                        <Statistic title="Active MRs" value={totals.active_mrs ?? '—'} />
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card loading={perfLoading}>
                        <Statistic title="Visits" value={totals.visits ?? '—'} />
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card loading={perfLoading}>
                        <Statistic title="Total Sales" value={totals.invoiced_value ?? 0} prefix="NPR" precision={2} />
                    </Card>
                </Col>
                <Col xs={12} md={6}>
                    <Card loading={salesLoading}>
                        <Statistic title="Grand Total (Products)" value={salesData?.grand_total ?? 0} prefix="NPR" precision={2} />
                    </Card>
                </Col>
            </Row>

            {/* ── Branch sales breakdown ────────────────────────────────────── */}
            <Card
                title="Product Sales by Branch & MR"
                loading={salesLoading}
                extra={<Tag color="blue">{salesData?.period}</Tag>}
            >
                <Table
                    rowKey={(_, i) => i}
                    dataSource={salesData?.rows || []}
                    columns={branchSalesColumns}
                    pagination={{ pageSize: 15, showSizeChanger: true }}
                    scroll={{ x: 700 }}
                    size="small"
                />
            </Card>

            {/* ── MR Performance table ──────────────────────────────────────── */}
            <Card title="MR Performance" loading={perfLoading}>
                <Table
                    rowKey="id"
                    dataSource={perfData?.rows || []}
                    pagination={{ pageSize: 10 }}
                    columns={[
                        { title: 'MR', dataIndex: 'name' },
                        { title: 'Territory', dataIndex: 'territory' },
                        { title: 'Visits', dataIndex: 'visits', align: 'right', width: 80 },
                        { title: 'Orders', dataIndex: 'visit_order_value', align: 'right', width: 130, render: (v) => <Money value={v} /> },
                        { title: 'Invoiced', dataIndex: 'invoiced_value', align: 'right', width: 130, render: (v) => <Money value={v} /> },
                        { title: 'Target', dataIndex: 'monthly_target', align: 'right', width: 130, render: (v) => <Money value={v} /> },
                        {
                            title: 'Achievement', dataIndex: 'achievement_percent', width: 110, align: 'right',
                            render: (v) => {
                                const pct = Math.min(v || 0, 100);
                                const color = pct >= 100 ? '#52c41a' : pct >= 70 ? '#faad14' : '#ff4d4f';
                                return <span style={{ color, fontWeight: 600 }}>{pct.toFixed(1)}%</span>;
                            },
                        },
                    ]}
                    scroll={{ x: 800 }}
                    size="small"
                />
            </Card>

            {/* ── MR Master list ────────────────────────────────────────────── */}
            {canManage && (
                <Card title="MR Directory">
                    <div className="table-toolbar table-toolbar-wide">
                        <Input.Search
                            value={mrTable.search}
                            onChange={(e) => mrTable.setSearch(e.target.value)}
                            placeholder="Search name, code or territory"
                            allowClear
                        />
                        <Select
                            allowClear placeholder="Branch"
                            value={mrTable.filters.branch_id}
                            onChange={(v) => mrTable.setFilters((c) => ({ ...c, branch_id: v }))}
                            options={branchOptions.map((b) => ({ value: b.id, label: b.name }))}
                            style={{ minWidth: 160 }}
                        />
                        <Select
                            allowClear placeholder="Status"
                            value={mrTable.filters.is_active}
                            onChange={(v) => mrTable.setFilters((c) => ({ ...c, is_active: v }))}
                            options={[{ value: true, label: 'Active' }, { value: false, label: 'Inactive' }]}
                        />
                    </div>
                    <ServerTable table={mrTable} columns={mrColumns} />
                </Card>
            )}

            {/* ── Visits list ───────────────────────────────────────────────── */}
            {canVisits && (
                <Card title="Visit Log">
                    <div className="table-toolbar table-toolbar-wide">
                        <Input.Search
                            value={visitTable.search}
                            onChange={(e) => visitTable.setSearch(e.target.value)}
                            placeholder="Search MR or customer"
                            allowClear
                        />
                        <Select
                            allowClear placeholder="MR"
                            value={visitTable.filters.medical_representative_id}
                            onChange={(v) => visitTable.setFilters((c) => ({ ...c, medical_representative_id: v }))}
                            options={mrOptions.map((m) => ({ value: m.id, label: m.name }))}
                            style={{ minWidth: 160 }}
                        />
                        <Select
                            allowClear placeholder="Status"
                            value={visitTable.filters.status}
                            onChange={(v) => visitTable.setFilters((c) => ({ ...c, status: v }))}
                            options={mrVisitStatusOptions}
                        />
                    </div>
                    <ServerTable table={visitTable} columns={visitColumns} />
                </Card>
            )}

            {/* ══ Drawers & Modals ════════════════════════════════════════════ */}

            {/* MR form */}
            <FormDrawer
                title={editingMr ? `Edit MR: ${editingMr.name}` : 'New Medical Representative'}
                open={mrDrawerOpen}
                onClose={() => setMrDrawerOpen(false)}
                footer={<Button type="primary" block onClick={() => mrForm.submit()}>Save MR</Button>}
            >
                <Form form={mrForm} layout="vertical" onFinish={saveMr}>
                    <Form.Item name="name" label="Full Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name="employee_code" label="Employee Code"><Input /></Form.Item>
                        <Form.Item name="branch_id" label="Branch">
                            <Select allowClear options={branchOptions.map((b) => ({ value: b.id, label: b.name }))} />
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                        <Form.Item name="email" label="Email"><Input /></Form.Item>
                    </div>
                    <Form.Item name="territory" label="Territory"><Input /></Form.Item>
                    <Form.Item name="monthly_target" label="Monthly Target (NPR)">
                        <InputNumber min={0} className="full-width" />
                    </Form.Item>
                    <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </FormDrawer>

            {/* Visit form */}
            <FormDrawer
                title={editingVisit ? 'Edit Visit' : 'New Visit'}
                open={visitDrawerOpen}
                onClose={() => setVisitDrawerOpen(false)}
                footer={<Button type="primary" block onClick={() => visitForm.submit()}>Save Visit</Button>}
            >
                <Form form={visitForm} layout="vertical" onFinish={saveVisit}>
                    <Form.Item name="medical_representative_id" label="MR" rules={[{ required: true }]}>
                        <Select options={mrOptions.map((m) => ({ value: m.id, label: m.name }))} />
                    </Form.Item>
                    <Form.Item name="customer_id" label="Customer">
                        <Select allowClear options={customers.map((c) => ({ value: c.id, label: c.name }))} />
                    </Form.Item>
                    <div className="form-grid">
                        <Form.Item name="visit_date" label="Visit Date" rules={[{ required: true }]}>
                            <DatePicker className="full-width" />
                        </Form.Item>
                        <Form.Item name="status" label="Status" rules={[{ required: true }]}>
                            <Select options={mrVisitStatusOptions} />
                        </Form.Item>
                    </div>
                    <Form.Item name="order_value" label="Order Value">
                        <InputNumber min={0} className="full-width" />
                    </Form.Item>
                    <Form.Item name="notes" label="Notes"><Input.TextArea rows={2} /></Form.Item>

                    {/* GPS check-in */}
                    <Card size="small" title="Check-in Location (optional)" style={{ marginBottom: 16 }}>
                        <Button icon={<EnvironmentOutlined />} onClick={captureLocation} style={{ marginBottom: 12 }}>
                            Capture Current Location
                        </Button>
                        <div className="form-grid">
                            <Form.Item name="latitude" label="Latitude"><Input /></Form.Item>
                            <Form.Item name="longitude" label="Longitude"><Input /></Form.Item>
                        </div>
                        <Form.Item name="location_name" label="Location Name (optional)"><Input /></Form.Item>
                    </Card>
                </Form>
            </FormDrawer>

            {/* Branch form */}
            <FormDrawer
                title={editingBranch ? `Edit Branch: ${editingBranch.name}` : 'New Branch'}
                open={branchDrawerOpen}
                onClose={() => setBranchDrawerOpen(false)}
                footer={<Button type="primary" block onClick={() => branchForm.submit()}>Save Branch</Button>}
            >
                <Form form={branchForm} layout="vertical" onFinish={saveBranch}>
                    <Form.Item name="name" label="Branch Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name="code" label="Code"><Input /></Form.Item>
                        <Form.Item name="type" label="Type" rules={[{ required: true }]}>
                            <Select options={[{ value: 'hq', label: 'HQ' }, { value: 'branch', label: 'Branch' }]} />
                        </Form.Item>
                    </div>
                    <Form.Item name="parent_id" label="Parent HQ (leave empty if this IS the HQ)">
                        <Select
                            allowClear
                            options={branchOptions.filter((b) => b.type === 'hq').map((b) => ({ value: b.id, label: b.name }))}
                        />
                    </Form.Item>
                    <Form.Item name="address" label="Address"><Input.TextArea rows={2} /></Form.Item>
                    <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                    <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </FormDrawer>

            {/* Map modal — opens on location pin click */}
            <Drawer
                title={mapVisit ? `Visit Location — ${mapVisit.medical_representative?.name ?? ''}` : ''}
                open={!!mapVisit}
                onClose={() => setMapVisit(null)}
                width={560}
            >
                {mapVisit && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        <p style={{ margin: 0 }}>
                            <strong>Coordinates:</strong> {mapVisit.latitude}, {mapVisit.longitude}
                        </p>
                        {mapVisit.location_name && (
                            <p style={{ margin: 0 }}><strong>Location:</strong> {mapVisit.location_name}</p>
                        )}
                        <iframe
                            title="Visit Map"
                            width="100%"
                            height="380"
                            style={{ border: 0, borderRadius: 8 }}
                            loading="lazy"
                            src={`https://www.openstreetmap.org/export/embed.html?bbox=${+mapVisit.longitude - 0.01},${+mapVisit.latitude - 0.01},${+mapVisit.longitude + 0.01},${+mapVisit.latitude + 0.01}&layer=mapnik&marker=${mapVisit.latitude},${mapVisit.longitude}`}
                        />
                        <a
                            href={`https://www.openstreetmap.org/?mlat=${mapVisit.latitude}&mlon=${mapVisit.longitude}#map=15/${mapVisit.latitude}/${mapVisit.longitude}`}
                            target="_blank"
                            rel="noreferrer"
                            style={{ fontSize: 12 }}
                        >
                            Open in OpenStreetMap ↗
                        </a>
                    </div>
                )}
            </Drawer>
        </div>
    );
}
