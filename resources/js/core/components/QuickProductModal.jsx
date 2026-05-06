import React, { useEffect, useState } from 'react';
import { App, Button, Form, Input, InputNumber, Modal, Select } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import { BarcodeInput } from './BarcodeInput';
import { endpoints } from '../api/endpoints';
import { apiErrorMessage, formErrors, http, validationErrors } from '../api/http';

export function QuickProductModal({ open, onClose, onCreated }) {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [meta, setMeta] = useState({ companies: [], units: [], divisions: [] });
    const [saving, setSaving] = useState(false);
    const [quickMaster, setQuickMaster] = useState(null);
    const [quickForm] = Form.useForm();

    useEffect(() => {
        if (open) {
            http.get(endpoints.productMeta).then(({ data }) => setMeta(data.data));
            form.setFieldsValue({
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
            form.setFields(formErrors(error));
            notification.error({ message: 'Product save failed', description: apiErrorMessage(error) });
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
        } catch (error) {
            quickForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: `${config.label} save failed`, description: apiErrorMessage(error) });
        }
    }

    return (
        <Modal
            title="Quick Add Product"
            open={open}
            onCancel={onClose}
            onOk={() => form.submit()}
            confirmLoading={saving}
            width={840}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" onFinish={submit}>
                <div className="form-grid">
                    <Form.Item name="name" label="Product Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    <Form.Item name="barcode" label="Barcode"><BarcodeInput /></Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name="product_code" label="Product Code"><Input placeholder="Auto generated if empty" /></Form.Item>
                    <Form.Item name="hs_code" label="HS Code"><Input /></Form.Item>
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
                    <Form.Item name="division_id" label="Division">
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            options={meta.divisions?.map((item) => ({ value: item.id, label: item.code ? `${item.name} (${item.code})` : item.name }))}
                        />
                    </Form.Item>
                    <Form.Item name="group_name" label="Group Name"><Input /></Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name="generic_name" label="Generic Name"><Input /></Form.Item>
                    <Form.Item name="manufacturer_name" label="Manufacturer"><Input /></Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name="packaging_type" label="Packaging Type"><Input placeholder="Strip, bottle, box..." /></Form.Item>
                    <Form.Item name="keywords" label="Meta Keywords"><Input placeholder="fever, antibiotic, paediatric..." /></Form.Item>
                </div>
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
                okButtonProps={{ disabled: saving }}
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
                </Form>
            </Modal>
        </Modal>
    );
}
