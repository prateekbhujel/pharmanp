import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Divider, Drawer, Form, Input, InputNumber, Modal, Select, Space, Switch, Tabs, Upload } from 'antd';
import { BarcodeOutlined, DeleteOutlined, EditOutlined, HistoryOutlined, PlusOutlined, PrinterOutlined, ReloadOutlined, UndoOutlined, UploadOutlined } from '@ant-design/icons';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { BarcodeLabel, printBarcodeLabels, productBarcodeValue } from '../../core/components/BarcodeLabel';
import { FormDrawer } from '../../core/components/FormDrawer';
import { ExportButtons, ImportButton } from '../../core/components/ListToolbarActions';
import { Money } from '../../core/components/Money';
import { PageHeader } from '../../core/components/PageHeader';
import { DateText } from '../../core/components/DateText';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { StatusTag } from '../../core/components/StatusTag';
import { StatusToggle } from '../../core/components/StatusToggle';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { InventoryMasterTable } from './InventoryMasterTable';
import { StockAdjustmentsPanel } from './StockAdjustmentsPanel';
import { StockMovementsPanel } from './StockMovementsPanel';
import { makeBarcodeCandidate } from '../../core/utils/code128';

const inventorySections = {
    companies: { title: 'Company', master: 'companies' },
    units: { title: 'Unit', master: 'units' },
    'stock-adjustment': { title: 'Stock Adjustment' },
    'stock-ledger': { title: 'Stock Ledger' },
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

function ProductHistoryDrawer({ product, onClose }) {
    const batchTable = useServerTable({
        endpoint: endpoints.inventoryBatches,
        defaultSort: { field: 'expires_at', order: 'asc' },
        defaultFilters: { product_id: product.id },
    });
    const movementTable = useServerTable({
        endpoint: endpoints.stockMovements,
        defaultSort: { field: 'movement_date', order: 'desc' },
        defaultFilters: { product_id: product.id },
    });

    const batchColumns = [
        { title: 'Batch', dataIndex: 'batch_no', field: 'batch_no', sorter: true, width: 150 },
        { title: 'Supplier', dataIndex: ['supplier', 'name'], width: 180 },
        { title: 'Expiry', dataIndex: 'expires_at', field: 'expires_at', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Available', dataIndex: 'quantity_available', field: 'quantity_available', sorter: true, align: 'right', width: 120 },
        { title: 'Received', dataIndex: 'quantity_received', align: 'right', width: 120 },
        { title: 'Purchase', dataIndex: 'purchase_price', field: 'purchase_price', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
        { title: 'MRP', dataIndex: 'mrp', field: 'mrp', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
        { title: 'Status', dataIndex: 'expiry_status', width: 130, render: (value) => <PharmaBadge tone={value === 'expired' ? 'danger' : value?.startsWith('expiring') ? 'warning' : 'success'}>{String(value || 'valid').replaceAll('_', ' ')}</PharmaBadge> },
    ];
    const movementColumns = [
        { title: 'Date', dataIndex: 'movement_date', field: 'movement_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Batch', dataIndex: ['batch', 'batch_no'], width: 150 },
        { title: 'Movement', dataIndex: 'movement_type', field: 'movement_type', sorter: true, width: 180, render: (value) => <PharmaBadge tone={String(value || '').includes('_out') ? 'warning' : 'info'}>{String(value || '').replaceAll('_', ' ')}</PharmaBadge> },
        { title: 'In', dataIndex: 'quantity_in', field: 'quantity_in', sorter: true, align: 'right', width: 100 },
        { title: 'Out', dataIndex: 'quantity_out', field: 'quantity_out', sorter: true, align: 'right', width: 100 },
        { title: 'Reference', width: 180, render: (_, row) => row.reference_type ? `${row.reference_type} #${row.reference_id}` : '-' },
        { title: 'Notes', dataIndex: 'notes', width: 280 },
    ];

    return (
        <Drawer
            title={product.name}
            open
            onClose={onClose}
            width={980}
            destroyOnHidden
            className="product-history-drawer"
        >
            <div className="product-history-summary">
                <div>
                    <span>SKU</span>
                    <strong>{product.sku || product.product_code || '-'}</strong>
                </div>
                <div>
                    <span>Company</span>
                    <strong>{product.company?.name || '-'}</strong>
                </div>
                <div>
                    <span>Stock</span>
                    <strong>{Number(product.stock_on_hand || 0).toLocaleString()}</strong>
                </div>
                <div>
                    <span>MRP</span>
                    <strong><Money value={product.mrp} /></strong>
                </div>
                <div>
                    <span>Barcode</span>
                    <strong>{productBarcodeValue(product) || '-'}</strong>
                </div>
            </div>
            <div className="barcode-history-preview">
                <BarcodeLabel value={productBarcodeValue(product)} caption={product.name} compact />
                <Button icon={<PrinterOutlined />} onClick={() => printBarcodeLabels([product])}>Print Label</Button>
            </div>
            <Tabs
                items={[
                    {
                        key: 'batches',
                        label: 'Batch History',
                        children: <ServerTable table={batchTable} columns={batchColumns} />,
                    },
                    {
                        key: 'movements',
                        label: 'Stock Movement',
                        children: <ServerTable table={movementTable} columns={movementColumns} />,
                    },
                ]}
            />
        </Drawer>
    );
}

function CalculatedPricing({ form }) {
    const watchedMrp = Number(Form.useWatch('mrp', form) || 0);
    const watchedDiscount = Number(Form.useWatch('discount_percent', form) || 0);
    const watchedPurchasePrice = Number(Form.useWatch('purchase_price', form) || 0);
    const watchedConversion = Number(Form.useWatch('conversion', form) || 1);
    const displayPrice = watchedMrp - (watchedMrp * watchedDiscount / 100);
    const profit = displayPrice - (watchedPurchasePrice / (watchedConversion || 1));

    return (
        <>
            <Form.Item label="Calculated Display"><InputNumber readOnly value={Number(displayPrice.toFixed(2))} className="full-width" /></Form.Item>
            <Form.Item label="Profit"><InputNumber readOnly value={Number(profit.toFixed(2))} className="full-width" /></Form.Item>
        </>
    );
}

function BarcodePreview({ form, editing }) {
    const watchedBarcode = Form.useWatch('barcode', form);
    const watchedName = Form.useWatch('name', form);

    if (!watchedBarcode) {
        return null;
    }

    return (
        <div className="barcode-form-preview">
            <BarcodeLabel value={watchedBarcode} caption={watchedName || 'Product barcode'} compact />
            <Button icon={<PrinterOutlined />} onClick={() => printBarcodeLabels([{ ...(editing || {}), name: watchedName, barcode: watchedBarcode }])}>Print</Button>
        </div>
    );
}

export function ProductsPage() {
    const { notification } = App.useApp();
    const section = currentSection();
    const sectionConfig = inventorySections[section];
    const table = useServerTable({ endpoint: endpoints.products });
    const [meta, setMeta] = useState({ companies: [], units: [], divisions: [] });
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [quickMaster, setQuickMaster] = useState(null);
    const [historyProduct, setHistoryProduct] = useState(null);
    const [form] = Form.useForm();
    const [quickForm] = Form.useForm();
    const deletedMode = Boolean(table.filters.deleted);

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
            division_id: record.division?.id,
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

    function generateBarcode() {
        const seed = form.getFieldValue('sku')
            || form.getFieldValue('product_code')
            || form.getFieldValue('name')
            || 'PNP';

        form.setFieldValue('barcode', makeBarcodeCandidate(seed));
    }

    function printProductBarcode(record) {
        const printed = printBarcodeLabels([record]);

        if (!printed) {
            notification.warning({ message: 'Add barcode or SKU before printing' });
        }
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
                        <small>{row.generic_name || row.product_code || row.sku}</small>
                    </div>
                </div>
            ),
        },
        { title: 'Code', dataIndex: 'product_code', field: 'product_code', sorter: true, width: 130, render: (value, row) => value || row.sku || '-' },
        { title: 'HS Code', dataIndex: 'hs_code', width: 120, render: (value) => value || '-' },
        { title: 'Company', dataIndex: ['company', 'name'], width: 170, render: (value) => value || '-' },
        { title: 'Division', dataIndex: ['division', 'name'], width: 150, render: (value) => value || '-' },
        { title: 'Packaging', dataIndex: 'packaging_type', width: 140, render: (value) => value || '-' },
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
            width: 190,
            render: (_, record) => (
                record.deleted_at ? (
                    <Button aria-label="Restore" icon={<UndoOutlined />} onClick={() => restore(record)}>Restore</Button>
                ) : (
                    <Space>
                        <Button aria-label="History" icon={<HistoryOutlined />} onClick={() => setHistoryProduct(record)} />
                        <Button aria-label="Print Barcode" icon={<BarcodeOutlined />} onClick={() => printProductBarcode(record)} />
                        <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                        <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                    </Space>
                )
            ),
        },
    ];

    function productExpandedRow(record) {
        const stock = Number(record.stock_on_hand || 0);
        const reorder = Number(record.reorder_level || 0);
        const margin = Number(record.selling_price || 0) - Number(record.purchase_price || 0);

        return (
            <div className="expanded-summary-grid">
                <div>
                    <span>Division</span>
                    <strong>{record.division?.name || 'Unassigned'}</strong>
                    <small>{record.division?.code || 'No division code'}</small>
                </div>
                <div>
                    <span>Manufacturer</span>
                    <strong>{record.manufacturer_name || record.company?.name || '-'}</strong>
                    <small>{record.group_name || record.generic_name || 'No group/generic set'}</small>
                </div>
                <div>
                    <span>HS / Packaging</span>
                    <strong>{record.hs_code || '-'}</strong>
                    <small>{record.packaging_type || '-'}</small>
                </div>
                <div>
                    <span>Stock Health</span>
                    <strong>{stock.toLocaleString()} available</strong>
                    <small>{stock <= reorder ? 'Below or near reorder level' : 'Healthy against reorder level'}</small>
                </div>
                <div>
                    <span>Pricing</span>
                    <strong><Money value={record.selling_price} /></strong>
                    <small>Margin <Money value={margin} /></small>
                </div>
                <div>
                    <span>Notes</span>
                    <strong>{record.notes || 'No notes'}</strong>
                    <small>{record.group_name || record.generic_name || 'No group set'}</small>
                </div>
            </div>
        );
    }

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
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="Division"
                        options={meta.divisions?.map((item) => ({ value: item.id, label: item.code ? `${item.name} (${item.code})` : item.name }))}
                        onChange={(division_id) => table.setFilters((filters) => ({ ...filters, division_id }))}
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
                <ServerTable
                    table={table}
                    columns={columns}
                    expandable={{ expandedRowRender: productExpandedRow }}
                />
            </Card>
        );
    }

    function sectionBody() {
        if (sectionConfig.master) {
            return <InventoryMasterTable master={sectionConfig.master} />;
        }

        if (section === 'stock-adjustment') {
            return <StockAdjustmentsPanel />;
        }

        if (section === 'stock-ledger') {
            return <StockMovementsPanel />;
        }

        return productList();
    }

    return (
        <div className="page-stack">
            {section === 'products' ? (
                <PageHeader
                    actions={(
                    <Space wrap>
                        <ExportButtons basePath={endpoints.inventoryProductsExport} params={{ search: table.search, ...table.filters }} />
                        <ImportButton target="products" />
                        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Product</Button>
                    </Space>
                    )}
                />
            ) : null}

            {sectionBody()}

            {historyProduct ? (
                <ProductHistoryDrawer
                    key={historyProduct.id}
                    product={historyProduct}
                    onClose={() => setHistoryProduct(null)}
                />
            ) : null}

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
                        <Form.Item name="division_id" label="Division">
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                placeholder="Select division"
                                options={meta.divisions?.map((item) => ({ value: item.id, label: item.code ? `${item.name} (${item.code})` : item.name }))}
                            />
                        </Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="product_code" label="Product Code"><Input placeholder="Optional unique code" /></Form.Item>
                        <Form.Item name="hs_code" label="HS Code"><Input placeholder="HS / customs code" /></Form.Item>
                        <Form.Item name="sku" label="SKU"><Input /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item label="Barcode">
                            <Space.Compact block>
                                <Form.Item name="barcode" noStyle>
                                    <BarcodeInput placeholder="Optional barcode" />
                                </Form.Item>
                                <Button onClick={generateBarcode}>Generate</Button>
                            </Space.Compact>
                        </Form.Item>
                    </div>
                    <BarcodePreview form={form} editing={editing} />
                    <div className="form-grid form-grid-3">
                        <Form.Item name="name" label="Product Name" rules={[{ required: true }]}><Input placeholder="e.g. Paracetamol 500mg" /></Form.Item>
                        <Form.Item name="generic_name" label="Generic Name"><Input /></Form.Item>
                        <Form.Item name="packaging_type" label="Packaging Type"><Input placeholder="Box, strip, bottle..." /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="group_name" label="Group Name"><Input /></Form.Item>
                        <Form.Item name="manufacturer_name" label="Manufacturer"><Input /></Form.Item>
                        <Form.Item name="strength" label="Strength"><Input /></Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
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
                        <CalculatedPricing form={form} />
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="reorder_level" label="Reorder Level" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="reorder_quantity" label="Reorder Qty"><InputNumber min={0} className="full-width" /></Form.Item>
                        <div className="switch-row switch-row-inline">
                            <Form.Item name="is_batch_tracked" label="Batch Tracked" valuePropName="checked"><Switch /></Form.Item>
                            <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                        </div>
                    </div>

                    <Divider orientation="left">Notes and Image</Divider>
                    <Form.Item name="notes" label="Internal Notes"><Input.TextArea rows={3} /></Form.Item>
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
                </Form>
            </Modal>
        </div>
    );
}
