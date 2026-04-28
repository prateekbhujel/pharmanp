import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Radio, Select, Space } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, PrinterOutlined, ReloadOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { DateText } from '../../core/components/DateText';
import { Money } from '../../core/components/Money';
import { ServerTable } from '../../core/components/ServerTable';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { validationErrorsByLine } from '../../core/utils/lineItems';
import { appUrl } from '../../core/utils/url';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';

const emptyReturnItem = {
    purchase_item_id: null,
    product_id: null,
    product_name: '',
    batch_id: null,
    batch_options: [],
    original_qty: 0,
    already_returned: 0,
    max_returnable: 0,
    return_qty: 1,
    rate: 0,
    discount_percent: 0,
    discount_amount: 0,
    net_rate: 0,
    return_amount: 0,
    locked: false,
};

function amount(row) {
    const qty = Number(row.return_qty || 0);
    const rate = Number(row.rate || 0);
    const netRate = row.net_rate === null || row.net_rate === undefined
        ? rate - (rate * Number(row.discount_percent || 0) / 100)
        : Number(row.net_rate || 0);

    return Number((qty * netRate).toFixed(2));
}

function normalizeRow(row) {
    const rate = Number(row.rate || 0);
    const discount = Number(row.discount_percent || 0);
    const netRate = Number(row.net_rate ?? (rate - (rate * discount / 100)));
    const returnQty = Number(row.return_qty || 0);

    return {
        ...emptyReturnItem,
        ...row,
        return_qty: returnQty,
        rate,
        discount_percent: discount,
        net_rate: Number(netRate.toFixed(2)),
        discount_amount: Number((Math.max(0, rate - netRate) * returnQty).toFixed(2)),
        return_amount: Number((returnQty * netRate).toFixed(2)),
    };
}

export function PurchaseReturnsPanel() {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [supplierForm] = Form.useForm();
    const [suppliers, setSuppliers] = useState([]);
    const [products, setProducts] = useState([]);
    const [purchases, setPurchases] = useState([]);
    const [items, setItems] = useState([]);
    const [lineErrors, setLineErrors] = useState({});
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [view, setView] = useState('list');
    const [quickSupplierOpen, setQuickSupplierOpen] = useState(false);
    const [range, setRange] = useState([]);
    const table = useServerTable({
        endpoint: endpoints.purchaseReturns,
        defaultSort: { field: 'return_date', order: 'desc' },
    });
    const supplierId = Form.useWatch('supplier_id', form);
    const returnMode = Form.useWatch('return_mode', form) || 'bill';

    useEffect(() => {
        form.setFieldsValue({ return_date: dayjs(), return_mode: 'bill' });
        loadSuppliers();
        searchProducts('');
    }, []);

    useEffect(() => {
        if (supplierId) {
            loadPurchases(supplierId);
        } else {
            setPurchases([]);
        }
    }, [supplierId]);

    useEffect(() => {
        table.setFilters((filters) => applyDateRangeFilter(filters, range));
    }, [range]);

    async function loadSuppliers() {
        const { data } = await http.get(endpoints.supplierOptions);
        setSuppliers(data.data || []);
    }

    async function loadPurchases(nextSupplierId) {
        const { data } = await http.get(endpoints.purchaseReturnPurchases, { params: { supplier_id: nextSupplierId } });
        setPurchases(data.data || []);
    }

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProducts(data.data || []);
    }

    async function supplierBatches(product_id) {
        const { data } = await http.get(endpoints.purchaseReturnBatches, {
            params: { supplier_id: form.getFieldValue('supplier_id'), product_id },
        });

        return data.data || [];
    }

    function updateRow(index, patch) {
        setItems((rows) => rows.map((row, rowIndex) => rowIndex === index ? normalizeRow({ ...row, ...patch }) : row));
    }

    async function selectProduct(index, product_id, option) {
        const batches = await supplierBatches(product_id);
        const selectedBatch = batches[0] || null;
        updateRow(index, {
            product_id,
            product_name: option?.label,
            batch_options: batches,
            batch_id: selectedBatch?.id || null,
            max_returnable: selectedBatch?.quantity_available || 0,
            rate: selectedBatch?.purchase_price || option?.product?.purchase_price || 0,
            net_rate: selectedBatch?.purchase_price || option?.product?.purchase_price || 0,
        });
    }

    function selectBatch(index, batch_id) {
        const row = items[index];
        const batch = row.batch_options?.find((item) => item.id === batch_id);
        updateRow(index, {
            batch_id,
            max_returnable: batch?.quantity_available || 0,
            rate: batch?.purchase_price ?? row.rate,
            net_rate: batch?.purchase_price ?? row.net_rate,
        });
    }

    function addManualRow() {
        if (!form.getFieldValue('supplier_id')) {
            notification.warning({ message: 'Choose supplier first' });
            return;
        }
        setItems((rows) => [...rows, { ...emptyReturnItem, locked: false }]);
    }

    async function loadBillItems() {
        const purchaseId = form.getFieldValue('purchase_id');
        if (!purchaseId) {
            notification.warning({ message: 'Choose purchase bill first' });
            return;
        }
        const { data } = await http.get(endpoints.purchaseReturnItems(purchaseId));
        setItems((data.data || []).filter((row) => Number(row.max_returnable || 0) > 0).map((row) => normalizeRow({ ...row, locked: true })));
        setLineErrors({});
    }

    function removeRow(index) {
        setItems((rows) => rows.filter((_, rowIndex) => rowIndex !== index));
    }

    const summary = useMemo(() => {
        return items.reduce((carry, row) => {
            const qty = Number(row.return_qty || 0);
            const rate = Number(row.rate || 0);
            const rowAmount = amount(row);
            const gross = qty * rate;

            carry.qty += qty;
            carry.subtotal += gross;
            carry.discount += Math.max(0, gross - rowAmount);
            carry.total += rowAmount;

            return carry;
        }, { qty: 0, subtotal: 0, discount: 0, total: 0 });
    }, [items]);

    async function submit(values) {
        setSaving(true);
        try {
            const payload = {
                supplier_id: values.supplier_id,
                purchase_id: values.return_mode === 'bill' ? values.purchase_id : null,
                return_date: values.return_date.format('YYYY-MM-DD'),
                notes: values.notes,
                items: items.map((row) => ({
                    purchase_item_id: row.purchase_item_id,
                    product_id: row.product_id,
                    batch_id: row.batch_id,
                    return_qty: row.return_qty,
                    rate: row.rate,
                    discount_percent: row.discount_percent,
                    discount_amount: row.discount_amount,
                    net_rate: row.net_rate,
                })),
            };
            const { data } = editing
                ? await http.put(`${endpoints.purchaseReturns}/${editing.id}`, payload)
                : await http.post(endpoints.purchaseReturns, payload);
            notification.success({ message: editing ? 'Purchase return updated' : 'Purchase return posted' });
            setEditing(null);
            setItems([]);
            setLineErrors({});
            form.resetFields();
            form.setFieldsValue({ return_date: dayjs(), return_mode: 'bill' });
            setView('list');
            table.reload();
            if (data?.print_url) {
                window.open(data.print_url, '_blank');
            }
        } catch (error) {
            const errors = validationErrors(error);
            setLineErrors(validationErrorsByLine(errors, 'items'));
            form.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Purchase return failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    async function editReturn(row) {
        setView('form');
        const { data } = await http.get(`${endpoints.purchaseReturns}/${row.id}`);
        const record = data.data;
        setEditing(record);
        form.setFieldsValue({
            supplier_id: record.supplier_id,
            purchase_id: record.purchase_id,
            return_mode: record.purchase_id ? 'bill' : 'product',
            return_date: dayjs(record.return_date),
            notes: record.notes,
        });
        if (record.supplier_id) {
            loadPurchases(record.supplier_id);
        }
        setItems((record.items || []).map((item) => normalizeRow({
            ...item,
            product_name: item.product?.name,
            batch_options: item.batch ? [{
                id: item.batch.id,
                label: `${item.batch.batch_no} | Qty: ${Number(item.batch.quantity_available || 0).toFixed(3)}`,
                quantity_available: Number(item.batch.quantity_available || 0) + Number(item.return_qty || 0),
                purchase_price: item.rate,
            }] : [],
            max_returnable: item.batch ? Number(item.batch.quantity_available || 0) + Number(item.return_qty || 0) : Number(item.return_qty || 0),
            locked: Boolean(record.purchase_id),
        })));
    }

    function deleteReturn(row) {
        confirmDelete({
            title: 'Delete purchase return?',
            content: `${row.return_no} will be removed and stock restored.`,
            onOk: async () => {
                await http.delete(`${endpoints.purchaseReturns}/${row.id}`);
                notification.success({ message: 'Purchase return deleted' });
                table.reload();
            },
        });
    }

    async function submitSupplier(values) {
        try {
            const { data } = await http.post(endpoints.suppliers, values);
            await loadSuppliers();
            form.setFieldValue('supplier_id', data.data.id);
            supplierForm.resetFields();
            setQuickSupplierOpen(false);
            notification.success({ message: 'Supplier added' });
        } catch (error) {
            supplierForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Supplier save failed', description: error?.response?.data?.message || error.message });
        }
    }

    const productOptions = products.map((product) => ({
        value: product.id,
        label: `${product.name}${product.sku ? ` (${product.sku})` : ''}`,
        product,
    }));

    const lineColumns = [
        {
            key: 'product',
            title: 'Product',
            width: 280,
            render: (row, index) => row.locked ? (
                <div>
                    <strong>{row.product_name}</strong>
                    <span className="line-muted-note">Loaded from purchase bill</span>
                </div>
            ) : (
                <Select
                    showSearch
                    filterOption={false}
                    onSearch={searchProducts}
                    value={row.product_id}
                    options={productOptions}
                    onChange={(product_id, option) => selectProduct(index, product_id, option)}
                    className="full-width"
                />
            ),
        },
        {
            key: 'batch',
            title: 'Batch',
            width: 220,
            render: (row, index) => (
                <Select
                    value={row.batch_id}
                    options={(row.batch_options || []).map((batch) => ({ value: batch.id, label: batch.label || batch.batch_no }))}
                    onChange={(batch_id) => selectBatch(index, batch_id)}
                    className="full-width"
                />
            ),
        },
        { key: 'available', title: 'Available', render: (row) => Number(row.max_returnable || 0).toFixed(3), width: 110 },
        { key: 'returned', title: 'Returned', render: (row) => Number(row.already_returned || 0).toFixed(3), width: 110 },
        { key: 'return_qty', title: 'Return Qty', width: 120, render: (row, index) => <InputNumber min={0.001} max={row.max_returnable || undefined} value={row.return_qty} onChange={(return_qty) => updateRow(index, { return_qty })} /> },
        { key: 'rate', title: 'Rate', width: 120, render: (row, index) => <InputNumber min={0} value={row.rate} onChange={(rate) => updateRow(index, { rate, net_rate: rate - (rate * Number(row.discount_percent || 0) / 100) })} /> },
        { key: 'discount_percent', title: 'Disc %', width: 100, render: (row, index) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateRow(index, { discount_percent, net_rate: Number(row.rate || 0) - (Number(row.rate || 0) * Number(discount_percent || 0) / 100) })} /> },
        { key: 'net_rate', title: 'Net Rate', width: 120, render: (row, index) => <InputNumber min={0} value={row.net_rate} onChange={(net_rate) => updateRow(index, { net_rate })} /> },
        { key: 'amount', title: 'Amount', className: 'line-money-cell', width: 130, render: (row) => <Money value={amount(row)} /> },
    ];

    const listColumns = [
        { title: 'Return No', dataIndex: 'return_no', field: 'return_no', sorter: true, width: 170 },
        { title: 'Date', dataIndex: 'return_date', field: 'return_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Supplier', dataIndex: ['supplier', 'name'], width: 220 },
        { title: 'Purchase', dataIndex: ['purchase', 'purchase_no'], width: 180, render: (value) => value || 'Manual' },
        { title: 'Total', dataIndex: 'grand_total', field: 'grand_total', sorter: true, align: 'right', width: 130, render: (value) => <Money value={value} /> },
        {
            title: 'Action',
            fixed: 'right',
            width: 240,
            render: (_, row) => (
                <Space>
                    <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => editReturn(row)} />
                    <Button icon={<PrinterOutlined />} onClick={() => window.open(appUrl(`/purchase-returns/${row.id}/print`), '_blank')}>Print</Button>
                    <Button danger icon={<DeleteOutlined />} onClick={() => deleteReturn(row)} />
                </Space>
            ),
        },
    ];

    return (
        <div className="page-stack">
            {view === 'form' ? (
                <Card title={editing ? `Edit ${editing.return_no}` : 'Purchase Return Entry'}>
                    <Form form={form} layout="vertical" onFinish={submit}>
                        <div className="form-grid form-grid-4">
                            <Form.Item name="supplier_id" label="Supplier" rules={[{ required: true }]}>
                                <Select
                                    showSearch
                                    optionFilterProp="label"
                                    options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                                    dropdownRender={(menu) => (
                                        <>
                                            {menu}
                                            <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickSupplierOpen(true)}>Quick add supplier</Button>
                                        </>
                                    )}
                                />
                            </Form.Item>
                            <Form.Item name="return_mode" label="Return Mode" rules={[{ required: true }]}>
                                <Radio.Group optionType="button" buttonStyle="solid" options={[
                                    { value: 'bill', label: 'By Purchase Bill' },
                                    { value: 'product', label: 'By Product / Batch' },
                                ]} />
                            </Form.Item>
                            <Form.Item name="return_date" label="Return Date" rules={[{ required: true }]}>
                                <SmartDatePicker className="full-width" />
                            </Form.Item>
                            <Form.Item name="notes" label="Notes"><Input /></Form.Item>
                        </div>
                        {returnMode === 'bill' && (
                            <div className="form-grid">
                                <Form.Item name="purchase_id" label="Purchase Bill" rules={[{ required: returnMode === 'bill' }]}>
                                    <Select options={purchases.map((item) => ({ value: item.id, label: item.label }))} />
                                </Form.Item>
                                <Form.Item label=" "><Button icon={<ReloadOutlined />} onClick={loadBillItems}>Load Bill Items</Button></Form.Item>
                            </div>
                        )}
                        <TransactionLineItems
                            rows={items}
                            columns={lineColumns}
                            errors={lineErrors}
                            addLabel="Add Manual Item"
                            onAdd={addManualRow}
                            onRemove={removeRow}
                            minRows={0}
                            summary={[
                                { label: 'Total Qty', value: Number(summary.qty || 0).toFixed(3) },
                                { label: 'Gross Return', value: <Money value={summary.subtotal} /> },
                                { label: 'Discount', value: <Money value={summary.discount} /> },
                                { label: 'Net Return', value: <Money value={summary.total} />, strong: true },
                            ]}
                            actions={(
                                <Space>
                                    <Button onClick={() => { setView('list'); setEditing(null); setItems([]); form.resetFields(); form.setFieldsValue({ return_date: dayjs(), return_mode: 'bill' }); }}>Cancel</Button>
                                    <Button type="primary" htmlType="submit" loading={saving}>{editing ? 'Update Return' : 'Post Return'}</Button>
                                </Space>
                            )}
                        />
                    </Form>
                </Card>
            ) : (
                <Card title="Purchase Return List" extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => { setEditing(null); setItems([]); form.resetFields(); form.setFieldsValue({ return_date: dayjs(), return_mode: 'bill' }); setView('form'); }}>New Return</Button>}>
                    <div className="table-toolbar table-toolbar-wide">
                        <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search return, supplier or purchase" allowClear />
                        <Select
                            allowClear
                            placeholder="Supplier"
                            options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                            onChange={(supplier_id) => table.setFilters((filters) => ({ ...filters, supplier_id }))}
                        />
                        <Select
                            allowClear
                            placeholder="Mode"
                            options={[
                                { value: 'bill', label: 'Bill' },
                                { value: 'product', label: 'By Product & Batch' },
                            ]}
                            onChange={(return_mode) => table.setFilters((filters) => ({ ...filters, return_mode }))}
                        />
                        <SmartDatePicker.RangePicker value={range} onChange={setRange} />
                        <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                    </div>
                    <ServerTable table={table} columns={listColumns} />
                </Card>
            )}

            <Modal
                title="Quick Add Supplier"
                open={quickSupplierOpen}
                onCancel={() => setQuickSupplierOpen(false)}
                onOk={() => supplierForm.submit()}
                destroyOnHidden
            >
                <Form form={supplierForm} layout="vertical" onFinish={submitSupplier}>
                    <Form.Item name="name" label="Supplier Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                        <Form.Item name="email" label="Email"><Input /></Form.Item>
                    </div>
                    <Form.Item name="address" label="Address"><Input /></Form.Item>
                </Form>
            </Modal>
        </div>
    );
}
