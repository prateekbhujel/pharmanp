import React, { useEffect, useMemo, useState } from 'react';
import { PlusOutlined, SendOutlined, UserOutlined } from '@ant-design/icons';
import { App, Button, Card, Form, Input, InputNumber, Segmented, Select, Space, Table, Tabs, Tag, Upload } from 'antd';
import { countryOptions, countries } from '../../core/utils/countries';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { useAuth } from '../../core/auth/AuthProvider';
import { useBranding } from '../../core/context/BrandingContext';
import { FiscalYearPanel } from './FiscalYearPanel';

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

function BrandingUploadField({ form, name, label, url, hint }) {
    const fileList = Form.useWatch(name, form) || [];
    const selectedFileName = fileList?.[0]?.name;

    return (
        <div className="branding-box">
            <div style={{ marginBottom: 8, fontSize: 13, fontWeight: 600 }}>{label}</div>
            <Form.Item name={name} valuePropName="fileList" getValueFromEvent={normalizeFile} noStyle>
                <Upload
                    beforeUpload={() => false}
                    maxCount={1}
                    accept={name === 'favicon_upload' ? '.ico,image/*' : 'image/*'}
                    showUploadList={false}
                >
                    <div className="smart-image-upload-wrapper">
                        {url ? (
                            <>
                                <img src={url} alt="preview" className="smart-image-preview" />
                                <div className="smart-image-overlay">
                                    <PlusOutlined />
                                    <span>Change Asset</span>
                                </div>
                            </>
                        ) : (
                            <div className="smart-image-placeholder">
                                <PlusOutlined />
                                <span>Upload</span>
                            </div>
                        )}
                    </div>
                </Upload>
            </Form.Item>
            {selectedFileName && <div className="upload-file-name" title={selectedFileName}>{selectedFileName}</div>}
            <div style={{ marginTop: 8, fontSize: 11, color: '#94a3b8' }}>{hint}</div>
        </div>
    );
}

export function SettingsPage() {
    const { notification } = App.useApp();
    const { user } = useAuth();
    const { data: features } = useApi(endpoints.featureCatalog);
    const { data: branding, reload: reloadBranding } = useBranding();

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
            adminForm.setFieldsValue(data.data || {});
        } catch (e) {
            // Silently handle — form stays empty
        }
        setAdminSettingsLoading(false);
    }

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
            data.map(feature => ({ ...feature, module }))
        );
    }, [features]);

    return (
        <div className="page-stack">
            <PageHeader
                title="Settings"
                actions={<Tag icon={<UserOutlined />}>{user?.name}</Tag>}
            />

            <Tabs items={[
                {
                    key: 'branding',
                    label: 'Branding',
                    children: (
                        <Card title="Application Identity" description="Customize how your pharmacy app looks to staff and customers">
                            <Form form={brandingForm} layout="vertical" onFinish={saveBranding}>
                                <div className="form-grid">
                                    <Form.Item name="app_name" label="Pharmacy Name" rules={[{ required: true }]}><Input size="large" /></Form.Item>
                                    <Form.Item name="layout" label="Navigation Layout" rules={[{ required: true }]}>
                                        <Select size="large" options={[
                                            { value: 'vertical', label: 'Vertical Sidebar (Modern)' },
                                            { value: 'horizontal', label: 'Horizontal Menu (Traditional)' },
                                        ]} />
                                    </Form.Item>
                                    <Form.Item name="country_code" label="Country" rules={[{ required: true }]}>
                                        <Select 
                                            size="large" 
                                            showSearch 
                                            optionFilterProp="label" 
                                            options={countryOptions} 
                                            onChange={(code) => {
                                                const country = countries.find(c => c.code === code);
                                                if (country) {
                                                    brandingForm.setFieldValue('currency_symbol', country.symbol);
                                                }
                                            }}
                                        />
                                    </Form.Item>
                                    <Form.Item name="currency_symbol" label="Currency Symbol" rules={[{ required: true }]}>
                                        <Input size="large" placeholder="e.g. Rs. or $" />
                                    </Form.Item>
                                    <Form.Item name="calendar_type" label="Preferred Calendar System" rules={[{ required: true }]}>
                                        <Segmented 
                                            block 
                                            size="large"
                                            options={[
                                                { label: 'AD (Gregorian)', value: 'ad' },
                                                { label: 'BS (Nepali)', value: 'bs' },
                                            ]} 
                                        />
                                    </Form.Item>
                                </div>
                                <div className="branding-upload-grid">
                                    {[
                                        ['logo_upload', 'Main Logo', branding?.logo_url, 'Best for light headers'],
                                        ['sidebar_logo_upload', 'Sidebar Logo', branding?.sidebar_logo_url, 'Compact sidebar icon'],
                                        ['app_icon_upload', 'App Icon', branding?.app_icon_url, 'Desktop/Mobile icon'],
                                        ['favicon_upload', 'Favicon', branding?.favicon_url, 'Browser tab icon'],
                                    ].map(([name, label, url, hint]) => <BrandingUploadField key={name} form={brandingForm} name={name} label={label} url={url} hint={hint} />)}
                                </div>
                                <Space>
                                    <Button type="primary" size="large" htmlType="submit">Apply Branding</Button>
                                    <Button size="large" onClick={() => reloadBranding()}>Discard Changes</Button>
                                </Space>
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
                    key: 'fiscal-years',
                    label: 'Fiscal Years',
                    children: <FiscalYearPanel />
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
