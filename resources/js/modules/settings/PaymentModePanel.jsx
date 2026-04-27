import React, { useState } from 'react';
import { Button, Card, Form, Input, Space, Switch, App, Upload, Tag, Radio } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, QrcodeOutlined, WalletOutlined, BankOutlined } from '@ant-design/icons';
import { FormDrawer } from '../../core/components/FormDrawer';
import { ServerTable } from '../../core/components/ServerTable';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

export function PaymentModePanel() {
    const { notification } = App.useApp();
    const [editing, setEditing] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [form] = Form.useForm();
    const table = useServerTable({ 
        endpoint: endpoints.dropdownOptions, 
        initialFilters: { alias: 'payment_mode' } 
    });

    function openDrawer(record = null) {
        setEditing(record);
        form.resetFields();
        if (record) {
            form.setFieldsValue({
                ...record,
                type: record.data?.type || 'cash',
                instructions: record.data?.instructions || '',
            });
        } else {
            form.setFieldsValue({ is_active: true, type: 'cash' });
        }
        setDrawerOpen(true);
    }

    async function save(values) {
        try {
            const payload = {
                ...values,
                alias: 'payment_mode',
                data: {
                    type: values.type,
                    instructions: values.instructions,
                    qr_url: editing?.data?.qr_url, // Placeholder for QR upload logic if handled via separate endpoint
                }
            };

            if (editing) {
                await http.put(`${endpoints.dropdownOptions}/${editing.id}`, payload);
                notification.success({ message: 'Payment mode updated' });
            } else {
                await http.post(endpoints.dropdownOptions, payload);
                notification.success({ message: 'Payment mode created' });
            }
            
            setDrawerOpen(false);
            table.reload();
        } catch (error) {
            const errors = validationErrors(error);
            form.setFields(Object.entries(errors).map(([name, messages]) => ({ name, errors: messages })));
        }
    }

    function remove(record) {
        confirmDelete({
            title: `Delete payment mode ${record.name}?`,
            content: 'This might affect existing transaction records if not handled carefully.',
            onOk: async () => {
                await http.delete(`${endpoints.dropdownOptions}/${record.id}`);
                notification.success({ message: 'Payment mode deleted' });
                table.reload();
            },
        });
    }

    const columns = [
        { 
            title: 'Mode Name', 
            dataIndex: 'name', 
            field: 'name', 
            sorter: true,
            render: (text, row) => (
                <Space>
                    {row.data?.type === 'bank' && <BankOutlined />}
                    {row.data?.type === 'wallet' && <WalletOutlined />}
                    {row.data?.type === 'cash' && <Tag color="green">Cash</Tag>}
                    <strong>{text}</strong>
                </Space>
            )
        },
        { 
            title: 'Type', 
            dataIndex: ['data', 'type'], 
            width: 120,
            render: (type) => <Tag style={{ textTransform: 'uppercase' }}>{type || 'cash'}</Tag>
        },
        { 
            title: 'Status', 
            dataIndex: 'is_active', 
            width: 100, 
            render: (active) => <Tag color={active ? 'green' : 'red'}>{active ? 'Active' : 'Inactive'}</Tag> 
        },
        {
            title: 'Action',
            width: 120,
            fixed: 'right',
            render: (_, record) => (
                <Space>
                    <Button icon={<EditOutlined />} onClick={() => openDrawer(record)} />
                    <Button danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                </Space>
            ),
        },
    ];

    return (
        <Card title="Payment Modes" description="Configure how you accept payments (Cash, Bank, QR Wallets)">
            <div className="table-toolbar">
                <Input.Search value={table.search} onChange={(e) => table.setSearch(e.target.value)} placeholder="Search modes" allowClear />
                <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer()}>Add Payment Mode</Button>
            </div>
            
            <ServerTable table={table} columns={columns} />

            <FormDrawer
                title={editing ? 'Edit Payment Mode' : 'New Payment Mode'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                footer={<Button type="primary" onClick={() => form.submit()} block>Save Payment Mode</Button>}
            >
                <Form form={form} layout="vertical" onFinish={save}>
                    <Form.Item name="name" label="Display Name" rules={[{ required: true }]} tooltip="e.g. FonePay QR, Nabil Bank, Petty Cash">
                        <Input placeholder="e.g. FonePay QR" />
                    </Form.Item>
                    
                    <Form.Item name="type" label="Payment Type">
                        <Radio.Group optionType="button" buttonStyle="solid">
                            <Radio.Button value="cash">Cash</Radio.Button>
                            <Radio.Button value="bank">Bank</Radio.Button>
                            <Radio.Button value="wallet">Digital Wallet</Radio.Button>
                        </Radio.Group>
                    </Form.Item>

                    <Form.Item name="instructions" label="Payment Instructions" tooltip="Shown during POS checkout">
                        <Input.TextArea rows={3} placeholder="e.g. Account No: 12345... or scan the QR code below" />
                    </Form.Item>

                    <div style={{ marginBottom: 24 }}>
                        <div style={{ marginBottom: 8, fontWeight: 500 }}>QR Code / Icon</div>
                        <div className="smart-image-upload-wrapper" style={{ width: '100%', height: 200 }}>
                            <div className="smart-image-placeholder">
                                <QrcodeOutlined />
                                <span>Upload QR Code</span>
                                <small style={{ fontWeight: 400, color: '#94a3b8' }}>Optional image for scanning</small>
                            </div>
                        </div>
                    </div>

                    <Form.Item name="is_active" label="Available for Transactions" valuePropName="checked">
                        <Switch checkedChildren="Active" unCheckedChildren="Inactive" />
                    </Form.Item>
                </Form>
            </FormDrawer>
        </Card>
    );
}
