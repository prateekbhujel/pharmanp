import React, { useState } from 'react';
import { Alert, Button, Card, Form, Input } from 'antd';

export function ApiLogin({ onLogin }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const submit = async (values) => {
        setLoading(true);
        setError(null);

        try {
            await onLogin(values);
        } catch (exception) {
            setError(exception?.response?.data?.message || 'Unable to sign in.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-100 px-4 py-8 flex items-center justify-center">
            <Card className="w-full max-w-sm shadow-sm" title="PharmaNP">
                {error ? <Alert className="mb-4" type="error" message={error} showIcon /> : null}
                <Form layout="vertical" onFinish={submit} autoComplete="on">
                    <Form.Item name="email" label="Email" rules={[{ required: true, type: 'email' }]}>
                        <Input autoFocus />
                    </Form.Item>
                    <Form.Item name="password" label="Password" rules={[{ required: true }]}>
                        <Input.Password />
                    </Form.Item>
                    <Button type="primary" htmlType="submit" loading={loading} block>
                        Sign In
                    </Button>
                </Form>
            </Card>
        </div>
    );
}
