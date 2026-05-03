import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Select, Space, Switch } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined, UndoOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { ServerTable } from '../../core/components/ServerTable';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

const resources = {
    areas: {
        title: 'Areas',
        endpoint: endpoints.setupAreas,
        restore: endpoints.setupAreaRestore,
        createLabel: 'New Area',
        defaultSort: { field: 'updated_at', order: 'desc' },
    },
    divisions: {
        title: 'Divisions',
        endpoint: endpoints.setupDivisions,
        restore: endpoints.setupDivisionRestore,
        createLabel: 'New Division',
        defaultSort: { field: 'updated_at', order: 'desc' },
    },
    employees: {
        title: 'Employees',
        endpoint: endpoints.setupEmployees,
        restore: endpoints.setupEmployeeRestore,
        createLabel: 'New Employee',
        defaultSort: { field: 'updated_at', order: 'desc' },
    },
    targets: {
        title: 'Targets',
        endpoint: endpoints.setupTargets,
        restore: endpoints.setupTargetRestore,
        createLabel: 'New Target',
        defaultSort: { field: 'updated_at', order: 'desc' },
    },
};

const targetTypeOptions = [
    { value: 'primary', label: 'Primary' },
    { value: 'secondary', label: 'Secondary' },
];

const targetPeriodOptions = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'annual', label: 'Annual' },
];

const targetLevelOptions = [
    { value: 'company', label: 'Company' },
    { value: 'division', label: 'Division' },
    { value: 'area', label: 'Area' },
    { value: 'employee', label: 'MR / Employee' },
    { value: 'product', label: 'Product' },
];

const targetStatusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'closed', label: 'Closed' },
];

function resourceFromPath() {
    const segment = window.location.pathname.split('/').filter(Boolean).pop();
    return resources[segment] ? segment : 'employees';
}

function optionLabel(item, codeKey = 'code') {
    if (!item) return '';
    const code = item[codeKey] || item.employee_code;
    return code ? `${item.name} (${code})` : item.name;
}

function datePayload(value) {
    if (!value) return null;
    return value?.format ? value.format('YYYY-MM-DD') : value;
}

export function SetupStructurePage() {
    const { notification } = App.useApp();
    const resource = resourceFromPath();
    const config = resources[resource];
    const table = useServerTable({ endpoint: config.endpoint, defaultSort: config.defaultSort });
    const [form] = Form.useForm();
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [lookups, setLookups] = useState({ branches: [], areas: [], divisions: [], employees: [], users: [] });
    const [productOptions, setProductOptions] = useState([]);
    const deletedMode = Boolean(table.filters.deleted);

    useEffect(() => {
        loadLookups();
    }, []);

    async function loadLookups() {
        const [branches, areas, divisions, employees, users] = await Promise.allSettled([
            http.get(endpoints.mrBranchOptions),
            http.get(endpoints.setupAreaOptions),
            http.get(endpoints.setupDivisionOptions),
            http.get(endpoints.setupEmployeeOptions),
            http.get(endpoints.users, { params: { per_page: 100 } }),
        ]);

        setLookups({
            branches: branches.value?.data?.data || [],
            areas: areas.value?.data?.data || [],
            divisions: divisions.value?.data?.data || [],
            employees: employees.value?.data?.data || [],
            users: users.value?.data?.data || [],
        });
    }

    async function searchProducts(q = '') {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProductOptions((data.data || []).map((item) => ({ value: item.id, label: optionLabel(item, 'sku') })));
    }

    function openModal(record = null) {
        setEditing(record);
        form.resetFields();
        const values = record ? {
            ...record,
            joined_on: record.joined_on ? dayjs(record.joined_on) : null,
            start_date: record.start_date ? dayjs(record.start_date) : null,
            end_date: record.end_date ? dayjs(record.end_date) : null,
            is_active: Boolean(record.is_active),
        } : defaultsFor(resource);

        if (resource === 'targets' && record?.product) {
            setProductOptions([{ value: record.product.id, label: optionLabel(record.product, 'product_code') }]);
        }

        form.setFieldsValue(values);
        setModalOpen(true);
    }

    function defaultsFor(type) {
        if (type === 'targets') {
            return {
                target_type: 'primary',
                target_period: 'monthly',
                target_level: 'employee',
                status: 'active',
            };
        }

        return { is_active: true };
    }

    function payloadFor(values) {
        const payload = { ...values };

        ['joined_on', 'start_date', 'end_date'].forEach((field) => {
            if (field in payload) {
                payload[field] = datePayload(payload[field]);
            }
        });

        if (resource === 'targets') {
            const level = payload.target_level;
            if (level !== 'area') payload.area_id = null;
            if (level !== 'division') payload.division_id = null;
            if (level !== 'employee') payload.employee_id = null;
            if (level !== 'product') payload.product_id = null;
        }

        return payload;
    }

    async function save(values) {
        setSaving(true);
        try {
            const payload = payloadFor(values);
            if (editing) {
                await http.put(`${config.endpoint}/${editing.id}`, payload);
                notification.success({ message: `${config.title.slice(0, -1)} updated` });
            } else {
                await http.post(config.endpoint, payload);
                notification.success({ message: `${config.title.slice(0, -1)} created` });
            }

            setModalOpen(false);
            await Promise.all([table.reload(), loadLookups()]);
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: `${config.title.slice(0, -1)} save failed`, description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    function remove(record) {
        confirmDelete({
            title: `Delete ${record.name || record.employee_code || 'record'}?`,
            content: 'This will soft delete the record and keep existing transaction history intact.',
            onOk: async () => {
                await http.delete(`${config.endpoint}/${record.id}`);
                notification.success({ message: `${config.title.slice(0, -1)} deleted` });
                await Promise.all([table.reload(), loadLookups()]);
            },
        });
    }

    function restore(record) {
        confirmDelete({
            title: `Restore ${record.name || record.employee_code || 'record'}?`,
            content: 'This will make the record selectable again.',
            okText: 'Restore',
            danger: false,
            onOk: async () => {
                await http.post(config.restore(record.id));
                notification.success({ message: `${config.title.slice(0, -1)} restored` });
                await Promise.all([table.reload(), loadLookups()]);
            },
        });
    }

    const columns = useMemo(() => {
        const actionColumn = {
            title: 'Action',
            key: 'actions',
            fixed: 'right',
            width: 128,
            render: (_, record) => record.deleted_at ? (
                <Button icon={<UndoOutlined />} onClick={() => restore(record)}>Restore</Button>
            ) : (
                <Space>
                    <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openModal(record)} />
                    <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                </Space>
            ),
        };

        if (resource === 'areas') {
            return [
                { title: 'Area', dataIndex: 'name', sorter: true, field: 'name', width: 220 },
                { title: 'Code', dataIndex: 'code', sorter: true, field: 'code', width: 110, render: (value) => value || '-' },
                { title: 'Branch', dataIndex: ['branch', 'name'], width: 180, render: (value) => value || '-' },
                { title: 'District', dataIndex: 'district', sorter: true, field: 'district', width: 150, render: (value) => value || '-' },
                { title: 'Province', dataIndex: 'province', sorter: true, field: 'province', width: 150, render: (value) => value || '-' },
                { title: 'Status', dataIndex: 'is_active', width: 120, render: (value, row) => row.deleted_at ? <PharmaBadge tone="danger">Deleted</PharmaBadge> : <PharmaBadge tone={value ? 'success' : 'archive'}>{value ? 'Active' : 'Inactive'}</PharmaBadge> },
                actionColumn,
            ];
        }

        if (resource === 'divisions') {
            return [
                { title: 'Division', dataIndex: 'name', sorter: true, field: 'name', width: 220 },
                { title: 'Code', dataIndex: 'code', sorter: true, field: 'code', width: 120, render: (value) => value || '-' },
                { title: 'Products', dataIndex: 'products_count', width: 110, align: 'right' },
                { title: 'Employees', dataIndex: 'employees_count', width: 120, align: 'right' },
                { title: 'Status', dataIndex: 'is_active', width: 120, render: (value, row) => row.deleted_at ? <PharmaBadge tone="danger">Deleted</PharmaBadge> : <PharmaBadge tone={value ? 'success' : 'archive'}>{value ? 'Active' : 'Inactive'}</PharmaBadge> },
                actionColumn,
            ];
        }

        if (resource === 'targets') {
            return [
                { title: 'Level', dataIndex: 'target_level', sorter: true, field: 'target_level', width: 130, render: (value) => value?.replaceAll('_', ' ') },
                { title: 'Target For', key: 'target_for', width: 220, render: (_, row) => row.product?.name || row.employee?.name || row.division?.name || row.area?.name || row.branch?.name || 'Company' },
                { title: 'Type', dataIndex: 'target_type', sorter: true, field: 'target_type', width: 120, render: (value) => <PharmaBadge tone={value === 'primary' ? 'info' : 'current'}>{value}</PharmaBadge> },
                { title: 'Period', dataIndex: 'target_period', sorter: true, field: 'target_period', width: 130 },
                { title: 'Amount', dataIndex: 'target_amount', align: 'right', width: 130 },
                { title: 'Quantity', dataIndex: 'target_quantity', align: 'right', width: 120 },
                { title: 'Start', dataIndex: 'start_date', sorter: true, field: 'start_date', width: 130, render: (value) => <DateText value={value} style="compact" /> },
                { title: 'End', dataIndex: 'end_date', sorter: true, field: 'end_date', width: 130, render: (value) => <DateText value={value} style="compact" /> },
                { title: 'Status', dataIndex: 'status', sorter: true, field: 'status', width: 120, render: (value, row) => row.deleted_at ? <PharmaBadge tone="danger">Deleted</PharmaBadge> : <PharmaBadge tone={value === 'active' ? 'success' : value === 'paused' ? 'warning' : 'archive'}>{value}</PharmaBadge> },
                actionColumn,
            ];
        }

        return [
            { title: 'Code', dataIndex: 'employee_code', sorter: true, field: 'employee_code', width: 130, render: (value) => value || '-' },
            { title: 'Employee', dataIndex: 'name', sorter: true, field: 'name', width: 220 },
            { title: 'Designation', dataIndex: 'designation', sorter: true, field: 'designation', width: 160, render: (value) => value || '-' },
            { title: 'Branch', dataIndex: ['branch', 'name'], width: 160, render: (value) => value || '-' },
            { title: 'Area', dataIndex: ['area', 'name'], width: 150, render: (value) => value || '-' },
            { title: 'Division', dataIndex: ['division', 'name'], width: 150, render: (value) => value || '-' },
            { title: 'Reports To', dataIndex: ['manager', 'name'], width: 170, render: (value) => value || '-' },
            { title: 'Status', dataIndex: 'is_active', width: 120, render: (value, row) => row.deleted_at ? <PharmaBadge tone="danger">Deleted</PharmaBadge> : <PharmaBadge tone={value ? 'success' : 'archive'}>{value ? 'Active' : 'Inactive'}</PharmaBadge> },
            actionColumn,
        ];
    }, [resource, table.pagination.current, table.pagination.pageSize]);

    return (
        <div className="page-stack">
            <Card
                title={config.title}
                extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openModal()}>{config.createLabel}</Button>}
            >
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search
                        value={table.search}
                        onChange={(event) => table.setSearch(event.target.value)}
                        placeholder={`Search ${config.title.toLowerCase()}`}
                        allowClear
                    />
                    {renderFilters(resource, table, lookups)}
                    <div className="table-switch">
                        <Switch
                            checked={deletedMode}
                            onChange={(deleted) => table.setFilters((filters) => ({ ...filters, deleted: deleted ? 1 : undefined }))}
                        />
                        <span>View Deleted</span>
                    </div>
                    <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                </div>
                <ServerTable table={table} columns={columns} />
            </Card>

            <Modal
                centered
                className="intent-modal"
                title={editing ? `Edit ${config.title.slice(0, -1)}` : config.createLabel}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={() => form.submit()}
                confirmLoading={saving}
                destroyOnHidden
                width={920}
            >
                <Form form={form} layout="vertical" onFinish={save}>
                    {renderForm(resource, form, lookups, productOptions, searchProducts)}
                </Form>
            </Modal>
        </div>
    );
}

function renderFilters(resource, table, lookups) {
    const statusFilter = (
        <Select
            allowClear
            placeholder="Status"
            style={{ width: 130 }}
            value={table.filters.is_active}
            onChange={(value) => table.setFilters((current) => ({ ...current, is_active: value }))}
            options={[
                { value: 1, label: 'Active' },
                { value: 0, label: 'Inactive' },
            ]}
        />
    );

    if (resource === 'targets') {
        return (
            <>
                <Select allowClear placeholder="Type" style={{ width: 140 }} value={table.filters.target_type} onChange={(value) => table.setFilters((current) => ({ ...current, target_type: value }))} options={targetTypeOptions} />
                <Select allowClear placeholder="Period" style={{ width: 150 }} value={table.filters.target_period} onChange={(value) => table.setFilters((current) => ({ ...current, target_period: value }))} options={targetPeriodOptions} />
                <Select allowClear placeholder="Level" style={{ width: 150 }} value={table.filters.target_level} onChange={(value) => table.setFilters((current) => ({ ...current, target_level: value }))} options={targetLevelOptions} />
                <Select allowClear placeholder="Status" style={{ width: 140 }} value={table.filters.status} onChange={(value) => table.setFilters((current) => ({ ...current, status: value }))} options={targetStatusOptions} />
            </>
        );
    }

    return (
        <>
            {['areas', 'employees'].includes(resource) && (
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    placeholder="Branch"
                    style={{ width: 180 }}
                    value={table.filters.branch_id}
                    onChange={(value) => table.setFilters((current) => ({ ...current, branch_id: value }))}
                    options={lookups.branches.map((item) => ({ value: item.id, label: optionLabel(item) }))}
                />
            )}
            {resource === 'employees' && (
                <>
                    <Select allowClear showSearch optionFilterProp="label" placeholder="Area" style={{ width: 170 }} value={table.filters.area_id} onChange={(value) => table.setFilters((current) => ({ ...current, area_id: value }))} options={lookups.areas.map((item) => ({ value: item.id, label: optionLabel(item) }))} />
                    <Select allowClear showSearch optionFilterProp="label" placeholder="Division" style={{ width: 170 }} value={table.filters.division_id} onChange={(value) => table.setFilters((current) => ({ ...current, division_id: value }))} options={lookups.divisions.map((item) => ({ value: item.id, label: optionLabel(item) }))} />
                </>
            )}
            {statusFilter}
        </>
    );
}

function renderForm(resource, form, lookups, productOptions, searchProducts) {
    if (resource === 'areas') {
        return (
            <>
                <div className="form-grid">
                    <Form.Item name="branch_id" label="Branch" rules={[{ required: true }]}>
                        <Select showSearch optionFilterProp="label" options={lookups.branches.map((item) => ({ value: item.id, label: optionLabel(item) }))} />
                    </Form.Item>
                    <Form.Item name="name" label="Area Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name="code" label="Area Code"><Input /></Form.Item>
                    <Form.Item name="district" label="District"><Input /></Form.Item>
                    <Form.Item name="province" label="Province"><Input /></Form.Item>
                </div>
                <Form.Item name="notes" label="Notes"><Input.TextArea rows={3} /></Form.Item>
                <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
            </>
        );
    }

    if (resource === 'divisions') {
        return (
            <>
                <div className="form-grid">
                    <Form.Item name="name" label="Division Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    <Form.Item name="code" label="Division Code"><Input /></Form.Item>
                </div>
                <Form.Item name="notes" label="Notes"><Input.TextArea rows={3} /></Form.Item>
                <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
            </>
        );
    }

    if (resource === 'targets') {
        return (
            <>
                <div className="form-grid form-grid-3">
                    <Form.Item name="target_type" label="Target Type" rules={[{ required: true }]}><Select options={targetTypeOptions} /></Form.Item>
                    <Form.Item name="target_period" label="Target Period" rules={[{ required: true }]}><Select options={targetPeriodOptions} /></Form.Item>
                    <Form.Item name="target_level" label="Target Level" rules={[{ required: true }]}><Select options={targetLevelOptions} /></Form.Item>
                </div>
                <Form.Item shouldUpdate={(prev, next) => prev.target_level !== next.target_level} noStyle>
                    {({ getFieldValue }) => {
                        const level = getFieldValue('target_level');
                        return (
                            <div className="form-grid">
                                {level === 'area' && <Form.Item name="area_id" label="Area" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" options={lookups.areas.map((item) => ({ value: item.id, label: optionLabel(item) }))} /></Form.Item>}
                                {level === 'division' && <Form.Item name="division_id" label="Division" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" options={lookups.divisions.map((item) => ({ value: item.id, label: optionLabel(item) }))} /></Form.Item>}
                                {level === 'employee' && <Form.Item name="employee_id" label="MR / Employee" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" options={lookups.employees.map((item) => ({ value: item.id, label: optionLabel(item, 'employee_code') }))} /></Form.Item>}
                                {level === 'product' && <Form.Item name="product_id" label="Product" rules={[{ required: true }]}><Select showSearch filterOption={false} onFocus={() => searchProducts('')} onSearch={searchProducts} options={productOptions} /></Form.Item>}
                            </div>
                        );
                    }}
                </Form.Item>
                <div className="form-grid">
                    <Form.Item name="target_amount" label="Target Amount"><InputNumber min={0} className="full-width" /></Form.Item>
                    <Form.Item name="target_quantity" label="Target Quantity"><InputNumber min={0} className="full-width" /></Form.Item>
                    <Form.Item name="status" label="Status"><Select options={targetStatusOptions} /></Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name="start_date" label="Start Date" rules={[{ required: true }]}><SmartDatePicker className="full-width" /></Form.Item>
                    <Form.Item name="end_date" label="End Date" rules={[{ required: true }]}><SmartDatePicker className="full-width" /></Form.Item>
                </div>
                <Form.Item name="notes" label="Notes"><Input.TextArea rows={3} /></Form.Item>
            </>
        );
    }

    return (
        <>
            <div className="form-grid">
                <Form.Item name="name" label="Employee Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                <Form.Item name="employee_code" label="Employee Code"><Input placeholder="Auto generated if empty" /></Form.Item>
            </div>
            <div className="form-grid">
                <Form.Item name="user_id" label="Login User"><Select allowClear showSearch optionFilterProp="label" options={lookups.users.map((item) => ({ value: item.id, label: `${item.name} (${item.email})` }))} /></Form.Item>
                <Form.Item name="designation" label="Designation"><Input placeholder="MR, Manager, Area Manager, HQ..." /></Form.Item>
            </div>
            <div className="form-grid form-grid-3">
                <Form.Item name="branch_id" label="Branch"><Select allowClear showSearch optionFilterProp="label" options={lookups.branches.map((item) => ({ value: item.id, label: optionLabel(item) }))} /></Form.Item>
                <Form.Item name="area_id" label="Area"><Select allowClear showSearch optionFilterProp="label" options={lookups.areas.map((item) => ({ value: item.id, label: optionLabel(item) }))} /></Form.Item>
                <Form.Item name="division_id" label="Division"><Select allowClear showSearch optionFilterProp="label" options={lookups.divisions.map((item) => ({ value: item.id, label: optionLabel(item) }))} /></Form.Item>
            </div>
            <div className="form-grid">
                <Form.Item name="reports_to_employee_id" label="Reports To"><Select allowClear showSearch optionFilterProp="label" options={lookups.employees.map((item) => ({ value: item.id, label: optionLabel(item, 'employee_code') }))} /></Form.Item>
                <Form.Item name="joined_on" label="Joined On"><SmartDatePicker className="full-width" /></Form.Item>
            </div>
            <div className="form-grid">
                <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                <Form.Item name="email" label="Email"><Input /></Form.Item>
            </div>
            <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
        </>
    );
}
