import React, { useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Space, Switch, Tabs } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { ServerTable } from '../../core/components/ServerTable';
import { FormDrawer } from '../../core/components/FormDrawer';
import { Money } from '../../core/components/Money';
import { StatusTag } from '../../core/components/StatusTag';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

function PartyTab({ type }) {
    const { notification } = App.useApp();
    const endpoint = type === 'suppliers' ? endpoints.suppliers : endpoints.customers;
    const table = useServerTable({ endpoint });
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form] = Form.useForm();

    function open(record = null) {
        setEditing(record);
        form.resetFields();
        form.setFieldsValue(record || { is_active: true, opening_balance: 0, credit_limit: 0 });
        setDrawerOpen(true);
    }

    async function submit(values) {
        try {
            if (editing) {
                await http.put(`${endpoint}/${editing.id}`, values);
                notification.success({ message: 'Party updated' });
            } else {
                await http.post(endpoint, values);
                notification.success({ message: 'Party created' });
            }
            setDrawerOpen(false);
            table.reload();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Save failed', description: error?.response?.data?.message || error.message });
        }
    }

    function remove(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'Existing transactions remain; this only disables the party for future use.',
            onOk: async () => {
                await http.delete(`${endpoint}/${record.id}`);
                table.reload();
            },
        });
    }

    const columns = [
        { title: 'Name', dataIndex: 'name', sorter: true, field: 'name' },
        { title: 'Phone', dataIndex: 'phone', width: 140 },
        { title: 'PAN', dataIndex: 'pan_number', width: 120 },
        { title: 'Balance', dataIndex: 'current_balance', sorter: true, field: 'current_balance', align: 'right', width: 130, render: (value) => <Money value={value} /> },
        { title: 'Status', dataIndex: 'is_active', width: 110, render: (value) => <StatusTag active={value} /> },
        { title: '', width: 112, render: (_, record) => <Space><Button icon={<EditOutlined />} onClick={() => open(record)} /><Button danger icon={<DeleteOutlined />} onClick={() => remove(record)} /></Space> },
    ];

    return (
        <Card>
            <div className="table-toolbar">
                <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder={`Search ${type}`} allowClear />
                <span />
                <Button type="primary" icon={<PlusOutlined />} onClick={() => open()}>New {type === 'suppliers' ? 'Supplier' : 'Customer'}</Button>
            </div>
            <ServerTable table={table} columns={columns} />

            <FormDrawer
                title={editing ? 'Edit Party' : 'New Party'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                footer={<Button type="primary" onClick={() => form.submit()} block>Save</Button>}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="contact_person" label="Contact Person"><Input /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                        <Form.Item name="email" label="Email"><Input /></Form.Item>
                    </div>
                    <Form.Item name="pan_number" label="PAN"><Input /></Form.Item>
                    <Form.Item name="address" label="Address"><Input.TextArea rows={2} /></Form.Item>
                    {type === 'customers' && <Form.Item name="credit_limit" label="Credit Limit"><InputNumber min={0} className="full-width" /></Form.Item>}
                    <Form.Item name="opening_balance" label="Opening Balance"><InputNumber className="full-width" /></Form.Item>
                    <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </FormDrawer>
        </Card>
    );
}

export function PartiesPage() {
    return (
        <div className="page-stack">
            <PageHeader title="Parties" description="Supplier and customer masters with server-side tables and quick drawer entry" />
            <Tabs items={[
                { key: 'suppliers', label: 'Suppliers', children: <PartyTab type="suppliers" /> },
                { key: 'customers', label: 'Customers', children: <PartyTab type="customers" /> },
            ]} />
        </div>
    );
}
