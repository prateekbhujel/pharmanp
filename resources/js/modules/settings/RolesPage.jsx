import React, { useState } from 'react';
import { Button, Card, Form, Input, Modal, Select, Space, Table, Tag } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, SafetyCertificateOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';

export function RolesPage() {
    const { data: roleData, reload: reloadRoles } = useApi(endpoints.roles);
    const [roleModalOpen, setRoleModalOpen] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [roleForm] = Form.useForm();

    const permissionGroups = roleData?.permission_groups || {};
    const permissionOptions = Object.entries(permissionGroups).flatMap(([group, perms]) => 
        perms.map(p => ({ label: `${group}: ${p}`, value: p }))
    );

    const openRole = (record = null) => {
        setEditingRole(record);
        roleForm.resetFields();
        if (record) {
            roleForm.setFieldsValue(record);
        }
        setRoleModalOpen(true);
    };

    const saveRole = async (values) => {
        if (editingRole) {
            await http.put(endpoints.roles + '/' + editingRole.id, values);
        } else {
            await http.post(endpoints.roles, values);
        }
        setRoleModalOpen(false);
        reloadRoles();
    };

    const deleteRole = async (record) => {
        if (!window.confirm('Delete this role?')) return;
        await http.delete(endpoints.roles + '/' + record.id);
        reloadRoles();
    };

    return (
        <div className="page-stack">
            <PageHeader
                title="Roles & Permissions"
                description="Define access levels and assign permissions to roles"
                actions={<Button type="primary" icon={<PlusOutlined />} onClick={() => openRole()}>New Role</Button>}
            />

            <Card title="Roles">
                <Table
                    rowKey="id"
                    dataSource={roleData?.roles || []}
                    pagination={false}
                    columns={[
                        { title: 'Role', dataIndex: 'name', width: 200 },
                        { title: 'Permissions', dataIndex: 'permissions', render: (permissions) => (
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                                {permissions?.map((name) => <Tag key={name}>{name}</Tag>)}
                            </div>
                        )},
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
                        <Card key={group} size="small" title={group} style={{ height: '100%' }}>
                            <Space wrap>
                                {permissions.map((permission) => <Tag key={permission}>{permission}</Tag>)}
                            </Space>
                        </Card>
                    ))}
                </div>
            </Card>

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
        </div>
    );
}
