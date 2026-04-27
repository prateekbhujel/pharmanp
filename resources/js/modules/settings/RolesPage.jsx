import React, { useState } from 'react';
import { App, Button, Card, Checkbox, Form, Input, Modal, Space, Table, Tag } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';

function permissionLabel(permission) {
    const [, resource = permission, action = 'access'] = permission.split('.');
    const resourceLabel = resource.replaceAll('_', ' ').replaceAll('-', ' ');
    const actionLabel = {
        view: 'View',
        create: 'Create',
        update: 'Edit',
        delete: 'Delete',
        manage: 'Manage',
        use: 'Use',
        preview: 'Preview',
        commit: 'Commit',
        download: 'Download',
    }[action] || action.replaceAll('_', ' ');

    return `${actionLabel} ${resourceLabel}`;
}

export function RolesPage() {
    const { notification } = App.useApp();
    const { data: roleData, reload: reloadRoles } = useApi(endpoints.roles);
    const [roleModalOpen, setRoleModalOpen] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [permissionSearch, setPermissionSearch] = useState('');
    const [selectedPermissions, setSelectedPermissions] = useState([]);
    const [roleForm] = Form.useForm();

    const permissionGroups = roleData?.permission_groups || {};

    const openRole = (record = null) => {
        setEditingRole(record);
        roleForm.resetFields();
        setPermissionSearch('');
        setSelectedPermissions(record?.permissions || []);
        if (record) {
            roleForm.setFieldsValue(record);
        } else {
            roleForm.setFieldsValue({ permissions: [] });
        }
        setRoleModalOpen(true);
    };

    const saveRole = async (values) => {
        const payload = { ...values, permissions: selectedPermissions };
        if (editingRole) {
            await http.put(endpoints.roles + '/' + editingRole.id, payload);
            notification.success({ message: 'Role updated' });
        } else {
            await http.post(endpoints.roles, payload);
            notification.success({ message: 'Role created' });
        }
        setRoleModalOpen(false);
        reloadRoles();
    };

    const deleteRole = async (record) => {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'Users assigned to this role will lose the permissions attached to it.',
            onOk: async () => {
                await http.delete(endpoints.roles + '/' + record.id);
                notification.success({ message: 'Role deleted' });
                reloadRoles();
            },
        });
    };

    const setPermissions = (permissions) => {
        const unique = [...new Set(permissions)].sort();
        setSelectedPermissions(unique);
        roleForm.setFieldValue('permissions', unique);
    };

    const togglePermission = (permission, checked) => {
        setPermissions(checked
            ? [...selectedPermissions, permission]
            : selectedPermissions.filter((item) => item !== permission));
    };

    const toggleGroup = (permissions, checked) => {
        setPermissions(checked
            ? [...selectedPermissions, ...permissions]
            : selectedPermissions.filter((item) => !permissions.includes(item)));
    };

    const visiblePermissionGroups = Object.entries(permissionGroups)
        .map(([group, permissions]) => {
            const filtered = permissions.filter((permission) => {
                const needle = permissionSearch.trim().toLowerCase();
                if (!needle) return true;

                return group.toLowerCase().includes(needle)
                    || permission.toLowerCase().includes(needle)
                    || permissionLabel(permission).toLowerCase().includes(needle);
            });

            return [group, filtered];
        })
        .filter(([, permissions]) => permissions.length > 0);

    return (
        <div className="page-stack">
            <PageHeader
                title="Roles & Permissions"
                actions={<Button type="primary" icon={<PlusOutlined />} onClick={() => openRole()}>New Role</Button>}
            />

            <Card title="Roles">
                <Table
                    rowKey="id"
                    dataSource={roleData?.roles || []}
                    pagination={false}
                    columns={[
                        { title: 'Role', dataIndex: 'name', width: 200 },
                        {
                            title: 'Permissions', dataIndex: 'permissions', render: (permissions) => (
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                                    {(permissions || []).slice(0, 10).map((name) => <Tag key={name}>{permissionLabel(name)}</Tag>)}
                                    {(permissions?.length || 0) > 10 && <Tag>+{permissions.length - 10} more</Tag>}
                                </div>
                            )
                        },
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

            <Modal
                title={editingRole ? `Edit Role: ${editingRole.name}` : 'New Role'}
                open={roleModalOpen}
                onCancel={() => setRoleModalOpen(false)}
                onOk={() => roleForm.submit()}
                width={1040}
                destroyOnHidden
            >
                <Form form={roleForm} layout="vertical" onFinish={saveRole}>
                    <Form.Item name="name" label="Role Name" rules={[{ required: true }]}><Input disabled={editingRole?.locked} /></Form.Item>
                    <div className="permission-modal-toolbar">
                        <Input.Search
                            allowClear
                            value={permissionSearch}
                            onChange={(event) => setPermissionSearch(event.target.value)}
                            placeholder="Search permissions by module or action"
                        />
                        <Tag color="processing">{selectedPermissions.length} selected</Tag>
                    </div>
                    <div className="permission-matrix">
                        {visiblePermissionGroups.map(([group, permissions]) => {
                            const checkedCount = permissions.filter((permission) => selectedPermissions.includes(permission)).length;
                            const allChecked = checkedCount === permissions.length;
                            const indeterminate = checkedCount > 0 && !allChecked;

                            return (
                                <Card
                                    key={group}
                                    size="small"
                                    title={group}
                                    extra={
                                        <Checkbox
                                            checked={allChecked}
                                            indeterminate={indeterminate}
                                            onChange={(event) => toggleGroup(permissions, event.target.checked)}
                                        >
                                            All
                                        </Checkbox>
                                    }
                                >
                                    <div className="permission-toggle-grid">
                                        {permissions.map((permission) => (
                                            <Checkbox
                                                key={permission}
                                                checked={selectedPermissions.includes(permission)}
                                                onChange={(event) => togglePermission(permission, event.target.checked)}
                                            >
                                                <span>{permissionLabel(permission)}</span>
                                                <small>{permission}</small>
                                            </Checkbox>
                                        ))}
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                </Form>
            </Modal>
        </div>
    );
}
