import React from 'react';
import { Button, Card, Form, Input, InputNumber, Select } from 'antd';

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

export function SettingsNumberingPanel({ form, loading, onSave }) {
    return (
        <Card title="Numbering Rules" loading={loading} className="settings-inner-card">
            <Form
                form={form}
                layout="vertical"
                onFinish={(values) => onSave(form, values, 'Document numbering saved')}
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
    );
}
