import React, { useEffect, useState } from 'react';
import { App, Button, Form, Input, InputNumber, Modal, Select } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { BarcodeInput } from './BarcodeInput';
import { endpoints } from '../api/endpoints';
import { http, validationErrors } from '../api/http';

export function QuickProductModal({ open, onClose, onCreated }) {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [meta, setMeta] = useState({ companies: [], units: [], categories: [], formulations: [] });
    const [saving, setSaving] = useState(false);
    const [quickMaster, setQuickMaster] = useState(null);
    const [quickForm] = Form.useForm();

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

    async function loadMeta() {
        const { data } = await http.get(endpoints.productMeta);
        setMeta(data.data);
    }

    async function submitQuickMaster(values) {
        const config = {
            company: { endpoint: endpoints.quickCompany, label: 'Company' },
            unit: { endpoint: endpoints.quickUnit, label: 'Unit' },
            category: { endpoint: endpoints.quickCategory, label: 'Category' },
        }[quickMaster];

        if (!config) return;

        try {
            const { data } = await http.post(config.endpoint, values);
            await loadMeta();
            quickForm.resetFields();
            setQuickMaster(null);
            notification.success({ message: `${config.label} added` });

            if (quickMaster === 'company') form.setFieldValue('company_id', data.data.id);
            if (quickMaster === 'unit') form.setFieldValue('unit_id', data.data.id);
            if (quickMaster === 'category') form.setFieldValue('category_id', data.data.id);
        } catch (error) {
            quickForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: `${config.label} save failed` });
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
                        <Select 
                            showSearch 
                            optionFilterProp="label" 
                            options={meta.companies?.map((item) => ({ value: item.id, label: item.name }))} 
                            dropdownRender={(menu) => (
                                <>
                                    {menu}
                                    <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickMaster('company')}>Quick add company</Button>
                                </>
                            )}
                        />
                    </Form.Item>
                    <Form.Item name="unit_id" label="Unit" rules={[{ required: true }]}>
                        <Select 
                            options={meta.units?.map((item) => ({ value: item.id, label: item.name }))} 
                            dropdownRender={(menu) => (
                                <>
                                    {menu}
                                    <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickMaster('unit')}>Quick add unit</Button>
                                </>
                            )}
                        />
                    </Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name="category_id" label="Category" rules={[{ required: true }]}>
                        <Select 
                            options={meta.categories?.map((item) => ({ value: item.id, label: item.name }))} 
                            dropdownRender={(menu) => (
                                <>
                                    {menu}
                                    <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickMaster('category')}>Quick add category</Button>
                                </>
                            )}
                        />
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

            <Modal
                title={`Quick add ${quickMaster || ''}`}
                open={Boolean(quickMaster)}
                onCancel={() => setQuickMaster(null)}
                onOk={() => quickForm.submit()}
                destroyOnHidden
                zIndex={1050}
            >
                <Form form={quickForm} layout="vertical" onFinish={submitQuickMaster}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    {quickMaster === 'unit' && (
                        <div className="form-grid">
                            <Form.Item name="code" label="Code"><Input /></Form.Item>
                            <Form.Item name="type" label="Type" initialValue="both">
                                <Select options={[{ value: 'both', label: 'Purchase and sale' }, { value: 'purchase', label: 'Purchase only' }, { value: 'sale', label: 'Sale only' }]} />
                            </Form.Item>
                            <Form.Item name="factor" label="Factor" initialValue={1}><InputNumber min={0.0001} className="full-width" /></Form.Item>
                        </div>
                    )}
                    {quickMaster === 'company' && (
                        <>
                            <div className="form-grid">
                                <Form.Item name="legal_name" label="Legal Name"><Input /></Form.Item>
                                <Form.Item name="pan_number" label="PAN"><Input /></Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                                <Form.Item name="default_cc_rate" label="Default CC %"><InputNumber min={0} max={100} className="full-width" /></Form.Item>
                            </div>
                        </>
                    )}
                    {quickMaster === 'category' && <Form.Item name="code" label="Code"><Input /></Form.Item>}
                </Form>
            </Modal>
        </Modal>
    );
}
