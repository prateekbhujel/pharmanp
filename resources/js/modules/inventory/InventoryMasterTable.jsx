import React, { useMemo, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Select, Space, Switch } from 'antd';
import { CopyOutlined, DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined, UndoOutlined } from '@ant-design/icons';
import { DateText } from '../../core/components/DateText';
import { ExportButtons, ImportButton } from '../../core/components/ListToolbarActions';
import { ServerTable } from '../../core/components/ServerTable';
import { StatusTag } from '../../core/components/StatusTag';
import { StatusToggle } from '../../core/components/StatusToggle';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

const configs = {
    companies: {
        title: 'Companies / Manufacturers',
        createLabel: 'New Company',
        fields: ['name', 'company_type', 'default_cc_rate'],
        defaults: { company_type: 'domestic', default_cc_rate: 0, is_active: true },
        columns: [
            { title: 'Company Name', dataIndex: 'name', field: 'name', sorter: true, width: 260 },
            { title: 'Type', dataIndex: 'company_type', width: 140, render: (value) => labelFor(companyTypeOptions, value) },
            { title: 'Default CC Rate', dataIndex: 'default_cc_rate', align: 'right', width: 150, render: (value) => `${Number(value || 0).toFixed(2)}%` },
            { title: 'Added At', dataIndex: 'created_at', width: 130, render: (value) => <DateText value={value} style="compact" /> },
        ],
    },
    units: {
        title: 'Units',
        createLabel: 'New Unit',
        fields: ['name', 'type', 'description'],
        defaults: { type: 'both', factor: 1, is_active: true },
        columns: [
            { title: 'Unit Name', dataIndex: 'name', field: 'name', sorter: true, width: 220 },
            { title: 'Usage Type', dataIndex: 'type', width: 140, render: (value) => labelFor(unitTypeOptions, value) },
            { title: 'Description', dataIndex: 'description', width: 320, render: (value) => value || '-' },
            { title: 'Added At', dataIndex: 'created_at', width: 130, render: (value) => <DateText value={value} style="compact" /> },
        ],
    },
    categories: {
        title: 'Categories / Types',
        createLabel: 'New Category',
        fields: ['name', 'code'],
        columns: [
            { title: 'Name', dataIndex: 'name', field: 'name', sorter: true },
            { title: 'Code', dataIndex: 'code', width: 160 },
            { title: 'Added At', dataIndex: 'created_at', width: 130, render: (value) => <DateText value={value} style="compact" /> },
        ],
    },
};

const companyTypeOptions = [
    { value: 'domestic', label: 'Domestic' },
    { value: 'foreign', label: 'Foreign' },
];

const unitTypeOptions = [
    { value: 'both', label: 'Purchase and sale' },
    { value: 'purchase', label: 'Purchase only' },
    { value: 'sale', label: 'Sale only' },
];

function labelFor(options, value) {
    return options.find((item) => item.value === value)?.label || value || '-';
}

export function InventoryMasterTable({ master }) {
    const { notification } = App.useApp();
    const config = configs[master];
    const table = useServerTable({ endpoint: endpoints.inventoryMaster(master), defaultSort: { field: 'name', order: 'asc' } });
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form] = Form.useForm();
    const deletedMode = Boolean(table.filters.deleted);

    const columns = useMemo(() => [
        ...config.columns,
        { title: 'Status', dataIndex: 'is_active', width: 150, render: (value, record) => record.deleted_at ? <StatusTag active={false} falseText="Deleted" /> : <StatusToggle value={value} id={record.id} endpoint={endpoints.inventoryMaster(master)} /> },
        {
            title: 'Action',
            key: 'actions',
            fixed: 'right',
            width: deletedMode ? 100 : 150,
            render: (_, record) => (
                record.deleted_at ? (
                    <Button aria-label="Restore" icon={<UndoOutlined />} onClick={() => restore(record)}>Restore</Button>
                ) : (
                    <Space>
                        <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                        <Button aria-label="Copy" icon={<CopyOutlined />} onClick={() => openCopy(record)} />
                        <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                    </Space>
                )
            ),
        },
    ], [config, deletedMode]);

    function openCreate() {
        setEditing(null);
        form.resetFields();
        form.setFieldsValue({ is_active: true, ...config.defaults });
        setOpen(true);
    }

    function openEdit(record) {
        setEditing(record);
        form.setFieldsValue(record);
        setOpen(true);
    }

    function openCopy(record) {
        setEditing(null);
        form.resetFields();
        form.setFieldsValue({
            ...record,
            name: `${record.name} Copy`,
            is_active: true,
        });
        setOpen(true);
    }

    async function submit(values) {
        setSaving(true);
        try {
            if (editing) {
                await http.put(`${endpoints.inventoryMaster(master)}/${editing.id}`, values);
                notification.success({ message: 'Master updated' });
            } else {
                await http.post(endpoints.inventoryMaster(master), values);
                notification.success({ message: 'Master created' });
            }
            setOpen(false);
            table.reload();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Master save failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    function remove(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'This will soft delete the master record.',
            onOk: async () => {
                await http.delete(`${endpoints.inventoryMaster(master)}/${record.id}`);
                notification.success({ message: 'Master deleted' });
                table.reload();
            },
        });
    }

    function restore(record) {
        confirmDelete({
            title: `Restore ${record.name}?`,
            content: 'This record will return to the active master list.',
            okText: 'Restore',
            danger: false,
            onOk: async () => {
                await http.post(endpoints.inventoryMasterRestore(master, record.id));
                notification.success({ message: 'Master restored' });
                table.reload();
            },
        });
    }

    return (
        <Card>
            <div className="legacy-list-actions">
                <ExportButtons basePath={endpoints.inventoryMasterExport(master)} params={{ search: table.search, deleted: table.filters.deleted }} />
                {['companies', 'units'].includes(master) && <ImportButton target={master} />}
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>{config.createLabel}</Button>
            </div>
            <div className="table-toolbar table-toolbar-legacy">
                <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder={`Search ${config.title.toLowerCase()}`} allowClear />
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

            <Modal
                title={editing ? `Edit ${config.title}` : config.createLabel}
                open={open}
                onCancel={() => setOpen(false)}
                onOk={() => form.submit()}
                confirmLoading={saving}
                destroyOnHidden
                width={720}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    {config.fields.includes('legal_name') && <Form.Item name="legal_name" label="Legal Name"><Input /></Form.Item>}
                    {config.fields.includes('code') && <Form.Item name="code" label="Code"><Input /></Form.Item>}
                    {config.fields.includes('pan_number') && <Form.Item name="pan_number" label="PAN"><Input /></Form.Item>}
                    {config.fields.includes('phone') && <Form.Item name="phone" label="Phone"><Input /></Form.Item>}
                    {config.fields.includes('email') && <Form.Item name="email" label="Email"><Input /></Form.Item>}
                    {config.fields.includes('address') && <Form.Item name="address" label="Address"><Input.TextArea rows={2} /></Form.Item>}
                    {config.fields.includes('company_type') && (
                        <Form.Item name="company_type" label="Company Type" rules={[{ required: true }]}>
                            <Select options={companyTypeOptions} />
                        </Form.Item>
                    )}
                    {config.fields.includes('default_cc_rate') && <Form.Item name="default_cc_rate" label="Default CC %"><InputNumber min={0} max={100} className="full-width" /></Form.Item>}
                    {config.fields.includes('type') && (
                        <Form.Item name="type" label="Type" rules={[{ required: true }]}>
                            <Select options={unitTypeOptions} />
                        </Form.Item>
                    )}
                    {config.fields.includes('factor') && <Form.Item name="factor" label="Factor"><InputNumber min={0.0001} className="full-width" /></Form.Item>}
                    {config.fields.includes('description') && <Form.Item name="description" label="Description"><Input.TextArea rows={3} /></Form.Item>}
                    <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
