import React, { useEffect, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Select, Space, Switch, Tabs } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined } from '@ant-design/icons';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { FormDrawer } from '../../core/components/FormDrawer';
import { Money } from '../../core/components/Money';
import { PageHeader } from '../../core/components/PageHeader';
import { ServerTable } from '../../core/components/ServerTable';
import { StatusTag } from '../../core/components/StatusTag';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { InventoryMasterTable } from './InventoryMasterTable';

export function ProductsPage() {
    const { notification } = App.useApp();
    const table = useServerTable({ endpoint: endpoints.products });
    const [meta, setMeta] = useState({ companies: [], units: [], categories: [], formulations: [] });
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [quickMaster, setQuickMaster] = useState(null);
    const [form] = Form.useForm();
    const [quickForm] = Form.useForm();

    useEffect(() => {
        loadMeta();
    }, []);

    async function loadMeta() {
        const { data } = await http.get(endpoints.productMeta);
        setMeta(data.data);
    }

    function openCreate() {
        setEditing(null);
        form.resetFields();
        form.setFieldsValue({ is_active: true, is_batch_tracked: true, reorder_level: 10 });
        setDrawerOpen(true);
    }

    function openEdit(record) {
        setEditing(record);
        form.setFieldsValue({
            ...record,
            company_id: record.company?.id,
            unit_id: record.unit?.id,
            category_id: record.category?.id,
        });
        setDrawerOpen(true);
    }

    async function submit(values) {
        setSaving(true);
        try {
            if (editing) {
                await http.put(`${endpoints.products}/${editing.id}`, values);
                notification.success({ message: 'Product updated' });
            } else {
                await http.post(endpoints.products, values);
                notification.success({ message: 'Product created' });
            }
            setDrawerOpen(false);
            table.reload();
        } catch (error) {
            const errors = validationErrors(error);
            form.setFields(Object.entries(errors).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Product save failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    async function submitQuickMaster(values) {
        const config = {
            company: { endpoint: endpoints.quickCompany, label: 'Company' },
            unit: { endpoint: endpoints.quickUnit, label: 'Unit' },
            category: { endpoint: endpoints.quickCategory, label: 'Category' },
        }[quickMaster];

        if (!config) {
            return;
        }

        try {
            const { data } = await http.post(config.endpoint, {
                ...values,
                company_id: form.getFieldValue('company_id'),
            });
            await loadMeta();
            quickForm.resetFields();
            setQuickMaster(null);
            notification.success({ message: `${config.label} added` });

            if (quickMaster === 'company') {
                form.setFieldValue('company_id', data.data.id);
            }
            if (quickMaster === 'unit') {
                form.setFieldValue('unit_id', data.data.id);
            }
            if (quickMaster === 'category') {
                form.setFieldValue('category_id', data.data.id);
            }
        } catch (error) {
            const errors = validationErrors(error);
            quickForm.setFields(Object.entries(errors).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: `${config.label} save failed`, description: error?.response?.data?.message || error.message });
        }
    }

    function remove(record) {
        confirmDelete({
            title: 'Delete product?',
            content: `${record.name} will be soft deleted.`,
            onOk: async () => {
                await http.delete(`${endpoints.products}/${record.id}`);
                notification.success({ message: 'Product deleted' });
                table.reload();
            },
        });
    }

    const columns = [
        { title: 'Product', dataIndex: 'name', field: 'name', sorter: true, render: (value, row) => <div><strong>{value}</strong><small>{row.generic_name || row.composition || row.sku}</small></div> },
        { title: 'Barcode', dataIndex: 'barcode', field: 'barcode', sorter: true, width: 150 },
        { title: 'Company', dataIndex: ['company', 'name'], width: 160 },
        { title: 'Unit', dataIndex: ['unit', 'name'], width: 100 },
        { title: 'Stock', dataIndex: 'stock_on_hand', field: 'stock_on_hand', sorter: true, align: 'right', width: 110 },
        { title: 'MRP', dataIndex: 'mrp', field: 'mrp', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
        { title: 'Reorder', dataIndex: 'reorder_level', field: 'reorder_level', sorter: true, align: 'right', width: 110 },
        { title: 'Status', dataIndex: 'is_active', width: 110, render: (value) => <StatusTag active={value} /> },
        {
            title: '',
            key: 'actions',
            fixed: 'right',
            width: 112,
            render: (_, record) => (
                <Space>
                    <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                    <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                </Space>
            ),
        },
    ];

    return (
        <div className="page-stack">
            <PageHeader
                title="Inventory"
                description="Products, companies, units, categories and stock-facing master data"
                actions={<Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>New Product</Button>}
            />

            <Tabs items={[
                {
                    key: 'products',
                    label: 'Products',
                    children: (
                        <Card>
                            <div className="table-toolbar">
                                <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search name, generic, SKU or barcode" allowClear />
                                <Select
                                    allowClear
                                    placeholder="Company"
                                    options={meta.companies?.map((item) => ({ value: item.id, label: item.name }))}
                                    onChange={(company_id) => table.setFilters((filters) => ({ ...filters, company_id }))}
                                />
                                <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                            </div>
                            <ServerTable table={table} columns={columns} />
                        </Card>
                    ),
                },
                { key: 'companies', label: 'Companies', children: <InventoryMasterTable master="companies" /> },
                { key: 'units', label: 'Units', children: <InventoryMasterTable master="units" /> },
                { key: 'categories', label: 'Categories', children: <InventoryMasterTable master="categories" /> },
            ]} />

            <FormDrawer
                title={editing ? 'Edit Product' : 'New Product'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                footer={<Button type="primary" loading={saving} onClick={() => form.submit()} block>{editing ? 'Update Product' : 'Create Product'}</Button>}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="name" label="Product Name" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name="barcode" label="Barcode">
                        <BarcodeInput />
                    </Form.Item>
                    <Form.Item name="sku" label="SKU">
                        <Input />
                    </Form.Item>
                    <Form.Item name="generic_name" label="Generic Name">
                        <Input />
                    </Form.Item>
                    <Form.Item name="composition" label="Composition">
                        <Input />
                    </Form.Item>
                    <Form.Item name="formulation" label="Formulation" rules={[{ required: true }]}>
                        <Select options={meta.formulations?.map((item) => ({ value: item, label: item }))} />
                    </Form.Item>
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
                    <div className="form-grid">
                        <Form.Item name="purchase_price" label="Purchase Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="mrp" label="MRP" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="selling_price" label="Selling Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="cc_rate" label="CC %"><InputNumber min={0} max={100} className="full-width" /></Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="reorder_level" label="Reorder Level" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="reorder_quantity" label="Reorder Qty"><InputNumber min={0} className="full-width" /></Form.Item>
                    </div>
                    <Form.Item name="rack_location" label="Rack Location"><Input /></Form.Item>
                    <Form.Item name="notes" label="Notes"><Input.TextArea rows={3} /></Form.Item>
                    <div className="switch-row">
                        <Form.Item name="is_batch_tracked" label="Batch Tracked" valuePropName="checked"><Switch /></Form.Item>
                        <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                    </div>
                </Form>
            </FormDrawer>

            <Modal
                title={`Quick add ${quickMaster || ''}`}
                open={Boolean(quickMaster)}
                onCancel={() => setQuickMaster(null)}
                onOk={() => quickForm.submit()}
                destroyOnHidden
            >
                <Form form={quickForm} layout="vertical" onFinish={submitQuickMaster}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}>
                        <Input autoFocus />
                    </Form.Item>
                    {quickMaster === 'unit' && (
                        <div className="form-grid">
                            <Form.Item name="code" label="Code"><Input /></Form.Item>
                            <Form.Item name="type" label="Type" initialValue="both">
                                <Select options={[
                                    { value: 'both', label: 'Purchase and sale' },
                                    { value: 'purchase', label: 'Purchase only' },
                                    { value: 'sale', label: 'Sale only' },
                                ]} />
                            </Form.Item>
                        </div>
                    )}
                    {quickMaster === 'company' && (
                        <div className="form-grid">
                            <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                            <Form.Item name="pan_number" label="PAN"><Input /></Form.Item>
                        </div>
                    )}
                    {quickMaster === 'category' && (
                        <Form.Item name="code" label="Code"><Input /></Form.Item>
                    )}
                </Form>
            </Modal>
        </div>
    );
}
