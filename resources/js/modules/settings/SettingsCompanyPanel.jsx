import React, { useEffect } from 'react';
import { App, Button, Card, Form, Input, InputNumber } from 'antd';

export function SettingsCompanyPanel({ form, loading, onSave }) {
    return (
        <Card title="Company Contact" loading={loading} className="settings-inner-card">
            <Form
                form={form}
                layout="vertical"
                onFinish={(values) => onSave(form, values, 'Company details saved')}
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
    );
}
