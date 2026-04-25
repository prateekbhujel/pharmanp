import React, { useEffect, useState } from 'react';
import { Alert, App, Button, Card, Checkbox, DatePicker, Form, Input, Select, Steps } from 'antd';
import dayjs from 'dayjs';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';

export function SetupWizard() {
    const { notification } = App.useApp();
    const [status, setStatus] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form] = Form.useForm();

    useEffect(() => {
        http.get(endpoints.setupStatus).then(({ data }) => setStatus(data.data));
    }, []);

    const databaseReady = Boolean(status?.database?.ok);
    const environmentReady = Boolean(status?.environment?.app_key && status?.environment?.storage_writable && status?.environment?.cache_writable);

    async function submit(values) {
        setSaving(true);
        try {
            await http.post(endpoints.setupComplete, {
                ...values,
                fiscal_year: {
                    ...values.fiscal_year,
                    starts_on: values.fiscal_year.starts_on.format('YYYY-MM-DD'),
                    ends_on: values.fiscal_year.ends_on.format('YYYY-MM-DD'),
                },
            });
            notification.success({ message: 'Setup completed' });
            window.location.href = '/login';
        } catch (error) {
            const errors = validationErrors(error);
            form.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Setup failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    return (
        <main className="setup-page">
            <section className="setup-hero">
                <strong>PharmaNP</strong>
                <span>First install wizard for a serious pharmacy ERP/POS rollout</span>
                <Steps
                    current={databaseReady && environmentReady ? 1 : 0}
                    items={[
                        { title: 'Readiness', description: databaseReady ? 'Database connected' : 'Database needs attention' },
                        { title: 'Company / Store' },
                        { title: 'Branding / Fiscal Year' },
                        { title: 'Admin User' },
                    ]}
                />
            </section>

            <Card className="setup-card glass-card" title="Initialize PharmaNP">
                {!databaseReady && (
                    <Alert
                        className="mb-16"
                        type="warning"
                        showIcon
                        message="Database is not ready"
                        description={status?.database?.message || 'Create the database, update .env, then run php artisan migrate before completing setup.'}
                    />
                )}
                {!environmentReady && (
                    <Alert
                        className="mb-16"
                        type="info"
                        showIcon
                        message="Environment checks"
                        description="APP_KEY, storage and cache permissions must be ready before production use."
                    />
                )}

                <Form
                    form={form}
                    layout="vertical"
                    onFinish={submit}
                    initialValues={{
                        company: { name: 'PharmaNP Pharmacy' },
                        store: { name: 'Main Store' },
                        branding: {
                            app_name: 'PharmaNP',
                            accent_color: '#0f766e',
                            layout: 'vertical',
                            sidebar_default_collapsed: false,
                        },
                        fiscal_year: {
                            name: `${dayjs().year()}/${String(dayjs().add(1, 'year').year()).slice(-2)}`,
                            starts_on: dayjs().startOf('year'),
                            ends_on: dayjs().endOf('year'),
                        },
                        admin: {
                            name: 'Pratik Admin',
                            email: 'pratik@admin.com',
                            password: 'done',
                            password_confirmation: 'done',
                        },
                    }}
                >
                    <div className="form-grid">
                        <Form.Item name={['company', 'name']} label="Company Name" rules={[{ required: true }]}>
                            <Input />
                        </Form.Item>
                        <Form.Item name={['company', 'pan_number']} label="PAN">
                            <Input />
                        </Form.Item>
                    </div>
                    <Form.Item name={['company', 'address']} label="Company Address">
                        <Input />
                    </Form.Item>
                    <div className="form-grid">
                        <Form.Item name={['store', 'name']} label="Store Name" rules={[{ required: true }]}>
                            <Input />
                        </Form.Item>
                        <Form.Item name={['store', 'phone']} label="Store Phone">
                            <Input />
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name={['branding', 'app_name']} label="App Name" rules={[{ required: true }]}>
                            <Input />
                        </Form.Item>
                        <Form.Item name={['branding', 'layout']} label="Navigation Layout" rules={[{ required: true }]}>
                            <Select options={[
                                { value: 'vertical', label: 'Vertical sidebar' },
                                { value: 'horizontal', label: 'Horizontal top menu' },
                            ]} />
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name={['branding', 'logo_url']} label="Logo URL / Path">
                            <Input placeholder="/storage/settings/logo.png" />
                        </Form.Item>
                        <Form.Item name={['branding', 'sidebar_logo_url']} label="Sidebar Logo URL / Path">
                            <Input placeholder="/storage/settings/sidebar-logo.png" />
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name={['branding', 'accent_color']} label="Accent Color">
                            <Input />
                        </Form.Item>
                        <Form.Item name={['branding', 'sidebar_default_collapsed']} valuePropName="checked">
                            <Checkbox>Start sidebar minimized</Checkbox>
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name={['fiscal_year', 'name']} label="Fiscal Year" rules={[{ required: true }]}>
                            <Input />
                        </Form.Item>
                        <Form.Item name={['fiscal_year', 'starts_on']} label="Starts On" rules={[{ required: true }]}>
                            <DatePicker className="full-width" />
                        </Form.Item>
                    </div>
                    <Form.Item name={['fiscal_year', 'ends_on']} label="Ends On" rules={[{ required: true }]}>
                        <DatePicker className="full-width" />
                    </Form.Item>
                    <div className="form-grid">
                        <Form.Item name={['admin', 'name']} label="Admin Name" rules={[{ required: true }]}>
                            <Input />
                        </Form.Item>
                        <Form.Item name={['admin', 'email']} label="Admin Email" rules={[{ required: true, type: 'email' }]}>
                            <Input />
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name={['admin', 'password']} label="Password" rules={[{ required: true }]}>
                            <Input.Password />
                        </Form.Item>
                        <Form.Item name={['admin', 'password_confirmation']} label="Confirm Password" rules={[{ required: true }]}>
                            <Input.Password />
                        </Form.Item>
                    </div>
                    <Form.Item name="seed_demo" valuePropName="checked">
                        <Checkbox disabled={status?.environment?.app_env === 'production'}>Seed local demo data</Checkbox>
                    </Form.Item>
                    <Button type="primary" htmlType="submit" loading={saving} disabled={!databaseReady}>Complete Setup</Button>
                </Form>
            </Card>
        </main>
    );
}
