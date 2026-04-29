import React, { useEffect, useState } from 'react';
import {
    ApartmentOutlined,
    CalendarOutlined,
    IdcardOutlined,
    MailOutlined,
    NumberOutlined,
    SendOutlined,
    ShopOutlined,
    UserOutlined,
} from '@ant-design/icons';
import { App, Button, Card, Form, Input, InputNumber, Segmented, Select, Space, Typography } from 'antd';
import { BrandAssetUploadField } from '../../core/components/BrandAssetUploadField';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { useAuth } from '../../core/auth/AuthProvider';
import { useBranding } from '../../core/context/BrandingContext';
import { countryOptions, countries } from '../../core/utils/countries';
import { FiscalYearPanel } from './FiscalYearPanel';

const { Text } = Typography;

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

const settingsNavigation = [
    { id: 'settings-profile', label: 'Profile', icon: <UserOutlined /> },
    { id: 'settings-branding', label: 'Branding', icon: <IdcardOutlined /> },
    { id: 'settings-company', label: 'Company Details', icon: <ShopOutlined /> },
    { id: 'settings-numbering', label: 'Document Numbering', icon: <NumberOutlined /> },
    { id: 'settings-mail', label: 'SMTP & Notifications', icon: <MailOutlined /> },
    { id: 'settings-fiscal-years', label: 'Fiscal Years', icon: <CalendarOutlined /> },
];

const defaultSettingsSection = 'settings-profile';

function currentSettingsSection() {
    if (typeof window === 'undefined') {
        return defaultSettingsSection;
    }

    const hashSection = window.location.hash.replace('#', '');

    return settingsNavigation.some((item) => item.id === hashSection) ? hashSection : defaultSettingsSection;
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

function fieldErrors(error) {
    return Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages }));
}

function SettingsSection({ id, title, description, icon, children }) {
    return (
        <section data-settings-section={id} className="settings-section">
            <Card
                title={(
                    <Space size={10}>
                        <span className="settings-section-icon">{icon}</span>
                        <span>{title}</span>
                    </Space>
                )}
            >
                {description ? <Text className="settings-section-description">{description}</Text> : null}
                <div className="settings-section-body">
                    {children}
                </div>
            </Card>
        </section>
    );
}

export function SettingsPage() {
    const { notification } = App.useApp();
    const { user, reload: reloadAuth } = useAuth();
    const { branding, loading: brandingLoading, reload: reloadBranding } = useBranding();
    const { data: profile, reload: reloadProfile } = useApi(endpoints.profile);

    const [profileForm] = Form.useForm();
    const [brandingForm] = Form.useForm();
    const [companyForm] = Form.useForm();
    const [numberingForm] = Form.useForm();
    const [mailForm] = Form.useForm();
    const [adminSettingsLoading, setAdminSettingsLoading] = useState(false);
    const [smtpPasswordSet, setSmtpPasswordSet] = useState(false);
    const [activeSection, setActiveSection] = useState(currentSettingsSection);

    useEffect(() => {
        const syncHashSection = () => setActiveSection(currentSettingsSection());

        window.addEventListener('hashchange', syncHashSection);

        return () => window.removeEventListener('hashchange', syncHashSection);
    }, []);

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
            const settings = data.data || {};

            companyForm.setFieldsValue({
                company_email: settings.company_email,
                company_phone: settings.company_phone,
                company_address: settings.company_address,
                low_stock_threshold: settings.low_stock_threshold,
            });
            numberingForm.setFieldsValue({
                document_numbering: settings.document_numbering,
            });
            mailForm.setFieldsValue({
                smtp_host: settings.smtp_host,
                smtp_port: settings.smtp_port,
                smtp_username: settings.smtp_username,
                smtp_password: '',
                smtp_encryption: settings.smtp_encryption,
                mail_from_address: settings.mail_from_address,
                mail_from_name: settings.mail_from_name,
                notification_email: settings.notification_email,
            });
            setSmtpPasswordSet(Boolean(settings.smtp_password_set));
        } catch {
            // Settings can be empty on a fresh local install.
        } finally {
            setAdminSettingsLoading(false);
        }
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
            profileForm.setFields(fieldErrors(error));
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
            brandingForm.setFields(fieldErrors(error));
            notification.error({ message: 'Branding save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function saveAdminSection(form, values, message) {
        try {
            await http.put(endpoints.settingsAdmin, values);
            notification.success({ message });
            loadAdminSettings();
            reloadBranding?.();
        } catch (error) {
            form.setFields(fieldErrors(error));
            notification.error({ message: 'Configuration save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function sendTestMail() {
        try {
            const { data } = await http.post(endpoints.settingsTestMail, {
                email: mailForm.getFieldValue('notification_email') || mailForm.getFieldValue('mail_from_address'),
            });
            notification.success({ message: data.message || 'Test email sent' });
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Test mail failed' });
        }
    }

    function selectSettingsSection(sectionId) {
        setActiveSection(sectionId);
        window.history.replaceState({}, '', `${window.location.pathname}${window.location.search}#${sectionId}`);
    }

    return (
        <div className="page-stack settings-page">
            <PageHeader
                title="Settings"
                description="Company identity, fiscal year, mail delivery and operating controls."
                actions={<PharmaBadge tone="info" icon={<UserOutlined />}>{user?.name}</PharmaBadge>}
            />

            <div className="settings-layout">
                <aside className="settings-sidebar">
                    <div className="settings-sidebar-title">Configuration</div>
                    <nav className="settings-nav">
                        {settingsNavigation.map((item) => (
                            <a
                                key={item.id}
                                href={`#${item.id}`}
                                className={`settings-nav-item ${activeSection === item.id ? 'settings-nav-item-active' : ''}`}
                                aria-current={activeSection === item.id ? 'page' : undefined}
                                onClick={(event) => {
                                    event.preventDefault();
                                    selectSettingsSection(item.id);
                                }}
                            >
                                {item.icon}
                                <span>{item.label}</span>
                            </a>
                        ))}
                    </nav>
                </aside>

                <div className="settings-content">
                    {activeSection === 'settings-profile' ? (
                        <SettingsSection
                        id="settings-profile"
                        title="My Profile"
                        icon={<UserOutlined />}
                        description="Personal account details and password."
                    >
                        <Card title="Personal Access" loading={!profile} className="settings-inner-card">
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
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-branding' ? (
                        <SettingsSection
                        id="settings-branding"
                        title="Branding"
                        icon={<IdcardOutlined />}
                        description="Application name, calendar preference and uploaded brand assets."
                    >
                        <Card title="Application Identity" loading={brandingLoading} className="settings-inner-card">
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
                                <Space wrap>
                                    <Button type="primary" size="large" htmlType="submit">Apply Branding</Button>
                                    <Button size="large" onClick={() => reloadBranding?.()}>Discard Changes</Button>
                                </Space>
                            </Form>
                        </Card>
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-company' ? (
                        <SettingsSection
                        id="settings-company"
                        title="Company Details"
                        icon={<ShopOutlined />}
                        description="Contact details used on invoices, reports, notifications and stock alerts."
                    >
                        <Card title="Company Contact" loading={adminSettingsLoading} className="settings-inner-card">
                            <Form
                                form={companyForm}
                                layout="vertical"
                                onFinish={(values) => saveAdminSection(companyForm, values, 'Company details saved')}
                            >
                                <div className="form-grid">
                                    <Form.Item name="company_email" label="Company Email" rules={[{ type: 'email' }]}>
                                        <Input size="large" />
                                    </Form.Item>
                                    <Form.Item name="company_phone" label="Company Phone">
                                        <Input size="large" />
                                    </Form.Item>
                                </div>
                                <Form.Item name="company_address" label="Company Address">
                                    <Input.TextArea rows={3} />
                                </Form.Item>
                                <Card size="small" title="Stock Defaults" className="settings-sub-card">
                                    <Form.Item name="low_stock_threshold" label="Low Stock Threshold">
                                        <InputNumber min={1} className="full-width" size="large" />
                                    </Form.Item>
                                </Card>
                                <Button type="primary" htmlType="submit">Save Company Details</Button>
                            </Form>
                        </Card>
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-numbering' ? (
                        <SettingsSection
                        id="settings-numbering"
                        title="Document Numbering"
                        icon={<NumberOutlined />}
                        description="Invoice, bill, purchase order and voucher code rules."
                    >
                        <Card title="Numbering Rules" loading={adminSettingsLoading} className="settings-inner-card">
                            <Form
                                form={numberingForm}
                                layout="vertical"
                                onFinish={(values) => saveAdminSection(numberingForm, values, 'Document numbering saved')}
                            >
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
                                <Button type="primary" htmlType="submit">Save Numbering Rules</Button>
                            </Form>
                        </Card>
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-mail' ? (
                        <SettingsSection
                        id="settings-mail"
                        title="SMTP & Notifications"
                        icon={<MailOutlined />}
                        description="Mail server and notification sender used by system alerts."
                    >
                        <Card title="Mail Delivery" loading={adminSettingsLoading} className="settings-inner-card">
                            <Form
                                form={mailForm}
                                layout="vertical"
                                onFinish={(values) => saveAdminSection(mailForm, values, 'Mail configuration saved')}
                            >
                                <div className="form-grid">
                                    <Form.Item name="smtp_host" label="SMTP Host">
                                        <Input size="large" />
                                    </Form.Item>
                                    <Form.Item name="smtp_port" label="SMTP Port">
                                        <Input size="large" />
                                    </Form.Item>
                                </div>
                                <div className="form-grid">
                                    <Form.Item name="smtp_username" label="Username">
                                        <Input size="large" />
                                    </Form.Item>
                                    <Form.Item
                                        name="smtp_password"
                                        label="Password"
                                        extra={smtpPasswordSet ? 'Password is saved. Type a new one only if you want to replace it.' : undefined}
                                    >
                                        <Input.Password
                                            size="large"
                                            autoComplete="new-password"
                                            placeholder={smtpPasswordSet ? 'Saved password hidden' : undefined}
                                        />
                                    </Form.Item>
                                </div>
                                <div className="form-grid">
                                    <Form.Item name="smtp_encryption" label="Encryption">
                                        <Select
                                            size="large"
                                            allowClear
                                            options={[
                                                { value: 'tls', label: 'TLS' },
                                                { value: 'ssl', label: 'SSL' },
                                            ]}
                                        />
                                    </Form.Item>
                                    <Form.Item name="mail_from_address" label="From Address" rules={[{ type: 'email' }]}>
                                        <Input size="large" />
                                    </Form.Item>
                                </div>
                                <div className="form-grid">
                                    <Form.Item name="mail_from_name" label="From Name">
                                        <Input size="large" />
                                    </Form.Item>
                                    <Form.Item name="notification_email" label="Notification Email" rules={[{ type: 'email' }]}>
                                        <Input size="large" />
                                    </Form.Item>
                                </div>
                                <Space wrap>
                                    <Button type="primary" htmlType="submit">Save Mail Settings</Button>
                                    <Button icon={<SendOutlined />} onClick={sendTestMail}>Send Test Mail</Button>
                                </Space>
                            </Form>
                        </Card>
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-fiscal-years' ? (
                        <SettingsSection
                        id="settings-fiscal-years"
                        title="Fiscal Years"
                        icon={<ApartmentOutlined />}
                        description="Nepali fiscal year windows and active accounting period."
                    >
                        <FiscalYearPanel />
                        </SettingsSection>
                    ) : null}
                </div>
            </div>
        </div>
    );
}
