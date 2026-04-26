import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { App, Badge, Button, Card, Checkbox, Form, Input, InputNumber, Modal, Popconfirm, Select, Space, Switch, Table, Tabs, Tag, Upload } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, SendOutlined, UploadOutlined, UserOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { FormDrawer } from '../../core/components/FormDrawer';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useAuth } from '../../core/auth/AuthProvider';
import { can } from '../../core/utils/permissions';

function normalizeFile(event) {
    return Array.isArray(event) ? event : event?.fileList;
}

function brandingPayload(values) {
    const payload = new FormData();

    Object.entries(values).forEach(([key, value]) => {
        if (key.endsWith('_upload')) {
            const file = value?.[0]?.originFileObj;
            if (file) {
                payload.append(key.replace('_upload', '_file'), file);
            }

            return;
        }

        if (typeof value === 'boolean') {
            payload.append(key, value ? '1' : '0');
            return;
        }

        if (value !== undefined && value !== null) {
            payload.append(key, value);
        }
    });

    payload.append('_method', 'PUT');

    return payload;
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
    const [roleModalOpen, setRoleModalOpen] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [userDrawerOpen, setUserDrawerOpen] = useState(false);
    const [editingUser, setEditingUser] = useState(null);

    // --- Phase 2 state ---
    const [adminSettings, setAdminSettings] = useState({});
    const [adminSettingsLoading, setAdminSettingsLoading] = useState(false);
    const [adminForm] = Form.useForm();
    const [dropdownOptions, setDropdownOptions] = useState([]);
    const [dropdownAliases, setDropdownAliases] = useState({});
    const [dropdownAlias, setDropdownAlias] = useState('payment_mode');
    const [dropdownForm] = Form.useForm();
    const [dropdownModalOpen, setDropdownModalOpen] = useState(false);
    const [editingOption, setEditingOption] = useState(null);
    const [partyTypes, setPartyTypes] = useState([]);
    const [partyTypeForm] = Form.useForm();
    const [partyTypeModalOpen, setPartyTypeModalOpen] = useState(false);
    const [editingPartyType, setEditingPartyType] = useState(null);
    const [supplierTypes, setSupplierTypes] = useState([]);
    const [supplierTypeForm] = Form.useForm();
    const [supplierTypeModalOpen, setSupplierTypeModalOpen] = useState(false);
    const [editingSupplierType, setEditingSupplierType] = useState(null);

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

    useEffect(() => { loadAdminSettings(); loadDropdownOptions(); loadPartyTypes(); loadSupplierTypes(); }, []);

    async function loadAdminSettings() {
        setAdminSettingsLoading(true);
        try {
            const { data } = await http.get(endpoints.settingsAdmin);
            setAdminSettings(data.data || {});
            adminForm.setFieldsValue(data.data || {});
        } finally {
            setAdminSettingsLoading(false);
        }
    }

    async function saveAdminSettings(values) {
        try {
            await http.put(endpoints.settingsAdmin, values);
            notification.success({ message: 'Settings saved' });
            loadAdminSettings();
        } catch (error) {
            adminForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function sendTestMail() {
        try {
            const { data } = await http.post(endpoints.settingsTestMail, { email: adminForm.getFieldValue('notification_email') || adminForm.getFieldValue('mail_from_address') });
            notification.success({ message: data.message });
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Test mail failed' });
        }
    }

    async function loadDropdownOptions() {
        const { data } = await http.get(endpoints.dropdownOptions);
        setDropdownOptions(data.data || []);
        setDropdownAliases(data.aliases || {});
    }

    function openDropdownOption(option = null) {
        setEditingOption(option);
        dropdownForm.resetFields();
        dropdownForm.setFieldsValue(option || { alias: dropdownAlias, status: true });
        setDropdownModalOpen(true);
    }

    async function saveDropdownOption(values) {
        try {
            if (editingOption) {
                await http.put(`${endpoints.dropdownOptions}/${editingOption.id}`, { ...values, status: values.status ? 1 : 0 });
                notification.success({ message: 'Option updated' });
            } else {
                await http.post(endpoints.dropdownOptions, { ...values, status: values.status ? 1 : 0 });
                notification.success({ message: 'Option added' });
            }
            setDropdownModalOpen(false);
            loadDropdownOptions();
        } catch (error) {
            dropdownForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function deleteDropdownOption(option) {
        try {
            await http.delete(`${endpoints.dropdownOptions}/${option.id}`);
            notification.success({ message: 'Option deleted' });
            loadDropdownOptions();
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Delete failed' });
        }
    }

    async function loadPartyTypes() {
        const { data } = await http.get(endpoints.partyTypes);
        setPartyTypes(data.data || []);
    }

    function openPartyType(pt = null) {
        setEditingPartyType(pt);
        partyTypeForm.resetFields();
        partyTypeForm.setFieldsValue(pt || {});
        setPartyTypeModalOpen(true);
    }

    async function savePartyType(values) {
        try {
            if (editingPartyType) {
                await http.put(`${endpoints.partyTypes}/${editingPartyType.id}`, values);
                notification.success({ message: 'Party type updated' });
            } else {
                await http.post(endpoints.partyTypes, values);
                notification.success({ message: 'Party type created' });
            }
            setPartyTypeModalOpen(false);
            loadPartyTypes();
        } catch (error) {
            partyTypeForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Save failed' });
        }
    }

    async function deletePartyType(pt) {
        try {
            await http.delete(`${endpoints.partyTypes}/${pt.id}`);
            notification.success({ message: 'Party type deleted' });
            loadPartyTypes();
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Delete failed' });
        }
    }

    async function loadSupplierTypes() {
        const { data } = await http.get(endpoints.supplierTypes);
        setSupplierTypes(data.data || []);
    }

    function openSupplierType(st = null) {
        setEditingSupplierType(st);
        supplierTypeForm.resetFields();
        supplierTypeForm.setFieldsValue(st || {});
        setSupplierTypeModalOpen(true);
    }

    async function saveSupplierType(values) {
        try {
            if (editingSupplierType) {
                await http.put(`${endpoints.supplierTypes}/${editingSupplierType.id}`, values);
                notification.success({ message: 'Supplier type updated' });
            } else {
                await http.post(endpoints.supplierTypes, values);
                notification.success({ message: 'Supplier type created' });
            }
            setSupplierTypeModalOpen(false);
            loadSupplierTypes();
        } catch (error) {
            supplierTypeForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Save failed' });
        }
    }

    async function deleteSupplierType(st) {
        try {
            await http.delete(`${endpoints.supplierTypes}/${st.id}`);
            notification.success({ message: 'Supplier type deleted' });
            loadSupplierTypes();
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Delete failed' });
        }
    }

    const filteredOptions = useMemo(() => dropdownOptions.filter((o) => o.alias === dropdownAlias), [dropdownOptions, dropdownAlias]);

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
            await http.post(endpoints.branding, brandingPayload(values));
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
                            <div className="branding-upload-grid">
                                {[
                                    ['logo_upload', 'Main Logo', branding?.logo_url],
                                    ['sidebar_logo_upload', 'Sidebar Logo', branding?.sidebar_logo_url],
                                    ['app_icon_upload', 'App Icon', branding?.app_icon_url],
                                    ['favicon_upload', 'Favicon', branding?.favicon_url],
                                ].map(([name, label, url]) => (
                                    <Form.Item key={name} name={name} label={label} valuePropName="fileList" getValueFromEvent={normalizeFile}>
                                        <Upload beforeUpload={() => false} maxCount={1} accept={name === 'favicon_upload' ? '.ico,image/*' : 'image/*'} listType="picture">
                                            <Button icon={<UploadOutlined />}>{url ? 'Replace File' : 'Upload File'}</Button>
                                        </Upload>
                                        {url && (
                                            <div className="brand-upload-current">
                                                <img src={url} alt="" />
                                                <span>Current file</span>
                                            </div>
                                        )}
                                    </Form.Item>
                                ))}
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

        // --- General Settings tab ---
        items.push({
            key: 'general',
            label: 'General Settings',
            children: (
                <Card title="Company & SMTP Configuration" loading={adminSettingsLoading}>
                    <Form form={adminForm} layout="vertical" onFinish={saveAdminSettings}>
                        <div className="form-grid">
                            <Form.Item name="company_email" label="Company Email"><Input /></Form.Item>
                            <Form.Item name="company_phone" label="Company Phone"><Input /></Form.Item>
                        </div>
                        <Form.Item name="company_address" label="Company Address"><Input.TextArea rows={2} /></Form.Item>
                        <div className="form-grid">
                            <Form.Item name="currency_symbol" label="Currency Symbol"><Input /></Form.Item>
                            <Form.Item name="low_stock_threshold" label="Low Stock Threshold"><InputNumber min={1} className="full-width" /></Form.Item>
                        </div>
                        <Card size="small" title="SMTP / Mail Settings" style={{ marginBottom: 16 }}>
                            <div className="form-grid">
                                <Form.Item name="smtp_host" label="SMTP Host"><Input /></Form.Item>
                                <Form.Item name="smtp_port" label="SMTP Port"><Input /></Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="smtp_username" label="Username"><Input /></Form.Item>
                                <Form.Item name="smtp_password" label="Password"><Input.Password /></Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="smtp_encryption" label="Encryption"><Select allowClear options={[{ value: 'tls', label: 'TLS' }, { value: 'ssl', label: 'SSL' }]} /></Form.Item>
                                <Form.Item name="mail_from_address" label="From Address"><Input /></Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="mail_from_name" label="From Name"><Input /></Form.Item>
                                <Form.Item name="notification_email" label="Notification Email"><Input /></Form.Item>
                            </div>
                            <Button icon={<SendOutlined />} onClick={sendTestMail}>Send Test Mail</Button>
                        </Card>
                        <Button type="primary" htmlType="submit">Save Settings</Button>
                    </Form>
                </Card>
            ),
        });

        // --- Dropdown Options tab ---
        items.push({
            key: 'dropdown-options',
            label: 'Dropdown Options',
            children: (
                <div className="page-stack">
                    <Card
                        title="Shared Dropdown Options"
                        extra={
                            <Space>
                                <Select value={dropdownAlias} onChange={setDropdownAlias} style={{ width: 200 }} options={Object.entries(dropdownAliases).map(([key, meta]) => ({ value: key, label: meta.label }))} />
                                <Button type="primary" icon={<PlusOutlined />} onClick={() => openDropdownOption()}>Add Option</Button>
                            </Space>
                        }
                    >
                        <Table
                            rowKey="id"
                            dataSource={filteredOptions}
                            pagination={false}
                            columns={[
                                { title: 'Name', dataIndex: 'name' },
                                { title: 'Data', dataIndex: 'data', render: (v) => v || '-' },
                                { title: 'Status', dataIndex: 'is_active', width: 100, render: (v) => <Badge status={v ? 'success' : 'default'} text={v ? 'Active' : 'Inactive'} /> },
                                {
                                    title: '', width: 112, render: (_, record) => (
                                        <Space>
                                            <Button icon={<EditOutlined />} onClick={() => openDropdownOption(record)} />
                                            <Popconfirm title="Delete this option?" onConfirm={() => deleteDropdownOption(record)} okText="Delete" okType="danger"><Button danger icon={<DeleteOutlined />} /></Popconfirm>
                                        </Space>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                </div>
            ),
        });

        // --- Party Types tab ---
        items.push({
            key: 'party-types',
            label: 'Party Types',
            children: (
                <Card title="Party Types" extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openPartyType()}>New Party Type</Button>}>
                    <Table
                        rowKey="id"
                        dataSource={partyTypes}
                        pagination={false}
                        columns={[
                            { title: 'Name', dataIndex: 'name' },
                            { title: 'Code', dataIndex: 'code', render: (v) => v || '-' },
                            {
                                title: '', width: 112, render: (_, record) => (
                                    <Space>
                                        <Button icon={<EditOutlined />} onClick={() => openPartyType(record)} />
                                        <Popconfirm title="Delete?" onConfirm={() => deletePartyType(record)} okText="Delete" okType="danger"><Button danger icon={<DeleteOutlined />} /></Popconfirm>
                                    </Space>
                                ),
                            },
                        ]}
                    />
                </Card>
            ),
        });

        // --- Supplier Types tab ---
        items.push({
            key: 'supplier-types',
            label: 'Supplier Types',
            children: (
                <Card title="Supplier Types" extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openSupplierType()}>New Supplier Type</Button>}>
                    <Table
                        rowKey="id"
                        dataSource={supplierTypes}
                        pagination={false}
                        columns={[
                            { title: 'Name', dataIndex: 'name' },
                            { title: 'Code', dataIndex: 'code', render: (v) => v || '-' },
                            {
                                title: '', width: 112, render: (_, record) => (
                                    <Space>
                                        <Button icon={<EditOutlined />} onClick={() => openSupplierType(record)} />
                                        <Popconfirm title="Delete?" onConfirm={() => deleteSupplierType(record)} okText="Delete" okType="danger"><Button danger icon={<DeleteOutlined />} /></Popconfirm>
                                    </Space>
                                ),
                            },
                        ]}
                    />
                </Card>
            ),
        });

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
    }, [branding, brandingForm, featureRows, profileForm, roleData, user, userLookups, userTable, adminSettings, adminSettingsLoading, dropdownOptions, dropdownAliases, dropdownAlias, filteredOptions, partyTypes, supplierTypes]);

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

            <Modal
                title={editingOption ? 'Edit Option' : 'New Option'}
                open={dropdownModalOpen}
                onCancel={() => setDropdownModalOpen(false)}
                onOk={() => dropdownForm.submit()}
                destroyOnHidden
            >
                <Form form={dropdownForm} layout="vertical" onFinish={saveDropdownOption}>
                    <Form.Item name="alias" label="Alias" rules={[{ required: true }]}>
                        <Select options={Object.entries(dropdownAliases).map(([key, meta]) => ({ value: key, label: meta.label }))} />
                    </Form.Item>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="data" label="Data (optional)"><Input /></Form.Item>
                    <Form.Item name="status" valuePropName="checked" label="Active"><Switch /></Form.Item>
                </Form>
            </Modal>

            <Modal
                title={editingPartyType ? 'Edit Party Type' : 'New Party Type'}
                open={partyTypeModalOpen}
                onCancel={() => setPartyTypeModalOpen(false)}
                onOk={() => partyTypeForm.submit()}
                destroyOnHidden
            >
                <Form form={partyTypeForm} layout="vertical" onFinish={savePartyType}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="code" label="Code"><Input /></Form.Item>
                </Form>
            </Modal>

            <Modal
                title={editingSupplierType ? 'Edit Supplier Type' : 'New Supplier Type'}
                open={supplierTypeModalOpen}
                onCancel={() => setSupplierTypeModalOpen(false)}
                onOk={() => supplierTypeForm.submit()}
                destroyOnHidden
            >
                <Form form={supplierTypeForm} layout="vertical" onFinish={saveSupplierType}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="code" label="Code"><Input /></Form.Item>
                </Form>
            </Modal>
        </div>
    );
}
