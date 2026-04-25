import React, { useMemo, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Select, Space, Switch } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined } from '@ant-design/icons';
import { ServerTable } from '../../core/components/ServerTable';
import { StatusTag } from '../../core/components/StatusTag';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

const configs = {
    companies: {
        title: 'Companies / Manufacturers',
        createLabel: 'New Company',
        fields: ['name', 'legal_name', 'pan_number', 'phone', 'email', 'company_type', 'default_cc_rate'],
        columns: [
            { title: 'Name', dataIndex: 'name', field: 'name', sorter: true },
            { title: 'PAN', dataIndex: 'pan_number', width: 130 },
            { title: 'Phone', dataIndex: 'phone', width: 140 },
            { title: 'Type', dataIndex: 'company_type', width: 130 },
            { title: 'CC %', dataIndex: 'default_cc_rate', align: 'right', width: 100 },
        ],
    },
    units: {
        title: 'Units',
        createLabel: 'New Unit',
        fields: ['name', 'code', 'type', 'factor'],
        defaults: { type: 'both', factor: 1, is_active: true },
        columns: [
            { title: 'Name', dataIndex: 'name', field: 'name', sorter: true },
            { title: 'Code', dataIndex: 'code', width: 120 },
            { title: 'Type', dataIndex: 'type', width: 140 },
            { title: 'Factor', dataIndex: 'factor', align: 'right', width: 120 },
        ],
    },
    categories: {
        title: 'Categories / Types',
        createLabel: 'New Category',
        fields: ['name', 'code'],
        columns: [
            { title: 'Name', dataIndex: 'name', field: 'name', sorter: true },
            { title: 'Code', dataIndex: 'code', width: 160 },
        ],
    },
};

export function InventoryMasterTable({ master }) {
    const { notification } = App.useApp();
    const config = configs[master];
    const table = useServerTable({ endpoint: endpoints.inventoryMaster(master), defaultSort: { field: 'name', order: 'asc' } });
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form] = Form.useForm();

    const columns = useMemo(() => [
        ...config.columns,
        { title: 'Status', dataIndex: 'is_active', width: 110, render: (value) => <StatusTag active={value} /> },
        {
            title: '',
            key: 'actions',
            fixed: 'right',
            width: 112,
            render: (_, record) => (
                <Space>
                    <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                    <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                </Space>
            ),
        },
    ], [config]);

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

    return (
        <Card>
            <div className="table-toolbar">
                <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder={`Search ${config.title.toLowerCase()}`} allowClear />
                <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>{config.createLabel}</Button>
            </div>
            <ServerTable table={table} columns={columns} />

            <Modal
                title={editing ? `Edit ${config.title}` : config.createLabel}
                open={open}
                onCancel={() => setOpen(false)}
                onOk={() => form.submit()}
                confirmLoading={saving}
                destroyOnHidden
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    {config.fields.includes('legal_name') && <Form.Item name="legal_name" label="Legal Name"><Input /></Form.Item>}
                    {config.fields.includes('code') && <Form.Item name="code" label="Code"><Input /></Form.Item>}
                    {config.fields.includes('pan_number') && <Form.Item name="pan_number" label="PAN"><Input /></Form.Item>}
                    {config.fields.includes('phone') && <Form.Item name="phone" label="Phone"><Input /></Form.Item>}
                    {config.fields.includes('email') && <Form.Item name="email" label="Email"><Input /></Form.Item>}
                    {config.fields.includes('company_type') && <Form.Item name="company_type" label="Type" initialValue="manufacturer"><Input /></Form.Item>}
                    {config.fields.includes('default_cc_rate') && <Form.Item name="default_cc_rate" label="Default CC %"><InputNumber min={0} max={100} className="full-width" /></Form.Item>}
                    {config.fields.includes('type') && (
                        <Form.Item name="type" label="Type" rules={[{ required: true }]}>
                            <Select options={[
                                { value: 'both', label: 'Purchase and sale' },
                                { value: 'purchase', label: 'Purchase only' },
                                { value: 'sale', label: 'Sale only' },
                            ]} />
                        </Form.Item>
                    )}
                    {config.fields.includes('factor') && <Form.Item name="factor" label="Factor"><InputNumber min={0.0001} className="full-width" /></Form.Item>}
                    <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
