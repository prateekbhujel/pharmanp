import React, { useState } from 'react';
import { App, Button, Card, Form, Input, Select, Space, Switch } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { PharmaBadge, StatusBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { FormDrawer } from '../../core/components/FormDrawer';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

export function MrBranchesPanel({ branchOptions, canManage, onLookupsChange }) {
    const { notification } = App.useApp();
    const [branchDrawerOpen, setBranchDrawerOpen] = useState(false);
    const [editingBranch, setEditingBranch] = useState(null);
    const [branchForm] = Form.useForm();

    const branchTable = useServerTable({
        endpoint: endpoints.mrBranches,
        defaultSort: { field: 'name', order: 'asc' },
        enabled: canManage,
    });

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
            if (onLookupsChange) onLookupsChange();
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
                if (onLookupsChange) onLookupsChange();
            },
        });
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

    return (
        <>
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
        </>
    );
}
