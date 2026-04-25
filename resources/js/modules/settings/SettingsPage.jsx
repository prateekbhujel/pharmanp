import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Checkbox, Col, Empty, Form, Input, Row, Select, Space, Statistic, Switch, Table, Tabs, Tag, Typography } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined, UserOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { FormDrawer } from '../../core/components/FormDrawer';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useAuth } from '../../core/auth/AuthProvider';
import { can } from '../../core/utils/permissions';

function PermissionGroupCard({ groupKey, group, selectedPermissions, permissionSearch, onTogglePermission, onToggleGroup }) {
    const visiblePermissions = group.permissions.filter((permission) => {
        if (!permissionSearch) {
            return true;
        }

        const needle = permissionSearch.toLowerCase();

        return [permission.label, permission.description, permission.name, group.label]
            .filter(Boolean)
            .some((value) => value.toLowerCase().includes(needle));
    });

    if (!visiblePermissions.length) {
        return null;
    }

    const visibleNames = visiblePermissions.map((permission) => permission.name);
    const selectedCount = visibleNames.filter((name) => selectedPermissions.includes(name)).length;
    const fullySelected = selectedCount === visibleNames.length;
    const partiallySelected = selectedCount > 0 && !fullySelected;

    return (
        <Card
            key={groupKey}
            size="small"
            className="access-permission-card"
            title={(
                <div>
                    <Typography.Text strong>{group.label}</Typography.Text>
                    <Typography.Paragraph className="access-group-description">{group.description}</Typography.Paragraph>
                </div>
            )}
            extra={(
                <Checkbox
                    checked={fullySelected}
                    indeterminate={partiallySelected}
                    onChange={(event) => onToggleGroup(visibleNames, event.target.checked)}
                >
                    Allow all
                </Checkbox>
            )}
        >
            <div className="access-checkbox-list">
                {visiblePermissions.map((permission) => (
                    <Checkbox
                        key={permission.name}
                        checked={selectedPermissions.includes(permission.name)}
                        onChange={(event) => onTogglePermission(permission.name, event.target.checked)}
                    >
                        <div className="access-checkbox-content">
                            <strong>{permission.label}</strong>
                            <small>{permission.description}</small>
                        </div>
                    </Checkbox>
                ))}
            </div>
        </Card>
    );
}

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
    const [editingRole, setEditingRole] = useState(null);
    const [creatingRole, setCreatingRole] = useState(false);
    const [roleSearch, setRoleSearch] = useState('');
    const [permissionSearch, setPermissionSearch] = useState('');
    const [roleFocusName, setRoleFocusName] = useState(null);
    const [userDrawerOpen, setUserDrawerOpen] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const selectedPermissions = Form.useWatch('permissions', roleForm) || [];
    const selectedUserRoles = Form.useWatch('role_names', userForm) || [];

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

    useEffect(() => {
        const roles = roleData?.roles || [];

        if (!roles.length) {
            return;
        }

        if (roleFocusName) {
            const focusedRole = roles.find((role) => role.name === roleFocusName);

            if (focusedRole) {
                openRole(focusedRole);
            }

            setRoleFocusName(null);
            return;
        }

        if (creatingRole) {
            return;
        }

        if (!editingRole) {
            openRole(roles[0]);
            return;
        }

        const refreshedRole = roles.find((role) => role.id === editingRole.id);

        if (refreshedRole) {
            setEditingRole(refreshedRole);
            roleForm.setFieldsValue({
                name: refreshedRole.name,
                permissions: refreshedRole.permissions || [],
            });
        }
    }, [creatingRole, editingRole, roleData, roleFocusName, roleForm]);

    function openRole(role = null) {
        setCreatingRole(false);
        setEditingRole(role);
        roleForm.resetFields();
        roleForm.setFieldsValue(role ? {
            name: role.name,
            permissions: role.permissions || [],
        } : {
            name: '',
            permissions: [],
        });
    }

    function openNewRole() {
        setCreatingRole(true);
        setEditingRole(null);
        roleForm.resetFields();
        roleForm.setFieldsValue({
            name: '',
            permissions: [],
        });
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

    function updatePermissionSelection(permissionName, checked) {
        const current = new Set(roleForm.getFieldValue('permissions') || []);

        if (checked) {
            current.add(permissionName);
        } else {
            current.delete(permissionName);
        }

        roleForm.setFieldValue('permissions', Array.from(current));
    }

    function updatePermissionGroup(permissionNames, checked) {
        const current = new Set(roleForm.getFieldValue('permissions') || []);

        permissionNames.forEach((permissionName) => {
            if (checked) {
                current.add(permissionName);
            } else {
                current.delete(permissionName);
            }
        });

        roleForm.setFieldValue('permissions', Array.from(current));
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

            setCreatingRole(false);
            setRoleFocusName(values.name);
            await reloadRoles?.();
        } catch (error) {
            roleForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Role save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function deleteRole(role) {
        await confirmDelete({
            title: `Delete role ${role.name}?`,
            content: 'Users assigned to this role should be moved first. Deleting it removes the access profile immediately.',
            onOk: async () => {
                await http.delete(`${endpoints.roles}/${role.id}`);
                notification.success({ message: 'Role deleted' });
                setCreatingRole(false);
                setEditingRole(null);
                await reloadRoles?.();
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
        await confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'The login will be removed immediately and the user will lose access.',
            onOk: async () => {
                await http.delete(`${endpoints.users}/${record.id}`);
                notification.success({ message: 'User deleted' });
                userTable.reload();
            },
        });
    }

    const featureRows = Object.entries(features || {}).flatMap(([module, items]) => items.map((item) => ({ ...item, module })));
    const permissionCatalog = roleData?.permission_groups || {};
    const permissionOptions = (roleData?.permissions || []).map((permission) => ({
        value: permission.name,
        label: permission.label,
    }));
    const roleRows = (roleData?.roles || []).filter((role) => {
        if (!roleSearch) {
            return true;
        }

        const needle = roleSearch.toLowerCase();
        return [
            role.name,
            ...(role.summary || []).map((summary) => summary.group),
        ].some((value) => value?.toLowerCase().includes(needle));
    });
    const userLookups = userTable.extra?.lookups || {};
    const userSummary = userTable.extra?.summary || {};
    const roleProfiles = userTable.extra?.role_profiles || [];
    const selectedRoleSummary = useMemo(() => {
        const groups = Object.values(permissionCatalog);
        const enabledGroups = groups.filter((group) => group.permissions.some((permission) => selectedPermissions.includes(permission.name)));

        return {
            permissions: selectedPermissions.length,
            groups: enabledGroups.length,
        };
    }, [permissionCatalog, selectedPermissions]);
    const selectedUserRoleProfiles = useMemo(() => roleProfiles.filter((role) => selectedUserRoles.includes(role.name)), [roleProfiles, selectedUserRoles]);

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
                        <Row gutter={[16, 16]}>
                            <Col xs={24} sm={12} xl={6}>
                                <Card className="summary-stat-card"><Statistic title="Users" value={userSummary.total || 0} /></Card>
                            </Col>
                            <Col xs={24} sm={12} xl={6}>
                                <Card className="summary-stat-card"><Statistic title="Active" value={userSummary.active || 0} valueStyle={{ color: '#15803d' }} /></Card>
                            </Col>
                            <Col xs={24} sm={12} xl={6}>
                                <Card className="summary-stat-card"><Statistic title="MR Linked" value={userSummary.mr_linked || 0} valueStyle={{ color: '#6d28d9' }} /></Card>
                            </Col>
                            <Col xs={24} sm={12} xl={6}>
                                <Card className="summary-stat-card"><Statistic title="Owner Access" value={userSummary.owners || 0} valueStyle={{ color: '#b45309' }} /></Card>
                            </Col>
                        </Row>
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
                                <Select
                                    allowClear
                                    placeholder="MR login"
                                    value={userTable.filters.medical_representative_linked}
                                    onChange={(value) => userTable.setFilters((current) => ({ ...current, medical_representative_linked: value }))}
                                    options={[
                                        { value: true, label: 'Linked to MR' },
                                        { value: false, label: 'No MR link' },
                                    ]}
                                />
                                <Select
                                    allowClear
                                    placeholder="Access level"
                                    value={userTable.filters.is_owner}
                                    onChange={(value) => userTable.setFilters((current) => ({ ...current, is_owner: value }))}
                                    options={[
                                        { value: true, label: 'Owner access' },
                                        { value: false, label: 'Staff access' },
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
                                    { title: 'Access', dataIndex: 'is_owner', width: 120, render: (value) => <Tag color={value ? 'gold' : 'blue'}>{value ? 'Owner' : 'Staff'}</Tag> },
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
                label: 'Access Control',
                children: (
                    <div className="access-layout">
                        <Card
                            className="access-role-list-card"
                            title="Roles"
                            extra={<Button type="primary" icon={<PlusOutlined />} onClick={openNewRole}>New Role</Button>}
                        >
                            <div className="access-role-toolbar">
                                <Input.Search
                                    value={roleSearch}
                                    onChange={(event) => setRoleSearch(event.target.value)}
                                    placeholder="Search roles or modules"
                                    allowClear
                                />
                                <Button icon={<ReloadOutlined />} onClick={() => reloadRoles?.()}>Reload</Button>
                            </div>
                            <Table
                                rowKey="id"
                                size="small"
                                pagination={false}
                                dataSource={roleRows}
                                rowClassName={(record) => record.id === editingRole?.id && !creatingRole ? 'access-role-row-active' : ''}
                                onRow={(record) => ({
                                    onClick: () => openRole(record),
                                })}
                                columns={[
                                    {
                                        title: 'Role',
                                        dataIndex: 'name',
                                        render: (value, record) => (
                                            <div className="access-role-cell">
                                                <strong>{value}</strong>
                                                <small>{record.locked ? 'System role' : `${record.user_count || 0} assigned user${record.user_count === 1 ? '' : 's'}`}</small>
                                            </div>
                                        ),
                                    },
                                    { title: 'Users', dataIndex: 'user_count', align: 'right', width: 82 },
                                    { title: 'Access', dataIndex: 'permission_count', align: 'right', width: 82 },
                                ]}
                            />
                        </Card>

                        <Card
                            className="access-editor-card"
                            title={creatingRole ? 'Create Role' : editingRole ? `Edit Role: ${editingRole.name}` : 'Role Editor'}
                            extra={(
                                <Space wrap>
                                    {editingRole ? <Tag>{editingRole.user_count || 0} users</Tag> : null}
                                    <Tag>{selectedRoleSummary.groups} modules</Tag>
                                    <Tag>{selectedRoleSummary.permissions} permissions</Tag>
                                    {editingRole && !editingRole.locked ? (
                                        <Button danger icon={<DeleteOutlined />} onClick={() => deleteRole(editingRole)}>Delete</Button>
                                    ) : null}
                                    <Button type="primary" onClick={() => roleForm.submit()}>Save Role</Button>
                                </Space>
                            )}
                        >
                            <Form form={roleForm} layout="vertical" onFinish={saveRole}>
                                <Form.Item name="permissions" hidden>
                                    <Select mode="multiple" options={permissionOptions} />
                                </Form.Item>
                                <div className="access-editor-top">
                                    <Form.Item
                                        name="name"
                                        label="Role Name"
                                        rules={[{ required: true, message: 'Role name is required.' }]}
                                        className="access-role-name-field"
                                    >
                                        <Input
                                            disabled={editingRole?.locked}
                                            placeholder="Example: Store Manager"
                                        />
                                    </Form.Item>
                                    <Card size="small" className="access-summary-card">
                                        <Typography.Text strong>Access summary</Typography.Text>
                                        <Typography.Paragraph>
                                            {selectedRoleSummary.permissions} permissions across {selectedRoleSummary.groups} module areas are currently enabled.
                                        </Typography.Paragraph>
                                        <Typography.Text type="secondary">
                                            Use role names people understand. The access list below is grouped by business area, not by developer code.
                                        </Typography.Text>
                                    </Card>
                                </div>

                                <div className="access-permission-toolbar">
                                    <Input.Search
                                        value={permissionSearch}
                                        onChange={(event) => setPermissionSearch(event.target.value)}
                                        placeholder="Search access by module, screen or action"
                                        allowClear
                                    />
                                    <Typography.Text type="secondary">
                                        Use broader access only where the role actually needs it.
                                    </Typography.Text>
                                </div>

                                <div className="access-permission-grid">
                                    {Object.entries(permissionCatalog).map(([groupKey, group]) => (
                                        <PermissionGroupCard
                                            key={groupKey}
                                            groupKey={groupKey}
                                            group={group}
                                            selectedPermissions={selectedPermissions}
                                            permissionSearch={permissionSearch}
                                            onTogglePermission={updatePermissionSelection}
                                            onToggleGroup={updatePermissionGroup}
                                        />
                                    ))}
                                </div>

                                {Object.values(permissionCatalog).every((group) => group.permissions.every((permission) => {
                                    if (!permissionSearch) {
                                        return false;
                                    }

                                    const needle = permissionSearch.toLowerCase();
                                    return ![permission.label, permission.description, permission.name, group.label]
                                        .filter(Boolean)
                                        .some((value) => value.toLowerCase().includes(needle));
                                })) ? (
                                    <Empty description="No access entries matched that search." />
                                ) : null}
                            </Form>
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
    }, [
        brandingForm,
        creatingRole,
        editingRole,
        featureRows,
        permissionCatalog,
        permissionOptions,
        permissionSearch,
        profileForm,
        roleRows,
        roleSearch,
        selectedPermissions,
        selectedRoleSummary,
        selectedUserRoleProfiles,
        user,
        userLookups,
        userSummary,
        userTable,
    ]);

    return (
        <div className="page-stack">
            <PageHeader
                title="Setup"
                description="Branding, user access, profile management, module permissions and rollout controls"
                actions={<Tag icon={<UserOutlined />}>{user?.name}</Tag>}
            />

            <Tabs items={tabs} />

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
                    {selectedUserRoleProfiles.length > 0 ? (
                        <Card size="small" className="user-role-summary-card">
                            <Typography.Text strong>Assigned access profile</Typography.Text>
                            <div className="user-role-summary-list">
                                {selectedUserRoleProfiles.map((role) => (
                                    <div key={role.id || role.name}>
                                        <strong>{role.name}</strong>
                                        <small>
                                            {role.permission_count} permissions
                                            {role.summary?.length ? ` across ${role.summary.length} business areas` : ''}
                                        </small>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    ) : null}
                    <Form.Item name="medical_representative_id" label="Linked MR">
                        <Select allowClear options={(userLookups.medical_representatives || []).map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Typography.Text type="secondary">
                        Link an MR here when this login should open MR tracking for one field representative instead of a general back-office user.
                    </Typography.Text>
                    <div className="switch-row">
                        <Form.Item name="is_active" valuePropName="checked" label="Active"><Switch /></Form.Item>
                        <Form.Item name="is_owner" valuePropName="checked" label="Owner Access"><Switch /></Form.Item>
                    </div>
                </Form>
            </FormDrawer>
        </div>
    );
}
