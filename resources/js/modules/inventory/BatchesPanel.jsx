import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Select, Space, Statistic, Switch } from 'antd';
import { AuditOutlined, DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined, StopOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { FormDrawer } from '../../core/components/FormDrawer';
import { Money } from '../../core/components/Money';
import { PharmaBadge, StatusBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { apiErrorMessage, apiSuccessMessage, formErrors, http } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { appUrl } from '../../core/utils/url';

const expiryFilters = [
    { value: 'available', label: 'Available stock' },
    { value: 'expired', label: 'Expired' },
    { value: '30d', label: 'Expiring in 30 days' },
    { value: '60d', label: 'Expiring in 60 days' },
];

function batchDefaults() {
    return {
        quantity_received: 0,
        quantity_available: 0,
        purchase_price: 0,
        mrp: 0,
        is_active: true,
    };
}

function batchFormValues(record) {
    return {
        ...record,
        manufactured_at: record.manufactured_at ? dayjs(record.manufactured_at) : null,
        expires_at: record.expires_at ? dayjs(record.expires_at) : null,
        adjustment_reason: '',
    };
}

function batchPayload(values) {
    return {
        ...values,
        manufactured_at: values.manufactured_at?.format?.('YYYY-MM-DD') || null,
        expires_at: values.expires_at?.format?.('YYYY-MM-DD') || null,
    };
}

function expiryTone(value) {
    if (value === 'expired') return 'danger';
    if (String(value || '').startsWith('expiring')) return 'warning';

    return 'success';
}

export function BatchesPanel() {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [products, setProducts] = useState([]);
    const [suppliers, setSuppliers] = useState([]);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [closingId, setClosingId] = useState(null);
    const availableQuantity = Form.useWatch('quantity_available', form);
    const table = useServerTable({
        endpoint: endpoints.inventoryBatches,
        defaultSort: { field: 'expires_at', order: 'asc' },
    });

    useEffect(() => {
        searchProducts('');
        loadSuppliers();
    }, []);

    async function searchProducts(search) {
        const { data } = await http.get(endpoints.products, { params: { search, per_page: 20 } });
        setProducts(data.data || []);
    }

    async function loadSuppliers() {
        const { data } = await http.get(endpoints.supplierOptions);
        setSuppliers(data.data || []);
    }

    function openCreate() {
        setEditing(null);
        form.resetFields();
        form.setFieldsValue(batchDefaults());
        setDrawerOpen(true);
    }

    function openEdit(record) {
        setEditing(record);
        if (record.product && !products.some((product) => product.id === record.product.id)) {
            setProducts((current) => [record.product, ...current]);
        }
        if (record.supplier && !suppliers.some((supplier) => supplier.id === record.supplier.id)) {
            setSuppliers((current) => [record.supplier, ...current]);
        }
        form.setFieldsValue(batchFormValues(record));
        setDrawerOpen(true);
    }

    async function submit(values) {
        setSaving(true);
        try {
            const response = editing
                ? await http.put(`${endpoints.inventoryBatches}/${editing.id}`, batchPayload(values))
                : await http.post(endpoints.inventoryBatches, batchPayload(values));

            notification.success({ message: apiSuccessMessage(response, editing ? 'Batch updated' : 'Batch created') });
            setDrawerOpen(false);
            setEditing(null);
            table.reload();
        } catch (error) {
            form.setFields(formErrors(error));
            notification.error({
                message: editing ? 'Batch update failed' : 'Batch create failed',
                description: apiErrorMessage(error),
            });
        } finally {
            setSaving(false);
        }
    }

    function closeOrDelete(record) {
        confirmDelete({
            title: record.has_history ? 'Close batch?' : 'Delete batch?',
            content: record.has_history
                ? `${record.batch_no} has stock history, so it will be closed and hidden from sale selection.`
                : `${record.batch_no} will be removed from the batch register.`,
            okText: record.has_history ? 'Close Batch' : 'Delete',
            danger: !record.has_history,
            onOk: async () => {
                setClosingId(record.id);
                try {
                    const response = await http.delete(`${endpoints.inventoryBatches}/${record.id}`);
                    notification.success({ message: apiSuccessMessage(response, record.has_history ? 'Batch closed' : 'Batch deleted') });
                    table.reload();
                } catch (error) {
                    notification.error({ message: 'Batch action failed', description: apiErrorMessage(error) });
                } finally {
                    setClosingId(null);
                }
            },
        });
    }

    function openLedger(record) {
        window.history.pushState({}, '', appUrl(`/app/inventory/stock-ledger?batch_id=${record.id}`));
        window.dispatchEvent(new PopStateEvent('popstate'));
    }

    const summary = table.extra.summary || {};
    const quantityChanged = editing
        ? Number(availableQuantity || 0) !== Number(editing.quantity_available || 0)
        : false;

    const columns = useMemo(() => [
        { title: 'Batch', dataIndex: 'batch_no', field: 'batch_no', sorter: true, width: 150, fixed: 'left' },
        { title: 'Product', dataIndex: ['product', 'name'], width: 260 },
        { title: 'Supplier', dataIndex: ['supplier', 'name'], width: 220 },
        {
            title: 'Expiry',
            dataIndex: 'expires_at',
            field: 'expires_at',
            sorter: true,
            width: 140,
            render: (value, record) => (
                <Space direction="vertical" size={2}>
                    <DateText value={value} style="compact" />
                    <PharmaBadge tone={expiryTone(record.expiry_status)}>{String(record.expiry_status || 'valid').replaceAll('_', ' ')}</PharmaBadge>
                </Space>
            ),
        },
        { title: 'Available', dataIndex: 'quantity_available', field: 'quantity_available', sorter: true, align: 'right', width: 120 },
        { title: 'Received', dataIndex: 'quantity_received', align: 'right', width: 120 },
        { title: 'Purchase', dataIndex: 'purchase_price', field: 'purchase_price', sorter: true, align: 'right', width: 130, render: (value) => <Money value={value} /> },
        { title: 'MRP', dataIndex: 'mrp', field: 'mrp', sorter: true, align: 'right', width: 120, render: (value) => <Money value={value} /> },
        { title: 'Location', dataIndex: 'storage_location', width: 150 },
        { title: 'Status', dataIndex: 'is_active', width: 120, render: (value) => <StatusBadge value={value} /> },
        {
            title: 'Action',
            fixed: 'right',
            width: 150,
            render: (_, record) => (
                <Space>
                    <Button aria-label="Stock ledger" icon={<AuditOutlined />} onClick={() => openLedger(record)} />
                    <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                    <Button
                        aria-label={record.has_history ? 'Close' : 'Delete'}
                        danger={!record.has_history}
                        loading={closingId === record.id}
                        disabled={closingId === record.id}
                        icon={record.has_history ? <StopOutlined /> : <DeleteOutlined />}
                        onClick={() => closeOrDelete(record)}
                    />
                </Space>
            ),
        },
    ], [closingId, products, suppliers]);

    return (
        <div className="page-stack">
            <div className="batch-summary-grid">
                <Card><Statistic title="Total Batches" value={summary.total_batches || 0} /></Card>
                <Card><Statistic title="Total Stock" value={summary.total_stock || 0} precision={3} /></Card>
                <Card><Statistic title="Expired" value={summary.expired_batches || 0} valueStyle={{ color: '#dc2626' }} /></Card>
                <Card><Statistic title="Expiring in 30 Days" value={summary.expiring_30 || 0} valueStyle={{ color: '#d97706' }} /></Card>
            </div>
            <Card
                title="Batch Register"
                extra={<Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>New Batch</Button>}
            >
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search batch, barcode, product, supplier or location" allowClear />
                    <Select
                        allowClear
                        showSearch
                        filterOption={false}
                        placeholder="Product"
                        options={products.map((item) => ({ value: item.id, label: item.name }))}
                        onSearch={searchProducts}
                        onFocus={() => searchProducts('')}
                        onChange={(product_id) => table.setFilters((filters) => ({ ...filters, product_id }))}
                    />
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="Supplier"
                        options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(supplier_id) => table.setFilters((filters) => ({ ...filters, supplier_id }))}
                    />
                    <Select
                        allowClear
                        placeholder="Expiry"
                        options={expiryFilters}
                        onChange={(expiry_status) => table.setFilters((filters) => ({ ...filters, expiry_status }))}
                    />
                    <Select
                        allowClear
                        placeholder="Status"
                        options={[{ value: 1, label: 'Active' }, { value: 0, label: 'Inactive' }]}
                        onChange={(is_active) => table.setFilters((filters) => ({ ...filters, is_active }))}
                    />
                    <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                </div>
                <ServerTable table={table} columns={columns} />
            </Card>

            <FormDrawer
                title={editing ? `Edit Batch ${editing.batch_no}` : 'New Batch'}
                open={drawerOpen}
                onClose={() => !saving && setDrawerOpen(false)}
                footer={(
                    <Space className="drawer-footer-actions">
                        <Button onClick={() => setDrawerOpen(false)} disabled={saving}>Cancel</Button>
                        <Button type="primary" loading={saving} disabled={saving} onClick={() => form.submit()}>
                            {editing ? 'Update Batch' : 'Save Batch'}
                        </Button>
                    </Space>
                )}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Card title="Batch Metadata" className="settings-inner-card">
                        <div className="form-grid">
                            <Form.Item name="product_id" label="Product" rules={[{ required: true, message: 'Select product' }]}>
                                <Select
                                    showSearch
                                    filterOption={false}
                                    options={products.map((item) => ({ value: item.id, label: item.name }))}
                                    onSearch={searchProducts}
                                    onFocus={() => searchProducts('')}
                                />
                            </Form.Item>
                            <Form.Item name="supplier_id" label="Supplier">
                                <Select allowClear showSearch optionFilterProp="label" options={suppliers.map((item) => ({ value: item.id, label: item.name }))} />
                            </Form.Item>
                        </div>
                        <div className="form-grid">
                            <Form.Item name="batch_no" label="Batch No" rules={[{ required: true, message: 'Enter batch number' }]}><Input /></Form.Item>
                            <Form.Item name="barcode" label="Barcode"><Input /></Form.Item>
                        </div>
                        <div className="form-grid">
                            <Form.Item name="manufactured_at" label="Manufactured Date"><SmartDatePicker /></Form.Item>
                            <Form.Item name="expires_at" label="Expiry Date" rules={[{ required: true, message: 'Select expiry date' }]}><SmartDatePicker /></Form.Item>
                        </div>
                        <div className="form-grid">
                            <Form.Item name="storage_location" label="Storage Location"><Input placeholder="Rack, shelf or room" /></Form.Item>
                            <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                        </div>
                    </Card>
                    <Card title="Stock and Pricing" className="settings-inner-card">
                        <div className="form-grid form-grid-4">
                            <Form.Item name="quantity_received" label="Received Qty" rules={[{ required: true, message: 'Enter received quantity' }]}><InputNumber min={0} precision={3} className="full-width" /></Form.Item>
                            <Form.Item name="quantity_available" label="Available Qty" rules={[{ required: true, message: 'Enter available quantity' }]}><InputNumber min={0} precision={3} className="full-width" /></Form.Item>
                            <Form.Item name="purchase_price" label="Purchase Price" rules={[{ required: true, message: 'Enter purchase price' }]}><InputNumber min={0} precision={2} className="full-width" /></Form.Item>
                            <Form.Item name="mrp" label="MRP"><InputNumber min={0} precision={2} className="full-width" /></Form.Item>
                        </div>
                        <Form.Item
                            name="adjustment_reason"
                            label="Stock Adjustment Reason"
                            extra={editing ? 'Required when available quantity changes. The movement ledger will store this reason.' : 'Used as the opening batch movement note.'}
                            rules={quantityChanged ? [{ required: true, message: 'Write a stock adjustment reason' }] : []}
                        >
                            <Input.TextArea rows={3} placeholder="Physical count correction, opening stock, import correction..." />
                        </Form.Item>
                    </Card>
                </Form>
            </FormDrawer>
        </div>
    );
}
