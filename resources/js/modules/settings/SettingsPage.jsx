import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Checkbox, Form, Input, Modal, Select, Space, Switch, Table, Tabs, Tag } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, UserOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { FormDrawer } from '../../core/components/FormDrawer';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useAuth } from '../../core/auth/AuthProvider';
import { can } from '../../core/utils/permissions';

export function SettingsPage() {
    const { notification } = App.useApp();
    const { user } = useAuth();
    const { data: features } = useApi(endpoints.featureCatalog);
    const { data: branding, reload: reloadBranding } = useApi(endpoints.branding);
    const { data: roleData, reload: reloadRoles } = useApi(endpoints.roles);
    const { data: profile, reload: reloadProfile } = useApi(endpoints.profile);
    const userTable = useServerTable({ endpoint: endpoints.users, defaultSort: { field: 'created_at', order: 'desc' } });
    const [brandingForm] = Form.useForm();
    const [profileForm] = Form.useForm();
    const [roleForm] = Form.useForm();
    const [userForm] = Form.useForm();
    const [roleModalOpen, setRoleModalOpen] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [userDrawerOpen, setUserDrawerOpen] = useState(false);
    const [editingUser, setEditingUser] = useState(null);

    useEffect(() => {
        if (branding) {
            brandingForm.setFieldsValue(branding);
        }
    }, [branding, brandingForm]);

    useEffect(() => {
        if (profile) {
            profileForm.setFieldsValue({
                ...profile,
                password: undefined,
                current_password: undefined,
            });
        }
    }, [profile, profileForm]);

    function openRole(role = null) {
        setEditingRole(role);
        roleForm.resetFields();
        roleForm.setFieldsValue(role || { permissions: [] });
        setRoleModalOpen(true);
    }

    function openUser(userRecord = null) {
        setEditingUser(userRecord);
        userForm.resetFields();
        userForm.setFieldsValue(userRecord || {
            is_active: true,
            is_owner: false,
            role_names: [],
        });
        setUserDrawerOpen(true);
    }

    async function saveBranding(values) {
        try {
            await http.put(endpoints.branding, values);
            notification.success({ message: 'Application setup saved' });
            reloadBranding?.();
            window.location.reload();
        } catch (error) {
            brandingForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Setup save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function saveProfile(values) {
        try {
            await http.put(endpoints.profile, values);
            notification.success({ message: 'Profile updated' });
            reloadProfile?.();
            window.location.reload();
        } catch (error) {
            profileForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Profile update failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function saveRole(values) {
        try {
            if (editingRole) {
                await http.put(`${endpoints.roles}/${editingRole.id}`, values);
                notification.success({ message: 'Role updated' });
            } else {
                await http.post(endpoints.roles, values);
                notification.success({ message: 'Role created' });
            }
            setRoleModalOpen(false);
            roleForm.resetFields();
            reloadRoles?.();
        } catch (error) {
            roleForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Role save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function deleteRole(role) {
        confirmDelete({
            title: `Delete role ${role.name}?`,
            content: 'Users on this role should be reassigned before deleting it.',
            onOk: async () => {
                await http.delete(`${endpoints.roles}/${role.id}`);
                notification.success({ message: 'Role deleted' });
                reloadRoles?.();
            },
        });
    }

    async function saveUser(values) {
        try {
            const payload = {
                ...values,
                password: values.password || undefined,
            };

            if (editingUser) {
                await http.put(`${endpoints.users}/${editingUser.id}`, payload);
                notification.success({ message: 'User updated' });
            } else {
                await http.post(endpoints.users, payload);
                notification.success({ message: 'User created' });
            }

            setUserDrawerOpen(false);
            userForm.resetFields();
            userTable.reload();
        } catch (error) {
            userForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'User save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function deleteUser(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'The login will be removed immediately.',
            onOk: async () => {
                await http.delete(`${endpoints.users}/${record.id}`);
                notification.success({ message: 'User deleted' });
                userTable.reload();
            },
        });
    }

    const featureRows = Object.entries(features || {}).flatMap(([module, items]) => items.map((item) => ({ ...item, module })));
    const permissionOptions = (roleData?.permissions || []).map((name) => ({ value: name, label: name }));
    const permissionGroups = roleData?.permission_groups || {};
    const userLookups = userTable.extra?.lookups || {};

    const tabs = useMemo(() => {
        const items = [
            {
                key: 'branding',
                label: 'Application',
                children: (
                    <Card title="Application Identity">
                        <Form form={brandingForm} layout="vertical" onFinish={saveBranding}>
                            <div className="form-grid">
                                <Form.Item name="app_name" label="App Name" rules={[{ required: true }]}><Input /></Form.Item>
                                <Form.Item name="layout" label="Navigation Layout" rules={[{ required: true }]}>
                                    <Select options={[
                                        { value: 'vertical', label: 'Vertical sidebar' },
                                        { value: 'horizontal', label: 'Horizontal top menu' },
                                    ]} />
                                </Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="logo_url" label="Logo URL / Path"><Input placeholder="/storage/settings/logo.png" /></Form.Item>
                                <Form.Item name="sidebar_logo_url" label="Sidebar Logo URL / Path"><Input placeholder="/storage/settings/sidebar-logo.png" /></Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="app_icon_url" label="App Icon URL / Path"><Input placeholder="/storage/settings/app-icon.png" /></Form.Item>
                                <Form.Item name="favicon_url" label="Favicon URL / Path"><Input placeholder="/storage/settings/favicon.ico" /></Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="accent_color" label="Accent Color"><Input /></Form.Item>
                                <Form.Item name="sidebar_default_collapsed" valuePropName="checked">
                                    <Checkbox>Start sidebar minimized</Checkbox>
                                </Form.Item>
                            </div>
                            <Button type="primary" htmlType="submit">Save Application Setup</Button>
                        </Form>
                    </Card>
                ),
            },
            {
                key: 'profile',
                label: 'Profile',
                children: (
                    <Card title="My Profile">
                        <Form form={profileForm} layout="vertical" onFinish={saveProfile}>
                            <div className="form-grid">
                                <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                                <Form.Item name="email" label="Email" rules={[{ required: true, type: 'email' }]}><Input /></Form.Item>
                            </div>
                            <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                            <div className="form-grid">
                                <Form.Item name="current_password" label="Current Password"><Input.Password /></Form.Item>
                                <Form.Item name="password" label="New Password"><Input.Password /></Form.Item>
                            </div>
                            <Button type="primary" htmlType="submit">Save Profile</Button>
                        </Form>
                    </Card>
                ),
            },
        ];

        if (user?.is_owner || can(user, 'users.manage')) {
            items.push({
                key: 'users',
                label: 'Users',
                children: (
                    <div className="page-stack">
                        <Card>
                            <div className="table-toolbar table-toolbar-wide">
                                <Input.Search value={userTable.search} onChange={(event) => userTable.setSearch(event.target.value)} placeholder="Search user, email or role" allowClear />
                                <Select
                                    allowClear
                                    placeholder="Role"
                                    value={userTable.filters.role_name}
                                    onChange={(value) => userTable.setFilters((current) => ({ ...current, role_name: value }))}
                                    options={(userLookups.roles || []).map((role) => ({ value: role.name, label: role.name }))}
                                />
                                <Select
                                    allowClear
                                    placeholder="Status"
                                    value={userTable.filters.is_active}
                                    onChange={(value) => userTable.setFilters((current) => ({ ...current, is_active: value }))}
                                    options={[
                                        { value: true, label: 'Active' },
                                        { value: false, label: 'Inactive' },
                                    ]}
                                />
                                <Button type="primary" icon={<PlusOutlined />} onClick={() => openUser()}>New User</Button>
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
                                }}
                                onChange={userTable.handleTableChange}
                                columns={[
                                    { title: 'Name', dataIndex: 'name', sorter: true, field: 'name' },
                                    { title: 'Email', dataIndex: 'email', sorter: true, field: 'email' },
                                    { title: 'Roles', dataIndex: 'role_names', render: (roles) => roles?.map((role) => <Tag key={role}>{role}</Tag>) },
                                    { title: 'MR Link', dataIndex: ['medical_representative', 'name'], render: (value) => value || '-' },
                                    { title: 'Status', dataIndex: 'is_active', render: (value) => <Tag color={value ? 'green' : 'red'}>{value ? 'Active' : 'Inactive'}</Tag>, width: 110 },
                                    { title: 'Last Login', dataIndex: 'last_login_at', sorter: true, field: 'last_login_at', width: 170, render: (value) => value || '-' },
                                    {
                                        title: '',
                                        width: 112,
                                        render: (_, record) => (
                                            <Space>
                                                <Button icon={<EditOutlined />} onClick={() => openUser(record)} />
                                                <Button danger icon={<DeleteOutlined />} disabled={record.id === user?.id} onClick={() => deleteUser(record)} />
                                            </Space>
                                        ),
                                    },
                                ]}
                                scroll={{ x: 1080 }}
                            />
                        </Card>
                    </div>
                ),
            });
        }

        if (user?.is_owner || can(user, 'roles.manage')) {
            items.push({
                key: 'roles',
                label: 'Roles / Permissions',
                children: (
                    <div className="page-stack">
                        <Card title="Roles" extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openRole()}>New Role</Button>}>
                            <Table
                                rowKey="id"
                                dataSource={roleData?.roles || []}
                                pagination={false}
                                columns={[
                                    { title: 'Role', dataIndex: 'name' },
                                    { title: 'Permissions', dataIndex: 'permissions', render: (permissions) => permissions?.map((name) => <Tag key={name}>{name}</Tag>) },
                                    {
                                        title: '',
                                        width: 112,
                                        render: (_, role) => (
                                            <Space>
                                                <Button icon={<EditOutlined />} onClick={() => openRole(role)} />
                                                <Button danger icon={<DeleteOutlined />} disabled={role.locked} onClick={() => deleteRole(role)} />
                                            </Space>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                        <Card title="Permission Map">
                            <div className="permission-group-grid">
                                {Object.entries(permissionGroups).map(([group, permissions]) => (
                                    <Card key={group} size="small" title={group}>
                                        <Space wrap>
                                            {permissions.map((permission) => <Tag key={permission}>{permission}</Tag>)}
                                        </Space>
                                    </Card>
                                ))}
                            </div>
                        </Card>
                    </div>
                ),
            });
        }

        items.push({
            key: 'features',
            label: 'Feature Checklist',
            children: (
                <Card>
                    <Table
                        rowKey="code"
                        dataSource={featureRows}
                        pagination={{ pageSize: 15 }}
                        columns={[
                            { title: 'Module', dataIndex: 'module', width: 150 },
                            { title: 'Feature', dataIndex: 'name', width: 240 },
                            { title: 'Status', dataIndex: 'status', width: 130, render: (value) => <Tag color={value === 'foundation' ? 'green' : 'gold'}>{value}</Tag> },
                            { title: 'Use Case', dataIndex: 'description' },
                        ]}
                    />
                </Card>
            ),
        });

        return items;
    }, [brandingForm, featureRows, profileForm, roleData, user, userLookups, userTable]);

    return (
        <div className="page-stack">
            <PageHeader
                title="Setup"
                description="Branding, user access, profile management, module permissions and rollout controls"
                actions={<Tag icon={<UserOutlined />}>{user?.name}</Tag>}
            />

            <Tabs items={tabs} />

            <Modal
                title={editingRole ? `Edit Role: ${editingRole.name}` : 'New Role'}
                open={roleModalOpen}
                onCancel={() => setRoleModalOpen(false)}
                onOk={() => roleForm.submit()}
                width={820}
                destroyOnHidden
            >
                <Form form={roleForm} layout="vertical" onFinish={saveRole}>
                    <Form.Item name="name" label="Role Name" rules={[{ required: true }]}><Input disabled={editingRole?.locked} /></Form.Item>
                    <Form.Item name="permissions" label="Permissions">
                        <Select mode="multiple" optionFilterProp="label" options={permissionOptions} />
                    </Form.Item>
                </Form>
            </Modal>

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
                    <Form.Item name="medical_representative_id" label="Linked MR">
                        <Select allowClear options={(userLookups.medical_representatives || []).map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <div className="switch-row">
                        <Form.Item name="is_active" valuePropName="checked" label="Active"><Switch /></Form.Item>
                        <Form.Item name="is_owner" valuePropName="checked" label="Owner Access"><Switch /></Form.Item>
                    </div>
                </Form>
            </FormDrawer>
        </div>
    );
}
