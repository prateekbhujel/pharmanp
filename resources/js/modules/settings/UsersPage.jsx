import React, { useState } from 'react';
import { App, Button, Card, Form, Input, Select, Space, Switch, Table } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, UserSwitchOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { FormDrawer } from '../../core/components/FormDrawer';
import { PharmaBadge, StatusBadge } from '../../core/components/PharmaBadge';
import { StatusToggle } from '../../core/components/StatusToggle';
import { DateText } from '../../core/components/DateText';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, responseToken, setApiToken } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useAuth } from '../../core/auth/AuthProvider';
import { appUrl } from '../../core/utils/url';

export function UsersPage() {
    const { notification } = App.useApp();
    const { user, reload } = useAuth();
    const [userDrawerOpen, setUserDrawerOpen] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [userForm] = Form.useForm();

    const userTable = useServerTable({ endpoint: endpoints.users });
    const userLookups = userTable.extra?.lookups || {};
    const canImpersonate = Boolean(
        user?.is_owner
        || user?.permissions?.includes('users.manage')
        || user?.roles?.includes('Super Admin')
        || user?.roles?.includes('Owner'),
    );

    const openUser = (record = null) => {
        setEditingUser(record);
        userForm.resetFields();
        if (record) {
            userForm.setFieldsValue({
                ...record,
                role_names: record.role_names || [],
            });
        } else {
            userForm.setFieldsValue({ is_active: true });
        }
        setUserDrawerOpen(true);
    };

    const saveUser = async (values) => {
        try {
            if (editingUser) {
                await http.put(endpoints.users + '/' + editingUser.id, values);
            } else {
                await http.post(endpoints.users, values);
            }
            setUserDrawerOpen(false);
            userTable.reload();
        } catch (e) {
            // Error handled by http
        }
    };

    const deleteUser = async (record) => {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'This user will lose access to PharmaNP. Existing audit history remains untouched.',
            onOk: async () => {
                await http.delete(endpoints.users + '/' + record.id);
                userTable.reload();
            },
        });
    };

    const impersonateUser = async (record) => {
        confirmDelete({
            title: `View as ${record.name}?`,
            content: 'Your admin session will be remembered so you can return from the topbar.',
            okText: 'Start Impersonation',
            danger: false,
            onOk: async () => {
                const { data } = await http.post(endpoints.userImpersonate(record.id));
                const token = responseToken(data);

                if (token) {
                    setApiToken(token);
                    await reload?.();
                }

                notification.success({ message: `Viewing as ${record.name}` });
                window.location.href = appUrl('/app');
            },
        });
    };

    return (
        <div className="page-stack">
            <PageHeader
                actions={<Button type="primary" icon={<PlusOutlined />} onClick={() => openUser()}>New User</Button>}
            />

            <Card>
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search
                        value={userTable.search}
                        onChange={(event) => userTable.setSearch(event.target.value)}
                        placeholder="Search user, email or role"
                        allowClear
                    />
                    <Select
                        allowClear
                        placeholder="Role"
                        style={{ width: 200 }}
                        value={userTable.filters.role_name}
                        onChange={(value) => userTable.setFilters((current) => ({ ...current, role_name: value }))}
                        options={(userLookups.roles || []).map((role) => ({ value: role.name, label: role.name }))}
                    />
                    <Select
                        allowClear
                        placeholder="Status"
                        style={{ width: 120 }}
                        value={userTable.filters.is_active}
                        onChange={(value) => userTable.setFilters((current) => ({ ...current, is_active: value }))}
                        options={[
                            { value: true, label: 'Active' },
                            { value: false, label: 'Inactive' },
                        ]}
                    />
                </div>
                <Table
                    rowKey="id"
                    dataSource={userTable.rows}
                    loading={userTable.loading}
                    pagination={{
                        current: userTable.pagination.current,
                        pageSize: userTable.pagination.pageSize,
                        total: userTable.pagination.total,
                        showSizeChanger: true,
                        pageSizeOptions: ['10', '15', '20', '25', '50', '100'],
                    }}
                    onChange={userTable.handleTableChange}
                    columns={[
                        {
                            title: 'SN',
                            key: '__serial',
                            width: 68,
                            align: 'center',
                            className: 'table-serial-cell',
                            render: (_, __, index) => ((userTable.pagination.current - 1) * userTable.pagination.pageSize) + index + 1,
                        },
                        { title: 'Name', dataIndex: 'name', sorter: true, field: 'name' },
                        { title: 'Email', dataIndex: 'email', sorter: true, field: 'email' },
                        {
                            title: 'Roles',
                            dataIndex: 'role_names',
                            render: (roles) => (
                                <div className="badge-row">
                                    {roles?.map((role) => <PharmaBadge key={role} tone="info">{role}</PharmaBadge>)}
                                </div>
                            ),
                        },
                        { title: 'Branch', dataIndex: ['branch', 'name'], render: (value) => value || '-' },
                        { title: 'MR Link', dataIndex: ['medical_representative', 'name'], render: (value) => value || '-' },
                        {
                            title: 'Status',
                            dataIndex: 'is_active',
                            render: (value, record) => record.id === user?.id
                                ? <StatusBadge value={value} />
                                : <StatusToggle value={value} id={record.id} endpoint={endpoints.users} />,
                            width: 150,
                        },
                        { title: 'Last Login', dataIndex: 'last_login_at', sorter: true, field: 'last_login_at', width: 170, render: (value) => <DateText value={value} includeTime style="compact" /> },
                        {
                            title: 'Action',
                            width: 156,
                            render: (_, record) => (
                                <Space>
                                    {canImpersonate && record.id !== user?.id && record.is_active && (
                                        <Button aria-label="Impersonate" icon={<UserSwitchOutlined />} onClick={() => impersonateUser(record)} />
                                    )}
                                    <Button icon={<EditOutlined />} onClick={() => openUser(record)} />
                                    <Button danger icon={<DeleteOutlined />} disabled={record.id === user?.id} onClick={() => deleteUser(record)} />
                                </Space>
                            ),
                        },
                    ]}
                    scroll={{ x: 1080 }}
                />
            </Card>

            <FormDrawer
                title={editingUser ? `Edit User: ${editingUser.name}` : 'New User'}
                open={userDrawerOpen}
                onClose={() => setUserDrawerOpen(false)}
                footer={<Button type="primary" onClick={() => userForm.submit()} block>Save User</Button>}
            >
                <Form form={userForm} layout="vertical" onFinish={saveUser}>
                    <div className="form-grid">
                        <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                        <Form.Item name="email" label="Email" rules={[{ required: true, type: 'email' }]}><Input /></Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                        <Form.Item name="password" label={editingUser ? 'New Password' : 'Password'} rules={editingUser ? [] : [{ required: true }]}>
                            <Input.Password />
                        </Form.Item>
                    </div>
                    <Form.Item name="role_names" label="Roles" rules={[{ required: true }]}>
                        <Select mode="multiple" options={(userLookups.roles || []).map((role) => ({ value: role.name, label: role.name }))} />
                    </Form.Item>
                    <div className="form-grid">
                        <Form.Item name="branch_id" label="Branch">
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                options={(userLookups.branches || []).map((item) => ({
                                    value: item.id,
                                    label: `${item.name}${item.code ? ` (${item.code})` : ''}`,
                                }))}
                            />
                        </Form.Item>
                        <Form.Item name="medical_representative_id" label="Linked MR">
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                options={(userLookups.medical_representatives || []).map((item) => ({ value: item.id, label: item.name }))}
                            />
                        </Form.Item>
                    </div>
                    <div className="switch-row">
                        <Form.Item name="is_active" valuePropName="checked" label="Active"><Switch /></Form.Item>
                        <Form.Item name="is_owner" valuePropName="checked" label="Owner Access"><Switch /></Form.Item>
                    </div>
                </Form>
            </FormDrawer>
        </div>
    );
}
