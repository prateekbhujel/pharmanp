import React, { useEffect, useState } from 'react';
import { App, Button, Card, Col, Form, Input, InputNumber, Progress, Row, Select, Space, Statistic, Switch, Table, Tabs } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { StatusBadge } from '../../core/components/PharmaBadge';
import { PageHeader } from '../../core/components/PageHeader';
import { FormDrawer } from '../../core/components/FormDrawer';
import { Money } from '../../core/components/Money';
import { ServerTable } from '../../core/components/ServerTable';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useAuth } from '../../core/auth/AuthProvider';
import { can } from '../../core/utils/permissions';
import { mrVisitStatusOptions } from '../../core/utils/accountCatalog';
import { dateRangeParams } from '../../core/utils/dateFilters';

export function MrPerformancePage() {
    const { notification } = App.useApp();
    const { user } = useAuth();
    const [summaryRange, setSummaryRange] = useState([]);
    const [summary, setSummary] = useState({ loading: true, data: null });
    const [mrOptions, setMrOptions] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [view, setView] = useState('list');
    const [editingRepresentative, setEditingRepresentative] = useState(null);
    const [editingVisit, setEditingVisit] = useState(null);
    const [representativeForm] = Form.useForm();
    const [visitForm] = Form.useForm();
    const representativeTable = useServerTable({ endpoint: endpoints.mrRepresentatives, defaultSort: { field: 'name', order: 'asc' } });
    const visitTable = useServerTable({
        endpoint: endpoints.mrVisits,
        defaultSort: { field: 'visit_date', order: 'desc' },
        defaultFilters: { status: undefined, medical_representative_id: undefined },
    });
    const canManageRepresentatives = user?.is_owner || can(user, 'mr.manage');
    const canManageVisits = canManageRepresentatives || can(user, 'mr.visits.manage');

    useEffect(() => {
        loadSummary();
        loadLookups();
    }, [summaryRange]);

    async function loadSummary() {
        setSummary((current) => ({ ...current, loading: true }));

        try {
            const { data } = await http.get(endpoints.mrPerformance, {
                params: dateRangeParams(summaryRange),
            });
            setSummary({ loading: false, data: data.data });
        } catch (error) {
            notification.error({
                message: 'MR summary failed',
                description: error?.response?.data?.message || error.message,
            });
            setSummary({ loading: false, data: null });
        }
    }

    async function loadLookups() {
        try {
            const [{ data: mrData }, { data: customerData }] = await Promise.all([
                http.get(endpoints.mrOptions),
                http.get(endpoints.customerOptions),
            ]);
            setMrOptions(mrData.data || []);
            setCustomers(customerData.data || []);
        } catch {
            setMrOptions([]);
            setCustomers([]);
        }
    }

    function openRepresentative(record = null) {
        setEditingRepresentative(record);
        representativeForm.resetFields();
        representativeForm.setFieldsValue(record || { is_active: true, monthly_target: 0 });
        setView('mr');
    }

    function openVisit(record = null) {
        setEditingVisit(record);
        visitForm.resetFields();
        visitForm.setFieldsValue(record ? {
            ...record,
            medical_representative_id: record.medical_representative_id ?? record.medical_representative?.id,
            customer_id: record.customer_id ?? record.customer?.id,
            visit_date: record.visit_date ? dayjs(record.visit_date) : dayjs(),
        } : {
            visit_date: dayjs(),
            status: 'planned',
            order_value: 0,
        });
        setView('visit');
    }

    async function saveRepresentative(values) {
        try {
            if (editingRepresentative) {
                await http.put(`${endpoints.mrRepresentatives}/${editingRepresentative.id}`, values);
                notification.success({ message: 'MR updated' });
            } else {
                await http.post(endpoints.mrRepresentatives, values);
                notification.success({ message: 'MR created' });
            }
            setView('list');
            representativeTable.reload();
            loadLookups();
            loadSummary();
        } catch (error) {
            representativeForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'MR save failed', description: error?.response?.data?.message || error.message });
        }
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
            loadSummary();
        } catch (error) {
            visitForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Visit save failed', description: error?.response?.data?.message || error.message });
        }
    }

    function deleteRepresentative(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'This disables the representative and removes them from future assignment.',
            onOk: async () => {
                await http.delete(`${endpoints.mrRepresentatives}/${record.id}`);
                notification.success({ message: 'MR deleted' });
                representativeTable.reload();
                loadLookups();
                loadSummary();
            },
        });
    }

    function deleteVisit(record) {
        confirmDelete({
            title: 'Delete this visit?',
            content: 'The visit entry will be removed from MR tracking.',
            onOk: async () => {
                await http.delete(`${endpoints.mrVisits}/${record.id}`);
                notification.success({ message: 'Visit deleted' });
                visitTable.reload();
                loadSummary();
            },
        });
    }

    const totals = summary.data?.totals || {};

    const representativeColumns = [
        { title: 'MR', dataIndex: 'name', field: 'name', sorter: true },
        { title: 'Code', dataIndex: 'employee_code', width: 120 },
        { title: 'Territory', dataIndex: 'territory', width: 160 },
        { title: 'Phone', dataIndex: 'phone', width: 140 },
        { title: 'Target', dataIndex: 'monthly_target', align: 'right', width: 130, render: (value) => <Money value={value} /> },
        { title: 'Status', dataIndex: 'is_active', width: 120, render: (value) => <StatusBadge value={value} /> },
        canManageRepresentatives ? {
            title: 'Action',
            width: 112,
            render: (_, record) => (
                <Space>
                    <Button icon={<EditOutlined />} onClick={() => openRepresentative(record)} />
                    <Button danger icon={<DeleteOutlined />} onClick={() => deleteRepresentative(record)} />
                </Space>
            ),
        } : null,
    ].filter(Boolean);

    const visitColumns = [
        { title: 'Date', dataIndex: 'visit_date', field: 'visit_date', sorter: true, width: 120, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'MR', dataIndex: ['medical_representative', 'name'], render: (value) => value || '-' },
        { title: 'Customer', dataIndex: ['customer', 'name'], render: (value) => value || '-' },
        { title: 'Status', dataIndex: 'status', width: 120 },
        { title: 'Order Value', dataIndex: 'order_value', align: 'right', width: 140, render: (value) => <Money value={value} /> },
        canManageVisits ? {
            title: 'Action',
            width: 112,
            render: (_, record) => (
                <Space>
                    <Button icon={<EditOutlined />} onClick={() => openVisit(record)} />
                    <Button danger icon={<DeleteOutlined />} onClick={() => deleteVisit(record)} />
                </Space>
            ),
        } : null,
    ].filter(Boolean);

    return (
        <div className="page-stack">
            <PageHeader
                actions={(
                    <Space wrap>
                        <SmartDatePicker.RangePicker value={summaryRange} onChange={setSummaryRange} />
                        {canManageRepresentatives && <Button icon={<PlusOutlined />} onClick={() => openRepresentative()}>New MR</Button>}
                        {canManageVisits && <Button type="primary" icon={<PlusOutlined />} onClick={() => openVisit()}>New Visit</Button>}
                    </Space>
                )}
            />

            {view === 'list' ? (
            <>
            <Row gutter={[16, 16]}>
                <Col xs={24} md={6}><Card loading={summary.loading}><Statistic title="Active MRs" value={totals.active_mrs || 0} /></Card></Col>
                <Col xs={24} md={6}><Card loading={summary.loading}><Statistic title="Visits" value={totals.visits || 0} /></Card></Col>
                <Col xs={24} md={6}><Card loading={summary.loading}><Statistic title="Visit Orders" value={totals.visit_order_value || 0} prefix="Rs." precision={2} /></Card></Col>
                <Col xs={24} md={6}><Card loading={summary.loading}><Statistic title="Invoiced Value" value={totals.invoiced_value || 0} prefix="Rs." precision={2} /></Card></Col>
            </Row>

            <Tabs items={[
                {
                    key: 'performance',
                    label: 'Performance',
                    children: (
                        <Card>
                            <Table
                                loading={summary.loading}
                                rowKey="id"
                                dataSource={summary.data?.rows || []}
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
                    ),
                },
                canManageRepresentatives ? {
                    key: 'masters',
                    label: 'MR Master',
                    children: (
                        <Card>
                            <div className="table-toolbar">
                                <Input.Search value={representativeTable.search} onChange={(event) => representativeTable.setSearch(event.target.value)} placeholder="Search MR, code or territory" allowClear />
                                <Select
                                    allowClear
                                    placeholder="Status"
                                    value={representativeTable.filters.is_active}
                                    onChange={(value) => representativeTable.setFilters((current) => ({ ...current, is_active: value }))}
                                    options={[
                                        { value: true, label: 'Active' },
                                        { value: false, label: 'Inactive' },
                                    ]}
                                />
                                <span />
                                <Button type="primary" icon={<PlusOutlined />} onClick={() => openRepresentative()}>New MR</Button>
                            </div>
                            <ServerTable table={representativeTable} columns={representativeColumns} />
                        </Card>
                    ),
                } : null,
                canManageVisits ? {
                    key: 'visits',
                    label: 'Visits',
                    children: (
                        <Card>
                            <div className="table-toolbar table-toolbar-wide">
                                <Input.Search value={visitTable.search} onChange={(event) => visitTable.setSearch(event.target.value)} placeholder="Search visit, MR or customer" allowClear />
                                <Select
                                    allowClear
                                    placeholder="MR"
                                    value={visitTable.filters.medical_representative_id}
                                    onChange={(value) => visitTable.setFilters((current) => ({ ...current, medical_representative_id: value }))}
                                    options={mrOptions.map((item) => ({ value: item.id, label: item.name }))}
                                />
                                <Select
                                    allowClear
                                    placeholder="Status"
                                    value={visitTable.filters.status}
                                    onChange={(value) => visitTable.setFilters((current) => ({ ...current, status: value }))}
                                    options={mrVisitStatusOptions}
                                />
                                <Button type="primary" icon={<PlusOutlined />} onClick={() => openVisit()}>New Visit</Button>
                            </div>
                            <ServerTable table={visitTable} columns={visitColumns} />
                        </Card>
                    ),
                } : null,
            ].filter(Boolean)} />
            </>
            ) : view === 'mr' ? (
            <Card 
                title={editingRepresentative ? `Edit MR: ${editingRepresentative.name}` : 'New Medical Representative'}
                extra={<Button onClick={() => setView('list')}>Cancel</Button>}
            >
                <Form form={representativeForm} layout="vertical" onFinish={saveRepresentative}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name="employee_code" label="Employee Code"><Input /></Form.Item>
                        <Form.Item name="territory" label="Territory"><Input /></Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                        <Form.Item name="email" label="Email"><Input /></Form.Item>
                    </div>
                    <Form.Item name="monthly_target" label="Monthly Target"><InputNumber min={0} className="full-width" /></Form.Item>
                    <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                    <Button type="primary" htmlType="submit">Save MR</Button>
                </Form>
            </Card>
            ) : view === 'visit' ? (
            <Card 
                title={editingVisit ? 'Edit Visit' : 'New Visit'}
                extra={<Button onClick={() => setView('list')}>Cancel</Button>}
            >
                <Form form={visitForm} layout="vertical" onFinish={saveVisit}>
                    <Form.Item name="medical_representative_id" label="MR" rules={[{ required: true }]}>
                        <Select options={mrOptions.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Form.Item name="customer_id" label="Customer">
                        <Select allowClear options={customers.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <div className="form-grid">
                        <Form.Item name="visit_date" label="Visit Date" rules={[{ required: true }]}><SmartDatePicker className="full-width" /></Form.Item>
                        <Form.Item name="status" label="Status" rules={[{ required: true }]}><Select options={mrVisitStatusOptions} /></Form.Item>
                    </div>
                    <Form.Item name="order_value" label="Order Value"><InputNumber min={0} className="full-width" /></Form.Item>
                    <Form.Item name="notes" label="Notes"><Input.TextArea rows={3} /></Form.Item>
                    <Button type="primary" htmlType="submit">Save Visit</Button>
                </Form>
            </Card>
            ) : null}
        </div>
    );
}
