import React, { useState } from 'react';
import { Button, Card, Form, Input, Select, Space, Switch } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { FormDrawer } from '../../core/components/FormDrawer';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

export function FiscalYearPanel() {
    const [editing, setEditing] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [form] = Form.useForm();
    const table = useServerTable({ endpoint: endpoints.fiscalYears, defaultSort: { field: 'starts_on', order: 'desc' } });

    function openDrawer(record = null) {
        setEditing(record);
        form.resetFields();
        form.setFieldsValue(record ? {
            ...record,
            starts_on: record.starts_on ? dayjs(record.starts_on) : null,
            ends_on: record.ends_on ? dayjs(record.ends_on) : null,
        } : { is_current: true, status: 'open' });
        setDrawerOpen(true);
    }

    async function save(values) {
        try {
            const payload = {
                ...values,
                starts_on: values.starts_on.format('YYYY-MM-DD'),
                ends_on: values.ends_on.format('YYYY-MM-DD'),
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
        { title: 'Start Date', dataIndex: 'starts_on', field: 'starts_on', sorter: true, width: 150, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'End Date', dataIndex: 'ends_on', field: 'ends_on', sorter: true, width: 150, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Status', dataIndex: 'status', field: 'status', sorter: true, width: 120, render: (value) => <PharmaBadge tone={value} dot>{value === 'open' ? 'Open' : 'Closed'}</PharmaBadge> },
        { title: 'Current', dataIndex: 'is_current', width: 110, render: (value) => value ? <PharmaBadge tone="current" dot>Current</PharmaBadge> : <PharmaBadge tone="archive">Archive</PharmaBadge> },
        {
            title: 'Action',
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
                        <Form.Item name="starts_on" label="Start Date" rules={[{ required: true }]}>
                            <SmartDatePicker className="full-width" />
                        </Form.Item>
                        <Form.Item name="ends_on" label="End Date" rules={[{ required: true }]}>
                            <SmartDatePicker className="full-width" />
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="status" label="Status" rules={[{ required: true }]}>
                            <Select
                                options={[
                                    { value: 'open', label: 'Open' },
                                    { value: 'closed', label: 'Closed' },
                                ]}
                            />
                        </Form.Item>
                        <Form.Item name="is_current" label="Current Fiscal Year" valuePropName="checked">
                            <Switch checkedChildren="Current" unCheckedChildren="Archive" />
                        </Form.Item>
                    </div>
                </Form>
            </FormDrawer>
        </Card>
    );
}
