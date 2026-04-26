import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { App, Badge, Button, Card, Checkbox, ColorPicker, Form, Input, InputNumber, Menu, Modal, Popconfirm, Select, Space, Switch, Table, Tabs, Tag, Upload } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, SendOutlined, UploadOutlined, UserOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { FormDrawer } from '../../core/components/FormDrawer';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
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
            // Handle Ant Design ColorPicker object
            const finalValue = typeof value === 'object' && value?.toHexString ? value.toHexString() : value;
            payload.append(key, finalValue);
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
    
    const [brandingForm] = Form.useForm();
    const [adminForm] = Form.useForm();
    const [adminSettingsLoading, setAdminSettingsLoading] = useState(false);

    useEffect(() => {
        if (branding) {
            brandingForm.setFieldsValue(branding);
        }
    }, [branding, brandingForm]);

    useEffect(() => {
        loadAdminSettings();
    }, []);

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
    async function saveBranding(values) {
        try {
            await http.post(endpoints.branding, brandingPayload(values));
            notification.success({ message: 'Application branding saved' });
            reloadBranding?.();
        } catch (error) {
            brandingForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Branding save failed' });
        }
    }

    async function loadAdminSettings() {
        setAdminSettingsLoading(true);
        try {
            const { data } = await http.get(endpoints.adminSettings);
            adminForm.setFieldsValue(data.data || {});
        } catch (e) { }
        setAdminSettingsLoading(false);
    }

    async function saveAdminSettings(values) {
        try {
            await http.post(endpoints.adminSettings, values);
            notification.success({ message: 'Configuration saved' });
        } catch (e) {
            adminForm.setFields(Object.entries(validationErrors(e)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Configuration save failed' });
        }
    }

    async function sendTestMail() {
        try {
            const values = adminForm.getFieldsValue();
            await http.post(endpoints.sendTestMail, values);
            notification.success({ message: 'Test email sent' });
        } catch (e) {
            notification.error({ message: 'Failed to send test email' });
        }
    }

    const featureRows = useMemo(() => {
        if (!features) return [];
        return Object.entries(features).flatMap(([module, data]) => 
            data.map(feature => ({ ...feature, module }))
        );
    }, [features]);
    return (
        <div className="page-stack">
            <PageHeader
                title="Settings"
                description="Manage application identity and general configuration"
                actions={<Tag icon={<UserOutlined />}>{user?.name}</Tag>}
            />

            <Tabs items={[
                {
                    key: 'branding',
                    label: 'Branding',
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
                                        <div key={name} className="branding-upload-item">
                                            <Form.Item name={name} label={label} valuePropName="fileList" getValueFromEvent={normalizeFile}>
                                                <Upload 
                                                    beforeUpload={() => false} 
                                                    maxCount={1} 
                                                    accept={name === 'favicon_upload' ? '.ico,image/*' : 'image/*'} 
                                                    listType="picture-card"
                                                    className="robust-uploader"
                                                >
                                                    <div>
                                                        <PlusOutlined />
                                                        <div style={{ marginTop: 8 }}>Upload</div>
                                                    </div>
                                                </Upload>
                                            </Form.Item>
                                            {url && (
                                                <div className="brand-upload-current-compact">
                                                    <img src={url} alt="current" />
                                                    <Tag color="blue" bordered={false}>Current Asset</Tag>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                <div className="form-grid">
                                    <Form.Item name="accent_color" label="Accent Color">
                                        <ColorPicker showText format="hex" />
                                    </Form.Item>
                                    <Form.Item name="sidebar_default_collapsed" valuePropName="checked" style={{ paddingTop: 32 }}>
                                        <Checkbox>Start sidebar minimized</Checkbox>
                                    </Form.Item>
                                </div>
                                <Button type="primary" htmlType="submit">Save Branding</Button>
                            </Form>
                        </Card>
                    )
                },
                {
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
                                <Button type="primary" htmlType="submit">Save Configuration</Button>
                            </Form>
                        </Card>
                    )
                },
                {
                    key: 'features',
                    label: 'Feature Roadmap',
                    children: (
                        <Card title="Feature Roadmap">
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
                    )
                }
            ]} />
        </div>
    );
}


