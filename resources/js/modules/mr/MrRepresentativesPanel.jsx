import React, { useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Select, Space, Switch } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { Money } from '../../core/components/Money';
import { StatusBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

export function MrRepresentativesPanel({ branchOptions, areaOptions, divisionOptions, canManage }) {
    const { notification } = App.useApp();
    const [view, setView] = useState('list');
    const [editingMr, setEditingMr] = useState(null);
    const [mrForm] = Form.useForm();

    const mrTable = useServerTable({
        endpoint: endpoints.mrRepresentatives,
        defaultSort: { field: 'name', order: 'asc' },
        enabled: canManage,
    });

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
            },
        });
    }

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

    if (view === 'mr') {
        return (
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
        );
    }

    return (
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
    );
}
