import React, { useEffect, useState } from 'react';
import { App, Button, Card, Checkbox, Form, Input, Steps } from 'antd';
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

    async function submit(values) {
        setSaving(true);
        try {
            await http.post(endpoints.setupComplete, values);
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
            <section className="setup-panel">
                <div className="setup-brand">
                    <strong>PharmaNP</strong>
                    <span>First install setup</span>
                </div>
                <Steps
                    direction="vertical"
                    current={0}
                    items={[
                        { title: 'Environment', description: status?.database?.ok ? 'Database connected' : 'Checking database' },
                        { title: 'Company / Store' },
                        { title: 'Admin User' },
                        { title: 'Roles / Permissions' },
                    ]}
                />
            </section>

            <Card className="setup-card" title="Initialize PharmaNP">
                <Form form={form} layout="vertical" onFinish={submit}>
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
                    <Button type="primary" htmlType="submit" loading={saving}>Complete Setup</Button>
                </Form>
            </Card>
        </main>
    );
}
