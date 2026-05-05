import React from 'react';
import { App, Button, Card, Form, Input, Select, Space } from 'antd';
import { SendOutlined } from '@ant-design/icons';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';

export function SettingsMailPanel({ form, loading, smtpPasswordSet, onSave }) {
    const { notification } = App.useApp();

    async function sendTestMail() {
        try {
            const { data } = await http.post(endpoints.settingsTestMail, {
                email: form.getFieldValue('notification_email') || form.getFieldValue('mail_from_address'),
            });
            notification.success({ message: data.message || 'Test email sent' });
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Test mail failed' });
        }
    }

    return (
        <Card title="Mail Delivery" loading={loading} className="settings-inner-card">
            <Form
                form={form}
                layout="vertical"
                onFinish={(values) => onSave(form, values, 'Mail configuration saved')}
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
    );
}
