import React, { useEffect } from 'react';
import { App, Button, Card, Form, Input } from 'antd';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';

function fieldErrors(error) {
    return Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages }));
}

export function SettingsProfilePanel({ profile, reloadProfile, reloadAuth }) {
    const { notification } = App.useApp();
    const [profileForm] = Form.useForm();

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

    return (
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
    );
}
