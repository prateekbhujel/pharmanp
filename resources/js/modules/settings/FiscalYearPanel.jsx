import React, { useState } from 'react';
import { Button, Card, DatePicker, Form, Input, Space, Switch, Tag } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { FormDrawer } from '../../core/components/FormDrawer';
import { ServerTable } from '../../core/components/ServerTable';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

export function FiscalYearPanel() {
    const [editing, setEditing] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [form] = Form.useForm();
    const table = useServerTable({ endpoint: endpoints.fiscalYears, defaultSort: { field: 'start_date', order: 'desc' } });

    function openDrawer(record = null) {
        setEditing(record);
        form.resetFields();
        form.setFieldsValue(record ? {
            ...record,
            start_date: record.start_date ? dayjs(record.start_date) : null,
            end_date: record.end_date ? dayjs(record.end_date) : null,
        } : { is_active: true });
        setDrawerOpen(true);
    }

    async function save(values) {
        try {
            const payload = {
                ...values,
                start_date: values.start_date.format('YYYY-MM-DD'),
                end_date: values.end_date.format('YYYY-MM-DD'),
            };

            if (editing) {
                await http.put(`${endpoints.fiscalYears}/${editing.id}`, payload);
            } else {
                await http.post(endpoints.fiscalYears, payload);
            }
            
            setDrawerOpen(false);
            table.reload();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
        }
    }

    function remove(record) {
        confirmDelete({
            title: `Delete fiscal year ${record.name}?`,
            content: 'This will remove the fiscal year from the system.',
            onOk: async () => {
                await http.delete(`${endpoints.fiscalYears}/${record.id}`);
                table.reload();
            },
        });
    }

    const columns = [
        { title: 'Fiscal Year Name', dataIndex: 'name', field: 'name', sorter: true },
        { title: 'Start Date', dataIndex: 'start_date', width: 150 },
        { title: 'End Date', dataIndex: 'end_date', width: 150 },
        { title: 'Status', dataIndex: 'is_active', width: 120, render: (value) => <Tag color={value ? 'green' : 'default'}>{value ? 'Active' : 'Closed'}</Tag> },
        {
            title: '',
            width: 120,
            render: (_, record) => (
                <Space>
                    <Button icon={<EditOutlined />} onClick={() => openDrawer(record)} />
                    <Button danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                </Space>
            ),
        },
    ];

    return (
        <Card title="Fiscal Years">
            <div className="table-toolbar">
                <Input.Search value={table.search} onChange={(e) => table.setSearch(e.target.value)} placeholder="Search fiscal years" allowClear />
                <span />
                <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer()}>Add Fiscal Year</Button>
            </div>
            
            <ServerTable table={table} columns={columns} />

            <FormDrawer
                title={editing ? 'Edit Fiscal Year' : 'New Fiscal Year'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                footer={<Button type="primary" onClick={() => form.submit()} block>Save Fiscal Year</Button>}
            >
                <Form form={form} layout="vertical" onFinish={save}>
                    <Form.Item name="name" label="Fiscal Year Name" rules={[{ required: true }]} tooltip="e.g. FY 2080/81 or 2023/24">
                        <Input />
                    </Form.Item>
                    <div className="form-grid">
                        <Form.Item name="start_date" label="Start Date" rules={[{ required: true }]}>
                            <DatePicker className="full-width" />
                        </Form.Item>
                        <Form.Item name="end_date" label="End Date" rules={[{ required: true }]}>
                            <DatePicker className="full-width" />
                        </Form.Item>
                    </div>
                    <Form.Item name="is_active" label="Status" valuePropName="checked">
                        <Switch checkedChildren="Active" unCheckedChildren="Closed" />
                    </Form.Item>
                </Form>
            </FormDrawer>
        </Card>
    );
}
