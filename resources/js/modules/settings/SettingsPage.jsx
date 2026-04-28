import React, { useEffect, useMemo, useState } from 'react';
import { SendOutlined, UserOutlined } from '@ant-design/icons';
import { App, Button, Card, Form, Input, InputNumber, Segmented, Select, Space, Table, Tabs } from 'antd';
import { BrandAssetUploadField } from '../../core/components/BrandAssetUploadField';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { countryOptions, countries } from '../../core/utils/countries';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { useAuth } from '../../core/auth/AuthProvider';
import { useBranding } from '../../core/context/BrandingContext';
import { FiscalYearPanel } from './FiscalYearPanel';

const documentNumberTypes = [
    ['purchase_order', 'Purchase Order'],
    ['purchase', 'Purchase Bill'],
    ['sales_invoice', 'Sales Invoice'],
    ['voucher', 'Accounting Voucher'],
];

const documentDateFormatOptions = [
    { value: 'Ymd', label: 'Daily: 20260428' },
    { value: 'Ym', label: 'Monthly: 202604' },
    { value: 'Y', label: 'Yearly: 2026' },
    { value: 'none', label: 'No date' },
];

const documentSeparatorOptions = [
    { value: '-', label: 'Dash (-)' },
    { value: '/', label: 'Slash (/)' },
    { value: '.', label: 'Dot (.)' },
    { value: '', label: 'None' },
];

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
    const { user, reload: reloadAuth } = useAuth();
    const { data: features } = useApi(endpoints.featureCatalog);
    const { branding, loading: brandingLoading, reload: reloadBranding } = useBranding();
    const { data: profile, reload: reloadProfile } = useApi(endpoints.profile);

    const [brandingForm] = Form.useForm();
    const [adminForm] = Form.useForm();
    const [profileForm] = Form.useForm();
    const [adminSettingsLoading, setAdminSettingsLoading] = useState(false);

    useEffect(() => {
        if (branding) {
            brandingForm.setFieldsValue(branding);
        }
    }, [branding, brandingForm]);

    useEffect(() => {
        if (profile) {
            profileForm.setFieldsValue({
                name: profile.name,
                email: profile.email,
                phone: profile.phone,
                current_password: '',
                password: '',
                password_confirmation: '',
            });
        }
    }, [profile, profileForm]);

    useEffect(() => {
        loadAdminSettings();
    }, []);

    async function loadAdminSettings() {
        setAdminSettingsLoading(true);
        try {
            const { data } = await http.get(endpoints.settingsAdmin);
            adminForm.setFieldsValue(data.data || {});
        } catch {
            // Leave the form empty if the endpoint is not ready yet.
        }
        setAdminSettingsLoading(false);
    }

    async function saveProfile(values) {
        try {
            await http.put(endpoints.profile, values);
            notification.success({ message: 'Profile updated' });
            reloadProfile?.();
            reloadAuth?.();
            profileForm.setFieldsValue({
                ...values,
                current_password: '',
                password: '',
                password_confirmation: '',
            });
        } catch (error) {
            profileForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Profile update failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function saveBranding(values) {
        try {
            await http.post(endpoints.branding, brandingPayload(values));
            notification.success({ message: 'Application branding saved' });
            reloadBranding?.();
            reloadAuth?.();
        } catch (error) {
            brandingForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Branding save failed' });
        }
    }

    async function saveAdminSettings(values) {
        try {
            await http.put(endpoints.settingsAdmin, values);
            notification.success({ message: 'Configuration saved' });
            loadAdminSettings();
        } catch (error) {
            adminForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Configuration save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function sendTestMail() {
        try {
            const { data } = await http.post(endpoints.settingsTestMail, {
                email: adminForm.getFieldValue('notification_email') || adminForm.getFieldValue('mail_from_address'),
            });
            notification.success({ message: data.message || 'Test email sent' });
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Test mail failed' });
        }
    }

    const featureRows = useMemo(() => {
        if (!features) return [];

        return Object.entries(features).flatMap(([module, data]) =>
            data.map((feature) => ({ ...feature, module })),
        );
    }, [features]);

    return (
        <div className="page-stack">
            <PageHeader
                title="Settings"
                description="Profile, branding, fiscal year and operational configuration."
                actions={<PharmaBadge tone="info" icon={<UserOutlined />}>{user?.name}</PharmaBadge>}
            />

            <Tabs items={[
                {
                    key: 'profile',
                    label: 'My Profile',
                    children: (
                        <Card title="Personal Access" loading={!profile}>
                            <Form form={profileForm} layout="vertical" onFinish={saveProfile}>
                                <div className="form-grid">
                                    <Form.Item name="name" label="Full Name" rules={[{ required: true }]}>
                                        <Input size="large" />
                                    </Form.Item>
                                    <Form.Item name="email" label="Login Email" rules={[{ required: true, type: 'email' }]}>
                                        <Input size="large" />
                                    </Form.Item>
                                </div>
                                <Form.Item name="phone" label="Phone">
                                    <Input size="large" />
                                </Form.Item>
                                <div className="form-grid">
                                    <Form.Item
                                        name="current_password"
                                        label="Current Password"
                                        dependencies={['password']}
                                        rules={[
                                            ({ getFieldValue }) => ({
                                                validator(_, value) {
                                                    if (!getFieldValue('password') || value) {
                                                        return Promise.resolve();
                                                    }

                                                    return Promise.reject(new Error('Current password is required to change password.'));
                                                },
                                            }),
                                        ]}
                                    >
                                        <Input.Password size="large" autoComplete="current-password" />
                                    </Form.Item>
                                    <Form.Item name="password" label="New Password">
                                        <Input.Password size="large" autoComplete="new-password" />
                                    </Form.Item>
                                </div>
                                <Form.Item
                                    name="password_confirmation"
                                    label="Confirm New Password"
                                    dependencies={['password']}
                                    rules={[
                                        ({ getFieldValue }) => ({
                                            validator(_, value) {
                                                if (!getFieldValue('password') || value === getFieldValue('password')) {
                                                    return Promise.resolve();
                                                }

                                                return Promise.reject(new Error('Password confirmation must match the new password.'));
                                            },
                                        }),
                                    ]}
                                >
                                    <Input.Password size="large" autoComplete="new-password" />
                                </Form.Item>
                                <Button type="primary" htmlType="submit">Update Profile</Button>
                            </Form>
                        </Card>
                    ),
                },
                {
                    key: 'branding',
                    label: 'Branding',
                    children: (
                        <Card title="Application Identity" loading={brandingLoading}>
                            <Form form={brandingForm} layout="vertical" onFinish={saveBranding}>
                                <div className="form-grid">
                                    <Form.Item name="app_name" label="Pharmacy Name" rules={[{ required: true }]}>
                                        <Input size="large" />
                                    </Form.Item>
                                    <Form.Item name="country_code" label="Country" rules={[{ required: true }]}>
                                        <Select
                                            size="large"
                                            showSearch
                                            optionFilterProp="label"
                                            options={countryOptions}
                                            onChange={(code) => {
                                                const country = countries.find((item) => item.code === code);
                                                if (!country) return;
                                                brandingForm.setFieldValue('currency_symbol', country.symbol);
                                                brandingForm.setFieldValue('calendar_type', code === 'NP' ? 'bs' : 'ad');
                                            }}
                                        />
                                    </Form.Item>
                                    <Form.Item name="currency_symbol" label="Currency Symbol" rules={[{ required: true }]}>
                                        <Input size="large" placeholder="Rs." />
                                    </Form.Item>
                                    <Form.Item name="calendar_type" label="Preferred Calendar System" rules={[{ required: true }]}>
                                        <Segmented
                                            block
                                            size="large"
                                            options={[
                                                { label: 'Gregorian', value: 'ad' },
                                                { label: 'Nepali', value: 'bs' },
                                            ]}
                                        />
                                    </Form.Item>
                                </div>
                                <div className="branding-upload-grid">
                                    {[
                                        ['logo_upload', 'Main Logo', branding?.logo_url, 'Best for light headers'],
                                        ['sidebar_logo_upload', 'Sidebar Logo', branding?.sidebar_logo_url, 'Compact sidebar icon'],
                                        ['app_icon_upload', 'App Icon', branding?.app_icon_url, 'Desktop and mobile icon'],
                                        ['favicon_upload', 'Favicon', branding?.favicon_url, 'Browser tab icon'],
                                    ].map(([name, label, url, hint]) => (
                                        <BrandAssetUploadField
                                            key={name}
                                            form={brandingForm}
                                            name={name}
                                            label={label}
                                            url={url}
                                            hint={hint}
                                            accept={name === 'favicon_upload' ? '.ico,image/*' : 'image/*'}
                                        />
                                    ))}
                                </div>
                                <Space>
                                    <Button type="primary" size="large" htmlType="submit">Apply Branding</Button>
                                    <Button size="large" onClick={() => reloadBranding?.()}>Discard Changes</Button>
                                </Space>
                            </Form>
                        </Card>
                    ),
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
                                <Form.Item name="low_stock_threshold" label="Low Stock Threshold">
                                    <InputNumber min={1} className="full-width" />
                                </Form.Item>
                                <Card size="small" title="Document Numbering" style={{ marginBottom: 16 }}>
                                    <div className="document-number-grid">
                                        {documentNumberTypes.map(([key, label]) => (
                                            <Card size="small" key={key} title={label} className="document-number-card">
                                                <div className="form-grid">
                                                    <Form.Item name={['document_numbering', key, 'prefix']} label="Prefix">
                                                        <Input maxLength={12} placeholder="PO" />
                                                    </Form.Item>
                                                    <Form.Item name={['document_numbering', key, 'date_format']} label="Date Part">
                                                        <Select options={documentDateFormatOptions} />
                                                    </Form.Item>
                                                </div>
                                                <div className="form-grid">
                                                    <Form.Item name={['document_numbering', key, 'separator']} label="Separator">
                                                        <Select options={documentSeparatorOptions} />
                                                    </Form.Item>
                                                    <Form.Item name={['document_numbering', key, 'padding']} label="Sequence Padding">
                                                        <InputNumber min={1} max={12} className="full-width" />
                                                    </Form.Item>
                                                </div>
                                            </Card>
                                        ))}
                                    </div>
                                </Card>
                                <Card size="small" title="SMTP / Mail Settings" style={{ marginBottom: 16 }}>
                                    <div className="form-grid">
                                        <Form.Item name="smtp_host" label="SMTP Host"><Input /></Form.Item>
                                        <Form.Item name="smtp_port" label="SMTP Port"><Input /></Form.Item>
                                    </div>
                                    <div className="form-grid">
                                        <Form.Item name="smtp_username" label="Username"><Input /></Form.Item>
                                        <Form.Item
                                            name="smtp_password"
                                            label="Password"
                                            extra={adminForm.getFieldValue('smtp_password_set') ? 'Password is saved. Type a new one only if you want to replace it.' : undefined}
                                        >
                                            <Input.Password autoComplete="new-password" placeholder={adminForm.getFieldValue('smtp_password_set') ? 'Saved password hidden' : undefined} />
                                        </Form.Item>
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
                    ),
                },
                {
                    key: 'fiscal-years',
                    label: 'Fiscal Years',
                    children: <FiscalYearPanel />,
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
                                    { title: 'Status', dataIndex: 'status', width: 130, render: (value) => <PharmaBadge tone={value === 'ready' ? 'success' : 'warning'}>{value}</PharmaBadge> },
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
