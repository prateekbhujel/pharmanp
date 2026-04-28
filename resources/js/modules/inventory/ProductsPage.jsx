import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Divider, Form, Input, InputNumber, Modal, Select, Space, Switch, Upload } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined, UndoOutlined, UploadOutlined } from '@ant-design/icons';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { FormDrawer } from '../../core/components/FormDrawer';
import { ExportButtons, ImportButton } from '../../core/components/ListToolbarActions';
import { Money } from '../../core/components/Money';
import { PageHeader } from '../../core/components/PageHeader';
import { ServerTable } from '../../core/components/ServerTable';
import { StatusTag } from '../../core/components/StatusTag';
import { StatusToggle } from '../../core/components/StatusToggle';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { InventoryMasterTable } from './InventoryMasterTable';
import { InventoryBatchesPanel } from './InventoryBatchesPanel';
import { StockAdjustmentsPanel } from './StockAdjustmentsPanel';
import { StockMovementsPanel } from './StockMovementsPanel';

const inventorySections = {
    companies: { title: 'Company', master: 'companies' },
    units: { title: 'Unit', master: 'units' },
    categories: { title: 'Category', master: 'categories' },
    batches: { title: 'Batches' },
    'stock-adjustment': { title: 'Stock Adjustment' },
    'case-movement': { title: 'Case Movement' },
    products: { title: 'Product' },
};

function currentSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    return inventorySections[section] ? section : 'products';
}

function normalizeFile(event) {
    return Array.isArray(event) ? event : event?.fileList;
}

function appendFormValue(payload, key, value) {
    if (key === 'image_upload') {
        return;
    }

    if (value === undefined || value === null || value === '') {
        return;
    }

    if (typeof value === 'boolean') {
        payload.append(key, value ? '1' : '0');
        return;
    }

    payload.append(key, value);
}

function productPayload(values, method = null) {
    const payload = new FormData();
    Object.entries(values).forEach(([key, value]) => appendFormValue(payload, key, value));

    const image = values.image_upload?.[0]?.originFileObj;
    if (image) {
        payload.append('image', image);
    }

    if (method) {
        payload.append('_method', method);
    }

    return payload;
}

export function ProductsPage() {
    const { notification } = App.useApp();
    const section = currentSection();
    const sectionConfig = inventorySections[section];
    const table = useServerTable({ endpoint: endpoints.products });
    const [meta, setMeta] = useState({ companies: [], units: [], categories: [], formulations: [] });
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [quickMaster, setQuickMaster] = useState(null);
    const [form] = Form.useForm();
    const [quickForm] = Form.useForm();
    const deletedMode = Boolean(table.filters.deleted);

    const watchedMrp = Number(Form.useWatch('mrp', form) || 0);
    const watchedDiscount = Number(Form.useWatch('discount_percent', form) || 0);
    const watchedPurchasePrice = Number(Form.useWatch('purchase_price', form) || 0);
    const watchedConversion = Number(Form.useWatch('conversion', form) || 1);
    const displayPrice = useMemo(() => watchedMrp - (watchedMrp * watchedDiscount / 100), [watchedMrp, watchedDiscount]);
    const profit = useMemo(() => displayPrice - (watchedPurchasePrice / (watchedConversion || 1)), [displayPrice, watchedPurchasePrice, watchedConversion]);

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
        form.setFieldsValue({
            is_active: true,
            is_batch_tracked: true,
            conversion: 1,
            previous_price: 0,
            discount_percent: 0,
            cc_rate: 0,
            reorder_level: 10,
            reorder_quantity: 0,
            purchase_price: 0,
            mrp: 0,
            selling_price: 0,
        });
        setDrawerOpen(true);
    }

    function openEdit(record) {
        setEditing(record);
        form.setFieldsValue({
            ...record,
            company_id: record.company?.id,
            unit_id: record.unit?.id,
            category_id: record.category?.id,
            image_upload: [],
            remove_image: false,
        });
        setDrawerOpen(true);
    }

    async function submit(values) {
        setSaving(true);
        try {
            if (editing) {
                await http.post(`${endpoints.products}/${editing.id}`, productPayload(values, 'PUT'));
                notification.success({ message: 'Product updated' });
            } else {
                await http.post(endpoints.products, productPayload(values));
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

    function restore(record) {
        confirmDelete({
            title: 'Restore product?',
            content: `${record.name} will return to the active product list.`,
            okText: 'Restore',
            danger: false,
            onOk: async () => {
                await http.post(endpoints.productRestore(record.id));
                notification.success({ message: 'Product restored' });
                table.reload();
            },
        });
    }

    function syncPricing(changedValues, values) {
        if (!('mrp' in changedValues) && !('discount_percent' in changedValues)) {
            return;
        }

        const mrp = Number(values.mrp || 0);
        const discount = Number(values.discount_percent || 0);
        form.setFieldValue('selling_price', Number((mrp - (mrp * discount / 100)).toFixed(2)));
    }

    const columns = [
        {
            title: 'Product Name',
            dataIndex: 'name',
            field: 'name',
            sorter: true,
            width: 300,
            render: (value, row) => (
                <div className="product-cell">
                    {row.image_url ? <img src={row.image_url} alt="" /> : <span className="product-cell-fallback">{value?.slice(0, 1)}</span>}
                    <div>
                        <strong>{value}</strong>
                        <small>{row.generic_name || row.composition || row.product_code || row.sku}</small>
                    </div>
                </div>
            ),
        },
        { title: 'Company', dataIndex: ['company', 'name'], width: 160 },
        { title: 'Formulation', dataIndex: 'formulation', width: 130 },
        { title: 'Unit', dataIndex: ['unit', 'name'], width: 100 },
        { title: 'Reorder Level', dataIndex: 'reorder_level', field: 'reorder_level', sorter: true, align: 'right', width: 130 },
        { title: 'Stock Qty', dataIndex: 'stock_on_hand', field: 'stock_on_hand', sorter: true, align: 'right', width: 120 },
        { title: 'MRP', dataIndex: 'mrp', field: 'mrp', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
        { title: 'CC Rate', dataIndex: 'cc_rate', align: 'right', width: 110, render: (value) => `${Number(value || 0).toFixed(2)}%` },
        { title: 'Status', dataIndex: 'is_active', width: 150, render: (value, row) => row.deleted_at ? <StatusTag active={false} falseText="Deleted" /> : <StatusToggle value={value} id={row.id} endpoint={endpoints.products} /> },
        {
            title: 'Action',
            key: 'actions',
            fixed: 'right',
            width: 112,
            render: (_, record) => (
                record.deleted_at ? (
                    <Button aria-label="Restore" icon={<UndoOutlined />} onClick={() => restore(record)}>Restore</Button>
                ) : (
                    <Space>
                        <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                        <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                    </Space>
                )
            ),
        },
    ];

    function productList() {
        return (
            <Card title="Product List">
                <div className="table-toolbar table-toolbar-products">
                    <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search product, generic, code or barcode" allowClear />
                    <Select
                        allowClear
                        placeholder="Company"
                        options={meta.companies?.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(company_id) => table.setFilters((filters) => ({ ...filters, company_id }))}
                    />
                    <div className="table-switch">
                        <Switch
                            checked={deletedMode}
                            onChange={(deleted) => table.setFilters((filters) => ({ ...filters, deleted: deleted ? 1 : undefined }))}
                        />
                        <span>View Deleted</span>
                    </div>
                    <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                </div>
                <ServerTable table={table} columns={columns} />
            </Card>
        );
    }

    function sectionBody() {
        if (sectionConfig.master) {
            return <InventoryMasterTable master={sectionConfig.master} />;
        }

        if (section === 'batches') {
            return <InventoryBatchesPanel />;
        }

        if (section === 'stock-adjustment') {
            return <StockAdjustmentsPanel />;
        }

        if (section === 'case-movement') {
            return <StockMovementsPanel />;
        }

        return productList();
    }

    return (
        <div className="page-stack">
            <PageHeader
                title={sectionConfig.title}
                description={sectionConfig.description}
                actions={section === 'products' ? (
                    <Space wrap>
                        <ExportButtons basePath={endpoints.inventoryProductsExport} params={{ search: table.search, ...table.filters }} />
                        <ImportButton target="products" />
                        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Product</Button>
                    </Space>
                ) : null}
            />

            {sectionBody()}

            <FormDrawer
                title={editing ? 'Edit Product' : 'Add New Product'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                footer={<Button type="primary" loading={saving} onClick={() => form.submit()} block>{editing ? 'Update' : 'Save'}</Button>}
                width={900}
            >
                <Form form={form} layout="vertical" onFinish={submit} onValuesChange={syncPricing}>
                    <Divider orientation="left">Basic Information</Divider>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="company_id" label="Company" rules={[{ required: true }]}>
                            <Select
                                showSearch
                                optionFilterProp="label"
                                placeholder="Select company"
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
                                placeholder="Select unit"
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
                                placeholder="Select category"
                                options={meta.categories?.map((item) => ({ value: item.id, label: item.name }))}
                                dropdownRender={(menu) => (
                                    <>
                                        {menu}
                                        <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickMaster('category')}>Quick add category</Button>
                                    </>
                                )}
                            />
                        </Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="product_code" label="Product Code"><Input placeholder="Optional unique code" /></Form.Item>
                        <Form.Item name="sku" label="SKU"><Input /></Form.Item>
                        <Form.Item name="barcode" label="Barcode"><BarcodeInput placeholder="Optional barcode" /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="name" label="Product Name" rules={[{ required: true }]}><Input placeholder="e.g. Paracetamol 500mg" /></Form.Item>
                        <Form.Item name="generic_name" label="Generic Name"><Input /></Form.Item>
                        <Form.Item name="composition" label="Composition"><Input /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="group_name" label="Group Name"><Input /></Form.Item>
                        <Form.Item name="manufacturer_name" label="Manufacturer"><Input /></Form.Item>
                        <Form.Item name="formulation" label="Formulation" rules={[{ required: true }]}>
                            <Select options={meta.formulations?.map((item) => ({ value: item, label: item }))} />
                        </Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="strength" label="Strength"><Input /></Form.Item>
                        <Form.Item name="conversion" label="Conversion"><InputNumber min={0.001} className="full-width" /></Form.Item>
                        <Form.Item name="rack_location" label="Rack Location"><Input /></Form.Item>
                    </div>

                    <Divider orientation="left">Pricing</Divider>
                    <div className="form-grid form-grid-4">
                        <Form.Item name="previous_price" label="Previous Price"><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="purchase_price" label="Purchase Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="mrp" label="MRP" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="cc_rate" label="CC Rate (%)"><InputNumber min={0} max={100} className="full-width" /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-4">
                        <Form.Item name="discount_percent" label="Discount (%)"><InputNumber min={0} max={100} className="full-width" /></Form.Item>
                        <Form.Item name="selling_price" label="Display / Selling Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item label="Calculated Display"><InputNumber readOnly value={Number(displayPrice.toFixed(2))} className="full-width" /></Form.Item>
                        <Form.Item label="Profit"><InputNumber readOnly value={Number(profit.toFixed(2))} className="full-width" /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="reorder_level" label="Reorder Level" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="reorder_quantity" label="Reorder Qty"><InputNumber min={0} className="full-width" /></Form.Item>
                        <div className="switch-row switch-row-inline">
                            <Form.Item name="is_batch_tracked" label="Batch Tracked" valuePropName="checked"><Switch /></Form.Item>
                            <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                        </div>
                    </div>

                    <Divider orientation="left">Description and Image</Divider>
                    <Form.Item name="keywords" label="Meta Keywords"><Input.TextArea rows={2} /></Form.Item>
                    <Form.Item name="description" label="Product Description"><Input.TextArea rows={4} /></Form.Item>
                    <Form.Item name="notes" label="Notes"><Input.TextArea rows={3} /></Form.Item>
                    {editing?.image_url && (
                        <div className="product-image-preview">
                            <img src={editing.image_url} alt="" />
                            <span>Current image</span>
                        </div>
                    )}
                    <Form.Item name="image_upload" label="Thumbnail Image" valuePropName="fileList" getValueFromEvent={normalizeFile}>
                        <Upload beforeUpload={() => false} maxCount={1} accept="image/*" listType="picture">
                            <Button icon={<UploadOutlined />}>Select Image</Button>
                        </Upload>
                    </Form.Item>
                    {editing?.image_url && <Form.Item name="remove_image" label="Remove current image" valuePropName="checked"><Switch /></Form.Item>}
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
                    {quickMaster === 'category' && (
                        <Form.Item name="code" label="Code"><Input /></Form.Item>
                    )}
                </Form>
            </Modal>
        </div>
    );
}
