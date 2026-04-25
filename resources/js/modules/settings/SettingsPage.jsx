import React, { useState } from 'react';
import { App, Button, Card, DatePicker, Form, Input, Select, Space, Table, Tabs, Tag } from 'antd';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';

export function SettingsPage() {
    const { notification } = App.useApp();
    const { data: features } = useApi(endpoints.featureCatalog);
    const { data: invites, reload } = useApi(endpoints.setupInvites);
    const { data: roleData, reload: reloadRoles } = useApi(endpoints.roles);
    const [inviteResult, setInviteResult] = useState(null);
    const [form] = Form.useForm();
    const [roleForm] = Form.useForm();

    async function createInvite(values) {
        try {
            const payload = {
                ...values,
                expires_on: values.expires_on?.format('YYYY-MM-DD'),
            };
            const { data } = await http.post(endpoints.setupInvites, payload);
            setInviteResult(data.data);
            notification.success({ message: 'Setup link generated' });
            form.resetFields();
            reload?.();
        } catch (error) {
            const errors = validationErrors(error);
            form.setFields(Object.entries(errors).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Invite failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function revoke(record) {
        await http.post(`${endpoints.setupInvites}/${record.id}/revoke`);
        notification.warning({ message: 'Setup link revoked' });
        reload?.();
    }

    const featureOptions = Object.values(features || {})
        .flat()
        .map((item) => ({ value: item.code, label: item.name }));

    async function saveRole(values) {
        try {
            await http.post(endpoints.roles, values);
            notification.success({ message: 'Role saved' });
            roleForm.resetFields();
            reloadRoles?.();
        } catch (error) {
            roleForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Role failed', description: error?.response?.data?.message || error.message });
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                title="Settings and Provisioning"
                description="Branding, fiscal year, roles and client setup links belong here as the system grows"
            />

            <Tabs items={[
                {
                    key: 'provisioning',
                    label: 'Provisioning',
                    children: (
                        <div className="page-stack">
                            <Card title="Generate Client Setup / Demo Link">
                                <Form form={form} layout="vertical" onFinish={createInvite} initialValues={{ expires_on: dayjs().add(7, 'day') }}>
                                    <div className="form-grid">
                                        <Form.Item name="client_name" label="Client Name"><Input /></Form.Item>
                                        <Form.Item name="client_email" label="Client Email"><Input /></Form.Item>
                                    </div>
                                    <Form.Item name="requested_features" label="Requested Features">
                                        <Select mode="multiple" options={featureOptions} placeholder="Select modules this client wants" />
                                    </Form.Item>
                                    <Form.Item name="expires_on" label="Expires On">
                                        <DatePicker className="full-width" />
                                    </Form.Item>
                                    <Button type="primary" htmlType="submit">Generate Setup Link</Button>
                                </Form>
                                {inviteResult && (
                                    <div className="setup-link-result">
                                        <strong>One-time setup URL</strong>
                                        <code>{inviteResult.setup_url}</code>
                                    </div>
                                )}
                            </Card>

                            <Card title="Recent Setup Links">
                                <Table
                                    rowKey="id"
                                    dataSource={invites || []}
                                    pagination={false}
                                    columns={[
                                        { title: 'Client', dataIndex: 'client_name', render: (value) => value || 'Unnamed' },
                                        { title: 'Email', dataIndex: 'client_email' },
                                        { title: 'Status', dataIndex: 'status', render: (value) => <Tag color={value === 'active' ? 'green' : 'default'}>{value}</Tag> },
                                        { title: 'Expires', dataIndex: 'expires_on' },
                                        { title: '', align: 'right', render: (_, record) => <Space><Button disabled={record.status !== 'active'} onClick={() => revoke(record)}>Revoke</Button></Space> },
                                    ]}
                                />
                            </Card>
                        </div>
                    ),
                },
                {
                    key: 'roles',
                    label: 'Roles / Permissions',
                    children: (
                        <div className="page-stack">
                            <Card title="Create Role">
                                <Form form={roleForm} layout="vertical" onFinish={saveRole}>
                                    <Form.Item name="name" label="Role Name" rules={[{ required: true }]}><Input /></Form.Item>
                                    <Form.Item name="permissions" label="Permissions">
                                        <Select mode="multiple" options={(roleData?.permissions || []).map((name) => ({ value: name, label: name }))} />
                                    </Form.Item>
                                    <Button type="primary" htmlType="submit">Save Role</Button>
                                </Form>
                            </Card>
                            <Card title="Roles">
                                <Table
                                    rowKey="id"
                                    dataSource={roleData?.roles || []}
                                    pagination={false}
                                    columns={[
                                        { title: 'Role', dataIndex: 'name' },
                                        { title: 'Permissions', dataIndex: 'permissions', render: (permissions) => permissions?.map((name) => <Tag key={name}>{name}</Tag>) },
                                    ]}
                                />
                            </Card>
                        </div>
                    ),
                },
            ]} />
        </div>
    );
}
