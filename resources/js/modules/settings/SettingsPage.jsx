import React, { useEffect } from 'react';
import { App, Button, Card, Checkbox, Form, Input, Select, Table, Tabs, Tag } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';

export function SettingsPage() {
    const { notification } = App.useApp();
    const { data: features } = useApi(endpoints.featureCatalog);
    const { data: branding, reload: reloadBranding } = useApi(endpoints.branding);
    const { data: roleData, reload: reloadRoles } = useApi(endpoints.roles);
    const [brandingForm] = Form.useForm();
    const [roleForm] = Form.useForm();

    useEffect(() => {
        if (branding) {
            brandingForm.setFieldsValue(branding);
        }
    }, [branding, brandingForm]);

    async function saveBranding(values) {
        try {
            await http.put(endpoints.branding, values);
            notification.success({ message: 'Application setup saved' });
            reloadBranding?.();
        } catch (error) {
            brandingForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Setup save failed', description: error?.response?.data?.message || error.message });
        }
    }

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

    const featureRows = Object.entries(features || {}).flatMap(([module, items]) => items.map((item) => ({ ...item, module })));

    return (
        <div className="page-stack">
            <PageHeader
                title="Setup"
                description="Standalone application identity, fiscal operating defaults, roles and module checklist"
            />

            <Tabs items={[
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
                {
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
                                    { title: 'Feature', dataIndex: 'name', width: 220 },
                                    { title: 'Status', dataIndex: 'status', width: 130, render: (value) => <Tag color={value === 'foundation' ? 'green' : 'gold'}>{value}</Tag> },
                                    { title: 'Use Case', dataIndex: 'description' },
                                ]}
                            />
                        </Card>
                    ),
                },
            ]} />
        </div>
    );
}
