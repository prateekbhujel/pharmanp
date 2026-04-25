import React, { useEffect, useState } from 'react';
import { App, Form, Input, InputNumber, Modal, Select } from 'antd';
import { BarcodeInput } from './BarcodeInput';
import { endpoints } from '../api/endpoints';
import { http, validationErrors } from '../api/http';

export function QuickProductModal({ open, onClose, onCreated }) {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [meta, setMeta] = useState({ companies: [], units: [], categories: [], formulations: [] });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            http.get(endpoints.productMeta).then(({ data }) => setMeta(data.data));
            form.setFieldsValue({
                formulation: 'Tablet',
                purchase_price: 0,
                mrp: 0,
                selling_price: 0,
                reorder_level: 10,
                is_active: true,
                is_batch_tracked: true,
            });
        }
    }, [open, form]);

    async function submit(values) {
        setSaving(true);
        try {
            const { data } = await http.post(endpoints.products, values);
            notification.success({ message: 'Product added' });
            form.resetFields();
            onCreated?.(data.data);
            onClose?.();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Product save failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    return (
        <Modal
            title="Quick Add Product"
            open={open}
            onCancel={onClose}
            onOk={() => form.submit()}
            confirmLoading={saving}
            width={720}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" onFinish={submit}>
                <div className="form-grid">
                    <Form.Item name="name" label="Product Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    <Form.Item name="barcode" label="Barcode"><BarcodeInput /></Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name="company_id" label="Company / Manufacturer" rules={[{ required: true }]}>
                        <Select showSearch optionFilterProp="label" options={meta.companies?.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Form.Item name="unit_id" label="Unit" rules={[{ required: true }]}>
                        <Select options={meta.units?.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name="category_id" label="Category" rules={[{ required: true }]}>
                        <Select options={meta.categories?.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Form.Item name="formulation" label="Formulation" rules={[{ required: true }]}>
                        <Select options={meta.formulations?.map((item) => ({ value: item, label: item }))} />
                    </Form.Item>
                </div>
                <Form.Item name="generic_name" label="Generic Name"><Input /></Form.Item>
                <div className="form-grid">
                    <Form.Item name="purchase_price" label="Purchase Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                    <Form.Item name="mrp" label="MRP" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                    <Form.Item name="selling_price" label="Selling Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                    <Form.Item name="reorder_level" label="Reorder Level" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                </div>
            </Form>
        </Modal>
    );
}
