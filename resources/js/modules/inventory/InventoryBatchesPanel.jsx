import React, { useEffect, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Select, Space, Statistic, Switch } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { DateText } from '../../core/components/DateText';
import { ExportButtons } from '../../core/components/ListToolbarActions';
import { FormDrawer } from '../../core/components/FormDrawer';
import { Money } from '../../core/components/Money';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { StatusTag } from '../../core/components/StatusTag';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';

const expiryOptions = [
    { value: 'available', label: 'Available Stock' },
    { value: 'expired', label: 'Expired' },
    { value: '30d', label: 'Expiring in 30 days' },
    { value: '60d', label: 'Expiring in 60 days' },
];

const quantityFormatter = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 3,
});

function formatQuantity(value) {
    return quantityFormatter.format(Number(value || 0));
}

function expiryBadge(value) {
    if (!value) {
        return <span>-</span>;
    }

    const days = dayjs(value).startOf('day').diff(dayjs().startOf('day'), 'day');
    let tone = 'success';
    let label = 'Healthy';

    if (days < 0) {
        tone = 'danger';
        label = 'Expired';
    } else if (days <= 30) {
        tone = 'danger';
        label = `${days}d`;
    } else if (days <= 90) {
        tone = 'warning';
        label = `${days}d`;
    }

    return (
        <Space size={6} wrap>
            <DateText value={value} style="compact" />
            <PharmaBadge tone={tone}>{label}</PharmaBadge>
        </Space>
    );
}

function stockBadge(value) {
    const quantity = Number(value || 0);

    if (quantity <= 0) {
        return <PharmaBadge tone="danger">{formatQuantity(quantity)}</PharmaBadge>;
    }

    if (quantity <= 5) {
        return <PharmaBadge tone="warning">{formatQuantity(quantity)}</PharmaBadge>;
    }

    return <span className="tabular">{formatQuantity(quantity)}</span>;
}

export function InventoryBatchesPanel() {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [suppliers, setSuppliers] = useState([]);
    const [products, setProducts] = useState([]);
    const [expiryRange, setExpiryRange] = useState([]);
    const table = useServerTable({
        endpoint: endpoints.inventoryBatches,
        defaultSort: { field: 'expires_at', order: 'asc' },
        defaultFilters: { is_active: 1 },
    });

    useEffect(() => {
        loadSuppliers();
        searchProducts('');
    }, []);

    useEffect(() => {
        table.setFilters((filters) => applyDateRangeFilter(filters, expiryRange));
    }, [expiryRange]);

    async function loadSuppliers() {
        const { data } = await http.get(endpoints.supplierOptions);
        setSuppliers(data.data || []);
    }

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProducts(data.data || []);
    }

    function productOptions() {
        return products.map((product) => ({
            value: product.id,
            label: `${product.name}${product.sku ? ` (${product.sku})` : ''}`,
        }));
    }

    function openCreate() {
        setEditing(null);
        form.resetFields();
        form.setFieldsValue({
            expires_at: dayjs().add(1, 'year'),
            quantity_received: 1,
            quantity_available: 1,
            purchase_price: 0,
            mrp: 0,
            is_active: true,
        });
        setDrawerOpen(true);
    }

    function openEdit(record) {
        setEditing(record);
        if (record.product && !products.some((product) => product.id === record.product.id)) {
            setProducts((current) => [{ id: record.product.id, name: record.product.name, sku: record.product.sku }, ...current]);
        }
        form.setFieldsValue({
            ...record,
            manufactured_at: record.manufactured_at ? dayjs(record.manufactured_at) : null,
            expires_at: record.expires_at ? dayjs(record.expires_at) : null,
        });
        setDrawerOpen(true);
    }

    async function submit(values) {
        setSaving(true);
        try {
            const payload = {
                ...values,
                manufactured_at: values.manufactured_at?.format('YYYY-MM-DD'),
                expires_at: values.expires_at?.format('YYYY-MM-DD'),
            };
            if (editing) {
                await http.put(`${endpoints.inventoryBatches}/${editing.id}`, payload);
                notification.success({ message: 'Batch updated' });
            } else {
                await http.post(endpoints.inventoryBatches, payload);
                notification.success({ message: 'Batch created' });
            }
            setDrawerOpen(false);
            table.reload();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Batch save failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    function remove(record) {
        confirmDelete({
            title: 'Remove batch?',
            content: `${record.batch_no} will be hidden from active batch lists.`,
            onOk: async () => {
                await http.delete(`${endpoints.inventoryBatches}/${record.id}`);
                notification.success({ message: 'Batch removed' });
                table.reload();
            },
        });
    }

    const columns = [
        { title: 'Batch', dataIndex: 'batch_no', field: 'batch_no', sorter: true, width: 150 },
        { title: 'Product', dataIndex: ['product', 'name'], width: 260 },
        { title: 'Supplier', dataIndex: ['supplier', 'name'], width: 180 },
        { title: 'Expiry', dataIndex: 'expires_at', field: 'expires_at', sorter: true, width: 180, render: expiryBadge },
        { title: 'Storage', dataIndex: 'storage_location', width: 140, render: (value) => value || '-' },
        { title: 'Available', dataIndex: 'quantity_available', field: 'quantity_available', sorter: true, align: 'right', width: 120, render: stockBadge },
        { title: 'Received', dataIndex: 'quantity_received', align: 'right', width: 120, render: (value) => <span className="tabular">{formatQuantity(value)}</span> },
        { title: 'Purchase', dataIndex: 'purchase_price', field: 'purchase_price', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
        { title: 'MRP', dataIndex: 'mrp', field: 'mrp', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
        { title: 'Status', dataIndex: 'is_active', width: 110, render: (value) => <StatusTag active={value} /> },
        {
            title: 'Action',
            fixed: 'right',
            width: 110,
            render: (_, record) => (
                <Space>
                    <Button aria-label="Edit batch" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                    <Button aria-label="Remove batch" danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                </Space>
            ),
        },
    ];

    const summary = table.extra.summary || {};

    return (
        <div className="page-stack">
            <Card
                title="Batch List"
                extra={(
                    <Space wrap>
                        <ExportButtons basePath={endpoints.inventoryBatchesExport} params={{ search: table.search, ...table.filters }} />
                        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Batch</Button>
                    </Space>
                )}
            >
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search batch, product or supplier" allowClear />
                    <Select
                        allowClear
                        showSearch
                        filterOption={false}
                        onSearch={searchProducts}
                        placeholder="Product"
                        options={productOptions()}
                        onChange={(product_id) => table.setFilters((filters) => ({ ...filters, product_id }))}
                    />
                    <Select
                        allowClear
                        placeholder="Supplier"
                        options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(supplier_id) => table.setFilters((filters) => ({ ...filters, supplier_id }))}
                    />
                    <Select
                        allowClear
                        placeholder="Expiry"
                        options={expiryOptions}
                        onChange={(expiry_status) => table.setFilters((filters) => ({ ...filters, expiry_status }))}
                    />
                    <SmartDatePicker.RangePicker value={expiryRange} onChange={setExpiryRange} placeholder={['Expiry from', 'Expiry to']} />
                    <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                </div>
                <ServerTable table={table} columns={columns} />
            </Card>
            <FormDrawer
                title={editing ? 'Edit Batch' : 'Add Batch'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                footer={<Button type="primary" loading={saving} onClick={() => form.submit()} block>{editing ? 'Update' : 'Save'}</Button>}
                width={760}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <div className="form-grid">
                        <Form.Item name="product_id" label="Product" rules={[{ required: true }]}>
                            <Select showSearch filterOption={false} onSearch={searchProducts} options={productOptions()} />
                        </Form.Item>
                        <Form.Item name="supplier_id" label="Supplier" rules={[{ required: true }]}>
                            <Select allowClear showSearch optionFilterProp="label" options={suppliers.map((item) => ({ value: item.id, label: item.name }))} />
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="batch_no" label="Batch No" rules={[{ required: true }]}><Input /></Form.Item>
                        <Form.Item name="barcode" label="Barcode"><Input /></Form.Item>
                    </div>
                    <Form.Item name="storage_location" label="Storage"><Input placeholder="Rack A-1" /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name="manufactured_at" label="Manufactured"><SmartDatePicker className="full-width" /></Form.Item>
                        <Form.Item name="expires_at" label="Expiry" rules={[{ required: true }]}><SmartDatePicker className="full-width" /></Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="quantity_received" label="Received Qty" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="quantity_available" label="Available Qty"><InputNumber min={0} className="full-width" /></Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="purchase_price" label="Purchase Price" rules={[{ required: true }]}><InputNumber min={0} className="full-width" /></Form.Item>
                        <Form.Item name="mrp" label="MRP"><InputNumber min={0} className="full-width" /></Form.Item>
                    </div>
                    <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </FormDrawer>
        </div>
    );
}
