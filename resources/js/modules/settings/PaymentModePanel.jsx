import React, { useMemo, useState } from 'react';
import { App, Button, Card, Form, Input, Modal, Radio, Space, Switch, Table, Upload } from 'antd';
import { BankOutlined, DeleteOutlined, EditOutlined, PlusOutlined, QrcodeOutlined, WalletOutlined } from '@ant-design/icons';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { StatusToggle } from '../../core/components/StatusToggle';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { paymentModeTypeOptions } from '../../core/utils/dropdownOptions';

function normalizeFile(event) {
    return Array.isArray(event) ? event : event?.fileList;
}

function paymentModePayload(values, method = null) {
    const payload = new FormData();
    payload.append('alias', 'payment_mode');
    payload.append('name', values.name || '');
    payload.append('data', values.type || 'cash');
    payload.append('status', values.status ? '1' : '0');

    if (values.instructions) {
        payload.append('meta[instructions]', values.instructions);
    }

    const qrFile = values.qr_upload?.[0]?.originFileObj;
    if (qrFile) {
        payload.append('qr_file', qrFile);
    }

    if (method) {
        payload.append('_method', method);
    }

    return payload;
}

function ModeIcon({ type }) {
    if (type === 'bank') return <BankOutlined />;
    if (type === 'wallet') return <WalletOutlined />;
    return <WalletOutlined />;
}

export function PaymentModePanel() {
    const { notification } = App.useApp();
    const { data, reload } = useApi(endpoints.dropdownOptions);
    const [editing, setEditing] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form] = Form.useForm();

    const rows = useMemo(
        () => (Array.isArray(data) ? data : (data?.data || [])).filter((item) => item.alias === 'payment_mode'),
        [data],
    );

    function openModal(record = null) {
        setEditing(record);
        form.resetFields();
        form.setFieldsValue(record ? {
            name: record.name,
            type: record.data || 'cash',
            instructions: record.meta?.instructions || '',
            status: Boolean(record.status),
            qr_upload: [],
        } : {
            type: 'cash',
            status: true,
            qr_upload: [],
        });
        setModalOpen(true);
    }

    async function save(values) {
        setSaving(true);
        try {
            if (editing) {
                await http.post(`${endpoints.dropdownOptions}/${editing.id}`, paymentModePayload(values, 'PUT'));
                notification.success({ message: 'Payment mode updated' });
            } else {
                await http.post(endpoints.dropdownOptions, paymentModePayload(values));
                notification.success({ message: 'Payment mode created' });
            }

            setModalOpen(false);
            reload?.();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Payment mode save failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    function remove(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'Used payment modes are protected by the backend and cannot be deleted.',
            onOk: async () => {
                await http.delete(`${endpoints.dropdownOptions}/${record.id}`);
                notification.success({ message: 'Payment mode deleted' });
                reload?.();
            },
        });
    }

    const columns = [
        {
            title: 'Mode',
            dataIndex: 'name',
            render: (text, row) => (
                <Space>
                    <ModeIcon type={row.data} />
                    <strong>{text}</strong>
                    {row.meta?.qr_url && <PharmaBadge tone="info" icon={<QrcodeOutlined />}>QR</PharmaBadge>}
                </Space>
            ),
        },
        {
            title: 'Type',
            dataIndex: 'data',
            width: 150,
            render: (type) => <PharmaBadge tone={type === 'cash' ? 'success' : 'info'}>{paymentModeTypeOptions.find((item) => item.value === type)?.label || type || '-'}</PharmaBadge>,
        },
        {
            title: 'Instructions',
            dataIndex: ['meta', 'instructions'],
            ellipsis: true,
            render: (value) => value || '-',
        },
        {
            title: 'Status',
            dataIndex: 'is_active',
            width: 150,
            render: (active, record) => <StatusToggle value={active} id={record.id} endpoint={endpoints.dropdownOptions} />,
        },
        {
            title: 'Action',
            width: 112,
            render: (_, record) => (
                <Space>
                    <Button icon={<EditOutlined />} onClick={() => openModal(record)} />
                    <Button danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                </Space>
            ),
        },
    ];

    return (
        <Card
            title="Payment Modes"
            extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openModal()}>Add Payment Mode</Button>}
        >
            <Table rowKey="id" dataSource={rows} columns={columns} pagination={false} />

            <Modal
                centered
                className="intent-modal"
                title={editing ? 'Edit Payment Mode' : 'New Payment Mode'}
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                onOk={() => form.submit()}
                confirmLoading={saving}
                width={720}
                destroyOnHidden
            >
                <Form form={form} layout="vertical" onFinish={save}>
                    <Form.Item name="name" label="Display Name" rules={[{ required: true }]}>
                        <Input placeholder="Cash, FonePay QR, Nabil Bank" />
                    </Form.Item>
                    <Form.Item name="type" label="Ledger Route" rules={[{ required: true }]}>
                        <Radio.Group optionType="button" buttonStyle="solid" options={paymentModeTypeOptions} />
                    </Form.Item>
                    <Form.Item name="instructions" label="Payment Instructions">
                        <Input.TextArea rows={3} placeholder="Account number, wallet note, QR instructions or counter note" />
                    </Form.Item>
                    {editing?.meta?.qr_url && (
                        <div className="payment-qr-preview">
                            <img src={editing.meta.qr_url} alt="" />
                            <span>Current QR</span>
                        </div>
                    )}
                    <Form.Item name="qr_upload" label="QR Image" valuePropName="fileList" getValueFromEvent={normalizeFile}>
                        <Upload beforeUpload={() => false} maxCount={1} accept="image/*" listType="picture">
                            <Button icon={<QrcodeOutlined />}>Attach QR</Button>
                        </Upload>
                    </Form.Item>
                    <Form.Item name="status" label="Available for Transactions" valuePropName="checked">
                        <Switch checkedChildren="Active" unCheckedChildren="Inactive" />
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
