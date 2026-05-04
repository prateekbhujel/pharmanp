import React, { useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import ApiOutlined from '@ant-design/icons/es/icons/ApiOutlined';
import KeyOutlined from '@ant-design/icons/es/icons/KeyOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/es/icons/SafetyCertificateOutlined';
import { useBranding } from '../context/BrandingContext';

const { Text, Title } = Typography;

export function ApiLogin({ onLogin }) {
    const { branding } = useBranding();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const appName = branding?.app_name || 'PharmaNP';
    const logo = branding?.logo_url || branding?.app_icon_url || branding?.sidebar_logo_url;

    const submit = async (values) => {
        setLoading(true);
        setError(null);

        try {
            await onLogin(values);
        } catch (exception) {
            setError(
                exception?.response?.data?.message
                || (exception?.request ? 'Unable to reach the API. Check the frontend API URL and CORS origin.' : 'Unable to sign in.'),
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="auth-page">
            <div className="auth-card">
                <div className="auth-brand">
                    {logo ? (
                        <img src={logo} alt={appName} />
                    ) : (
                        <div className="auth-brand-mark"><SafetyCertificateOutlined /></div>
                    )}
                    <div>
                        <strong>{appName}</strong>
                    </div>
                </div>
                {error ? <Alert className="mb-4" type="error" message={error} showIcon /> : null}

                <Form layout="vertical" onFinish={submit} autoComplete="on">
                    <Form.Item name="email" label="Email" rules={[{ required: true, type: 'email' }]}>
                        <Input autoFocus prefix={<ApiOutlined />} size="large" />
                    </Form.Item>
                    <Form.Item name="password" label="Password" rules={[{ required: true }]}>
                        <Input.Password prefix={<KeyOutlined />} size="large" />
                    </Form.Item>
                    <Button type="primary" htmlType="submit" loading={loading} size="large" block>
                        {loading ? 'Signing in and loading workspace...' : 'Sign In'}
                    </Button>
                </Form>

                <div className="auth-copyright mt-[1.2rem] text-center">
                    <Text type="secondary">
                        © {new Date().getFullYear()} {appName}. All rights reserved.
                    </Text>
                </div>
            </div>
        </div>
    );
}
