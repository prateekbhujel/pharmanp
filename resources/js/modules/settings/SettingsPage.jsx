import React, { useEffect, useState } from 'react';
import {
    ApartmentOutlined,
    CalendarOutlined,
    IdcardOutlined,
    MailOutlined,
    NumberOutlined,
    ShopOutlined,
    UserOutlined,
} from '@ant-design/icons';
import { App, Card, Form, Space, Typography } from 'antd';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { useAuth } from '../../core/auth/AuthProvider';
import { useBranding } from '../../core/context/BrandingContext';
import { FiscalYearPanel } from './FiscalYearPanel';
import { SettingsProfilePanel } from './SettingsProfilePanel';
import { SettingsBrandingPanel } from './SettingsBrandingPanel';
import { SettingsCompanyPanel } from './SettingsCompanyPanel';
import { SettingsNumberingPanel } from './SettingsNumberingPanel';
import { SettingsMailPanel } from './SettingsMailPanel';

const { Text } = Typography;

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
    const { reload: reloadAuth } = useAuth();
    const { branding, loading: brandingLoading, reload: reloadBranding } = useBranding();
    const { data: profile, reload: reloadProfile } = useApi(endpoints.profile);

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
        loadAdminSettings();
    // eslint-disable-next-line react-hooks/exhaustive-deps
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

    function selectSettingsSection(sectionId) {
        setActiveSection(sectionId);
        window.history.replaceState({}, '', `${window.location.pathname}${window.location.search}#${sectionId}`);
    }

    return (
        <div className="page-stack settings-page">
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
                            <SettingsProfilePanel
                                profile={profile}
                                reloadProfile={reloadProfile}
                                reloadAuth={reloadAuth}
                            />
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-branding' ? (
                        <SettingsSection
                            id="settings-branding"
                            title="Branding"
                            icon={<IdcardOutlined />}
                            description="Application name, calendar preference and uploaded brand assets."
                        >
                            <SettingsBrandingPanel
                                branding={branding}
                                brandingLoading={brandingLoading}
                                reloadBranding={reloadBranding}
                                reloadAuth={reloadAuth}
                            />
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-company' ? (
                        <SettingsSection
                            id="settings-company"
                            title="Company Details"
                            icon={<ShopOutlined />}
                            description="Contact details used on invoices, reports, notifications and stock alerts."
                        >
                            <SettingsCompanyPanel
                                form={companyForm}
                                loading={adminSettingsLoading}
                                onSave={saveAdminSection}
                            />
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-numbering' ? (
                        <SettingsSection
                            id="settings-numbering"
                            title="Document Numbering"
                            icon={<NumberOutlined />}
                            description="Invoice, bill, purchase order and voucher code rules."
                        >
                            <SettingsNumberingPanel
                                form={numberingForm}
                                loading={adminSettingsLoading}
                                onSave={saveAdminSection}
                            />
                        </SettingsSection>
                    ) : null}

                    {activeSection === 'settings-mail' ? (
                        <SettingsSection
                            id="settings-mail"
                            title="SMTP & Notifications"
                            icon={<MailOutlined />}
                            description="Mail server and notification sender used by system alerts."
                        >
                            <SettingsMailPanel
                                form={mailForm}
                                loading={adminSettingsLoading}
                                smtpPasswordSet={smtpPasswordSet}
                                onSave={saveAdminSection}
                            />
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
