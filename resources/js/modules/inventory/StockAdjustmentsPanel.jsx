import React, { useEffect, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Select, Space } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { QuickDropdownOptionModal } from '../../core/components/QuickDropdownOptionModal';
import { QuickProductModal } from '../../core/components/QuickProductModal';
import { ServerTable } from '../../core/components/ServerTable';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { endpoints } from '../../core/api/endpoints';
import { apiErrorMessage, formErrors, http } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';

const defaultAdjustmentTypes = [
    { value: 'add', label: 'Add Stock' },
    { value: 'subtract', label: 'Subtract Stock' },
    { value: 'expired', label: 'Expired Stock' },
    { value: 'damaged', label: 'Damaged Stock' },
    { value: 'return', label: 'Returned to Stock' },
];

export function StockAdjustmentsPanel() {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [products, setProducts] = useState([]);
    const [batches, setBatches] = useState([]);
    const [editing, setEditing] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [deletingId, setDeletingId] = useState(null);
    const [adjustmentTypes, setAdjustmentTypes] = useState(defaultAdjustmentTypes);
    const [quickProductOpen, setQuickProductOpen] = useState(false);
    const [quickAdjustmentOpen, setQuickAdjustmentOpen] = useState(false);
    const [range, setRange] = useState([]);
    const table = useServerTable({ endpoint: endpoints.stockAdjustments });
    const productId = Form.useWatch('product_id', form);

    useEffect(() => {
        searchProducts('');
        loadBatches();
        loadAdjustmentTypes();
        form.setFieldsValue({ adjustment_date: dayjs(), adjustment_type: 'add' });
    }, []);

    useEffect(() => {
        loadBatches(productId);
        if (!editing || Number(productId) !== Number(editing.product_id)) {
            form.setFieldValue('batch_id', null);
        }
    }, [productId]);

    useEffect(() => {
        table.setFilters((filters) => applyDateRangeFilter(filters, range));
    }, [range]);

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProducts(data.data || []);
    }

    async function loadBatches(product_id = null) {
        const { data } = await http.get(endpoints.inventoryBatchOptions, { params: { product_id } });
        setBatches(data.data || []);
    }

    async function loadAdjustmentTypes() {
        const { data } = await http.get(endpoints.dropdownOptions, { params: { alias: 'adjustment_type' } });
        const activeOptions = (data.data || [])
            .filter((option) => option.alias === 'adjustment_type' && option.is_active)
            .map((option) => ({ value: option.name, label: option.name, effect: option.data }));

        setAdjustmentTypes(activeOptions.length ? activeOptions : defaultAdjustmentTypes);
    }

    function productOptions() {
        return products.map((product) => ({ value: product.id, label: `${product.name}${product.sku ? ` (${product.sku})` : ''}` }));
    }

    function batchOptions() {
        return batches.map((batch) => ({
            value: batch.id,
            label: `${batch.batch_no} | Qty: ${Number(batch.quantity_available || 0).toFixed(3)} | Exp: ${batch.expires_at || '-'}`,
            batch,
        }));
    }

    async function submit(values) {
        setSaving(true);
        try {
            const payload = {
                ...values,
                adjustment_date: values.adjustment_date.format('YYYY-MM-DD'),
            };
            if (editing) {
                await http.put(`${endpoints.stockAdjustments}/${editing.id}`, payload);
                notification.success({ message: 'Stock adjustment updated' });
            } else {
                await http.post(endpoints.stockAdjustments, payload);
                notification.success({ message: 'Stock adjustment posted' });
            }
            form.resetFields();
            form.setFieldsValue({ adjustment_date: dayjs(), adjustment_type: 'add' });
            setEditing(null);
            setModalOpen(false);
            table.reload();
            loadBatches(productId);
        } catch (error) {
            form.setFields(formErrors(error));
            notification.error({ message: 'Adjustment failed', description: apiErrorMessage(error) });
        } finally {
            setSaving(false);
        }
    }

    function openEdit(record) {
        setEditing(record);
        if (record.product && !products.some((product) => product.id === record.product.id)) {
            setProducts((current) => [{ id: record.product.id, name: record.product.name }, ...current]);
        }
        if (record.batch && !batches.some((batch) => batch.id === record.batch.id)) {
            setBatches((current) => [{ id: record.batch.id, batch_no: record.batch.batch_no, quantity_available: record.batch.quantity_available }, ...current]);
        }
        form.setFieldsValue({
            ...record,
            adjustment_date: record.adjustment_date ? dayjs(record.adjustment_date) : dayjs(),
        });
        setModalOpen(true);
    }

    function openCreate() {
        setEditing(null);
        form.resetFields();
        form.setFieldsValue({ adjustment_date: dayjs(), adjustment_type: adjustmentTypes[0]?.value || 'add', quantity: 1 });
        setModalOpen(true);
    }

    function remove(record) {
        confirmDelete({
            title: 'Delete adjustment?',
            content: 'This will reverse the stock effect from the selected batch.',
            onOk: async () => {
                setDeletingId(record.id);
                try {
                    await http.delete(`${endpoints.stockAdjustments}/${record.id}`);
                    notification.success({ message: 'Adjustment deleted' });
                    table.reload();
                    loadBatches(productId);
                } catch (error) {
                    notification.error({ message: 'Adjustment delete failed', description: apiErrorMessage(error) });
                } finally {
                    setDeletingId(null);
                }
            },
        });
    }

    const columns = [
        { title: 'Date', dataIndex: 'adjustment_date', width: 130 },
        { title: 'Product', dataIndex: ['product', 'name'], width: 260 },
        { title: 'Batch', dataIndex: ['batch', 'batch_no'], width: 150 },
        {
            title: 'Type',
            dataIndex: 'adjustment_type',
            width: 180,
            render: (value) => (
                <PharmaBadge tone={String(value || '').includes('out') || String(value || '').includes('loss') || String(value || '').includes('subtract') ? 'warning' : 'info'}>
                    {adjustmentTypes.find((item) => item.value === value)?.label || defaultAdjustmentTypes.find((item) => item.value === value)?.label || value}
                </PharmaBadge>
            ),
        },
        { title: 'Qty', dataIndex: 'quantity', align: 'right', width: 110 },
        { title: 'Reason', dataIndex: 'reason', width: 300 },
        { title: 'By', dataIndex: ['adjusted_by', 'name'], width: 150 },
        {
            title: 'Action',
            fixed: 'right',
            width: 110,
            render: (_, record) => (
                <Space>
                    <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openEdit(record)} />
                    <Button aria-label="Delete" danger loading={deletingId === record.id} disabled={deletingId === record.id} icon={<DeleteOutlined />} onClick={() => remove(record)} />
                </Space>
            ),
        },
    ];

    return (
        <div className="page-stack">
            <Card
                title="Recent Adjustments"
                extra={<Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Adjustment</Button>}
            >
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search product, batch, type or reason" allowClear />
                    <Select
                        allowClear
                        placeholder="Type"
                        options={adjustmentTypes}
                        onChange={(adjustment_type) => table.setFilters((filters) => ({ ...filters, adjustment_type }))}
                    />
                    <SmartDatePicker.RangePicker value={range} onChange={setRange} placeholder={['Adjusted from', 'Adjusted to']} />
                    <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                </div>
                <ServerTable table={table} columns={columns} />
            </Card>
            <Modal
                title={editing ? 'Edit Stock Adjustment' : 'Adjustment Form'}
                open={modalOpen}
                onCancel={() => !saving && setModalOpen(false)}
                onOk={() => form.submit()}
                confirmLoading={saving}
                okButtonProps={{ disabled: saving }}
                cancelButtonProps={{ disabled: saving }}
                okText={editing ? 'Update Adjustment' : 'Save Adjustment'}
                destroyOnHidden
                width={760}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <div className="form-grid">
                        <Form.Item name="product_id" label="Product" rules={[{ required: true }]}>
                            <Select
                                showSearch
                                filterOption={false}
                                onFocus={() => searchProducts('')}
                                onSearch={searchProducts}
                                options={productOptions()}
                                dropdownRender={(menu) => (
                                    <>
                                        {menu}
                                        <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickProductOpen(true)}>Quick add product</Button>
                                    </>
                                )}
                            />
                        </Form.Item>
                        <Form.Item name="batch_id" label="Batch" rules={[{ required: true }]}>
                            <Select showSearch optionFilterProp="label" options={batchOptions()} />
                        </Form.Item>
                    </div>
                    <div className="form-grid form-grid-3">
                        <Form.Item name="adjustment_type" label="Adjustment Type" rules={[{ required: true }]}>
                            <Select
                                options={adjustmentTypes}
                                dropdownRender={(menu) => (
                                    <>
                                        {menu}
                                        <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickAdjustmentOpen(true)}>Quick add adjustment type</Button>
                                    </>
                                )}
                            />
                        </Form.Item>
                        <Form.Item name="quantity" label="Quantity" rules={[{ required: true }]}>
                            <InputNumber min={0.001} className="full-width" />
                        </Form.Item>
                        <Form.Item name="adjustment_date" label="Date" rules={[{ required: true }]}>
                            <SmartDatePicker className="full-width" />
                        </Form.Item>
                    </div>
                    <Form.Item name="reason" label="Reason"><Input placeholder="Write short reason" /></Form.Item>
                </Form>
            </Modal>
            <QuickProductModal
                open={quickProductOpen}
                onClose={() => setQuickProductOpen(false)}
                onCreated={(product) => {
                    setProducts((current) => [product, ...current.filter((item) => item.id !== product.id)]);
                    form.setFieldValue('product_id', product.id);
                    loadBatches(product.id);
                }}
            />
            <QuickDropdownOptionModal
                alias="adjustment_type"
                title="Quick Add Adjustment Type"
                open={quickAdjustmentOpen}
                onClose={() => setQuickAdjustmentOpen(false)}
                onCreated={(option) => {
                    const next = { value: option.name, label: option.name, effect: option.data };
                    setAdjustmentTypes((current) => [next, ...current.filter((item) => item.value !== next.value)]);
                    form.setFieldValue('adjustment_type', next.value);
                }}
            />
        </div>
    );
}
