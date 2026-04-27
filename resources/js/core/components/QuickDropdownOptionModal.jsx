import React from 'react';
import { App, Form, Input, Modal, Select, Switch } from 'antd';
import { endpoints } from '../api/endpoints';
import { http, validationErrors } from '../api/http';
import { dropdownDataField, fallbackDropdownAliases } from '../utils/dropdownOptions';

export function QuickDropdownOptionModal({
    alias,
    open,
    onClose,
    onCreated,
    title,
}) {
    const { notification } = App.useApp();
    const [form] = Form.useForm();

    const aliasMeta = fallbackDropdownAliases[alias] || {};
    const dataField = dropdownDataField(alias);

    async function submit(values) {
        try {
            const { data } = await http.post(endpoints.dropdownOptions, {
                alias,
                name: values.name,
                data: values.data || null,
                status: values.status ?? true,
            });
            notification.success({ message: data.message || 'Option created' });
            form.resetFields();
            onCreated?.(data.data);
            onClose?.();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Quick add failed', description: error?.response?.data?.message || error.message });
        }
    }

    return (
        <Modal
            title={title || `Quick Add ${aliasMeta.label || alias}`}
            open={open}
            onCancel={() => {
                form.resetFields();
                onClose?.();
            }}
            onOk={() => form.submit()}
            destroyOnHidden
        >
            <Form
                form={form}
                layout="vertical"
                onFinish={submit}
                initialValues={{ status: true }}
            >
                <Form.Item name="name" label="Name" rules={[{ required: true }]}>
                    <Input autoFocus />
                </Form.Item>
                {aliasMeta.supports_data && dataField?.options && (
                    <Form.Item name="data" label={dataField.label}>
                        <Select allowClear options={dataField.options} placeholder={dataField.placeholder} />
                    </Form.Item>
                )}
                {aliasMeta.supports_data && !dataField?.options && (
                    <Form.Item name="data" label={dataField?.label || 'Extra Data'}>
                        <Input placeholder={dataField?.placeholder || 'Optional'} />
                    </Form.Item>
                )}
                <Form.Item name="status" valuePropName="checked" label="Active">
                    <Switch />
                </Form.Item>
            </Form>
        </Modal>
    );
}
