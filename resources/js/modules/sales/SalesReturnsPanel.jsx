import React, { useEffect, useMemo, useRef, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Select, Space, Switch } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, PrinterOutlined, ReloadOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { DateText } from '../../core/components/DateText';
import { Money } from '../../core/components/Money';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { validationErrorsByLine } from '../../core/utils/lineItems';
import { appUrl } from '../../core/utils/url';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';
import { useKeyboardFlow } from '../../core/hooks/useKeyboardFlow';

const emptyReturnItem = {
    sales_invoice_item_id: null,
    product_id: null,
    product_name: '',
    batch_id: null,
    batch_no: '',
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

function defaultReturnType() {
    return window.location.pathname.includes('expiry-returns') ? 'expiry' : 'regular';
}

export function SalesReturnsPanel() {
    const { notification } = App.useApp();
    const [form] = Form.useForm();
    const [customerForm] = Form.useForm();
    const [customers, setCustomers] = useState([]);
    const [products, setProducts] = useState([]);
    const [invoices, setInvoices] = useState([]);
    const [items, setItems] = useState([]);
    const [lineErrors, setLineErrors] = useState({});
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [view, setView] = useState('list');
    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);
    const [range, setRange] = useState([]);
    const returnFormRef = useRef(null);
    const table = useServerTable({
        endpoint: endpoints.salesReturns,
        defaultSort: { field: 'return_date', order: 'desc' },
    });
    const customerId = Form.useWatch('customer_id', form);
    const returnMode = Form.useWatch('return_mode', form) || 'invoice';
    const deletedMode = Boolean(table.filters.deleted);

    useEffect(() => {
        form.setFieldsValue({ return_date: dayjs(), return_mode: 'invoice', return_type: defaultReturnType() });
        if (defaultReturnType() === 'expiry') {
            table.setFilters((filters) => ({ ...filters, return_type: 'expiry' }));
        }
        loadCustomers();
        searchProducts('');
    }, []);

    useEffect(() => {
        if (customerId) {
            loadInvoices(customerId);
        } else {
            setInvoices([]);
        }
    }, [customerId]);

    useEffect(() => {
        table.setFilters((filters) => applyDateRangeFilter(filters, range));
    }, [range]);

    useKeyboardFlow(returnFormRef, {
        enabled: view === 'form',
        autofocus: view === 'form',
        onSubmit: () => form.submit(),
        onAddRow: addManualRow,
        resetKey: view,
    });

    async function loadCustomers() {
        const { data } = await http.get(endpoints.customerOptions);
        setCustomers(data.data || []);
    }

    async function loadInvoices(nextCustomerId) {
        const { data } = await http.get(endpoints.salesReturnInvoiceOptions, { params: { customer_id: nextCustomerId } });
        setInvoices(data.data || []);
    }

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProducts(data.data || []);
    }

    function updateRow(index, patch) {
        setItems((rows) => rows.map((row, rowIndex) => rowIndex === index ? normalizeRow({ ...row, ...patch }) : row));
    }

    async function selectProduct(index, product_id, option) {
        const product = option?.product || products.find((p) => p.id === product_id);
        const batches = product?.batches || [];
        const selectedBatch = batches[0] || null;
        updateRow(index, {
            product_id,
            product_name: product?.name || option?.label,
            batch_options: batches.map((b) => ({
                id: b.id,
                label: `${b.batch_no} | Qty: ${Number(b.quantity_available || 0).toFixed(3)}`,
                quantity_available: Number(b.quantity_available || 0),
            })),
            batch_id: selectedBatch?.id || null,
            batch_no: selectedBatch?.batch_no || '',
            max_returnable: selectedBatch?.quantity_available || 0,
            rate: product?.selling_price || selectedBatch?.mrp || product?.mrp || 0,
            net_rate: product?.selling_price || selectedBatch?.mrp || product?.mrp || 0,
        });
    }

    function selectBatch(index, batch_id) {
        const row = items[index];
        const batch = row.batch_options?.find((item) => item.id === batch_id);
        updateRow(index, {
            batch_id,
            batch_no: batch?.batch_no || '',
            max_returnable: batch?.quantity_available || 0,
        });
    }

    function addManualRow() {
        if (!form.getFieldValue('customer_id')) {
            notification.warning({ message: 'Choose customer first' });
            return;
        }
        setItems((rows) => [...rows, { ...emptyReturnItem, locked: false }]);
    }

    async function loadInvoiceItems() {
        const invoiceId = form.getFieldValue('sales_invoice_id');
        if (!invoiceId) {
            notification.warning({ message: 'Choose sales invoice first' });
            return;
        }
        const { data } = await http.get(endpoints.salesReturnInvoiceItems(invoiceId));
        setItems((data.data || []).filter((row) => Number(row.max_returnable || row.quantity || 0) > 0).map((row) => normalizeRow({
            ...row,
            product_name: row.product_name || row.product?.name,
            batch_no: row.batch_no || row.batch?.batch_no || '',
            rate: row.unit_price || row.rate || 0,
            net_rate: row.unit_price || row.rate || 0,
            max_returnable: Number(row.max_returnable || row.quantity || 0),
            return_qty: 0,
            locked: true,
        })));
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
                customer_id: values.customer_id,
                sales_invoice_id: values.return_mode === 'invoice' ? values.sales_invoice_id : null,
                return_type: values.return_type || 'regular',
                return_date: values.return_date.format('YYYY-MM-DD'),
                reason: values.reason,
                notes: values.notes,
                items: items
                    .filter((row) => Number(row.return_qty || 0) > 0)
                    .map((row) => ({
                        sales_invoice_item_id: row.sales_invoice_item_id || row.id,
                        product_id: row.product_id,
                        batch_id: row.batch_id,
                        quantity: row.return_qty,
                        unit_price: row.net_rate || row.rate,
                        rate: row.rate,
                        discount_percent: row.discount_percent,
                        discount_amount: row.discount_amount,
                        net_rate: row.net_rate,
                    })),
            };
            const { data } = editing
                ? await http.put(`${endpoints.salesReturns}/${editing.id}`, payload)
                : await http.post(endpoints.salesReturns, payload);
            notification.success({ message: editing ? 'Sales return updated' : 'Sales return posted' });
            setEditing(null);
            setItems([]);
            setLineErrors({});
            form.resetFields();
            form.setFieldsValue({ return_date: dayjs(), return_mode: 'invoice', return_type: defaultReturnType() });
            setView('list');
            table.reload();
            if (data?.print_url) {
                window.open(data.print_url, '_blank');
            }
        } catch (error) {
            const errors = validationErrors(error);
            setLineErrors(validationErrorsByLine(errors, 'items'));
            form.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Sales return failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    async function editReturn(row) {
        setView('form');
        const { data } = await http.get(`${endpoints.salesReturns}/${row.id}`);
        const record = data.data;
        setEditing(record);
        form.setFieldsValue({
            customer_id: record.customer_id,
            sales_invoice_id: record.sales_invoice_id,
            return_mode: record.sales_invoice_id ? 'invoice' : 'product',
            return_type: record.return_type || 'regular',
            return_date: dayjs(record.return_date),
            reason: record.reason,
            notes: record.notes,
        });
        if (record.customer_id) {
            loadInvoices(record.customer_id);
        }
        setItems((record.items || []).map((item) => normalizeRow({
            ...item,
            product_name: item.product?.name || item.product_name,
            batch_no: item.batch?.batch_no || item.batch_no || '',
            batch_options: item.batch ? [{
                id: item.batch.id,
                label: `${item.batch.batch_no} | Qty: ${Number(item.batch.quantity_available || 0).toFixed(3)}`,
                quantity_available: Number(item.batch.quantity_available || 0) + Number(item.return_qty || item.quantity || 0),
            }] : [],
            max_returnable: item.batch
                ? Number(item.batch.quantity_available || 0) + Number(item.return_qty || item.quantity || 0)
                : Number(item.return_qty || item.quantity || 0),
            return_qty: item.return_qty || item.quantity || 0,
            rate: item.rate || item.unit_price || 0,
            net_rate: item.net_rate || item.unit_price || 0,
            locked: Boolean(record.sales_invoice_id),
        })));
    }

    function deleteReturn(row) {
        confirmDelete({
            title: 'Delete sales return?',
            content: `${row.return_no || 'This return'} will be removed and stock restored.`,
            onOk: async () => {
                await http.delete(`${endpoints.salesReturns}/${row.id}`);
                notification.success({ message: 'Sales return deleted' });
                table.reload();
            },
        });
    }

    async function submitCustomer(values) {
        try {
            const { data } = await http.post(endpoints.customers, values);
            await loadCustomers();
            form.setFieldValue('customer_id', data.data.id);
            customerForm.resetFields();
            setQuickCustomerOpen(false);
            notification.success({ message: 'Customer added' });
        } catch (error) {
            customerForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Customer save failed', description: error?.response?.data?.message || error.message });
        }
    }

    const productOptions = products.flatMap((product) => {
        const batches = product.batches || [];
        if (batches.length === 0) {
            return [{
                value: product.id,
                label: `${product.name}${product.sku ? ` (${product.sku})` : ''}`,
                product,
            }];
        }
        return batches.map((batch) => ({
            value: `${product.id}:${batch.id}`,
            label: `${product.name} | ${batch.batch_no} | stock ${batch.quantity_available}`,
            product,
            batch,
        }));
    });

    const lineColumns = [
        {
            key: 'product',
            title: 'Product',
            width: 280,
            render: (row, index) => row.locked ? (
                <div>
                    <strong>{row.product_name}</strong>
                    <span className="line-muted-note">Loaded from invoice{row.batch_no ? ` • Batch ${row.batch_no}` : ''}</span>
                </div>
            ) : (
                <Select
                    showSearch
                    filterOption={false}
                    onSearch={searchProducts}
                    value={row.product_id}
                    options={productOptions.map((opt) => ({ value: opt.product?.id || opt.value, label: opt.label, product: opt.product }))}
                    onChange={(product_id, option) => selectProduct(index, product_id, option)}
                    className="full-width"
                />
            ),
        },
        {
            key: 'batch',
            title: 'Batch',
            width: 220,
            render: (row, index) => row.locked ? (
                <span>{row.batch_no || '—'}</span>
            ) : (
                <Select
                    value={row.batch_id}
                    options={(row.batch_options || []).map((batch) => ({ value: batch.id, label: batch.label || batch.batch_no }))}
                    onChange={(batch_id) => selectBatch(index, batch_id)}
                    className="full-width"
                />
            ),
        },
        { key: 'available', title: 'Available', render: (row) => Number(row.max_returnable || 0).toFixed(3), width: 110 },
        { key: 'already_returned', title: 'Returned', render: (row) => Number(row.already_returned || 0).toFixed(3), width: 110 },
        { key: 'return_qty', title: 'Return Qty', width: 120, render: (row, index) => <InputNumber min={0} max={row.max_returnable || undefined} value={row.return_qty} onChange={(return_qty) => updateRow(index, { return_qty })} /> },
        { key: 'rate', title: 'Rate', width: 120, render: (row, index) => <InputNumber min={0} value={row.rate} onChange={(rate) => updateRow(index, { rate, net_rate: rate - (rate * Number(row.discount_percent || 0) / 100) })} /> },
        { key: 'discount_percent', title: 'Disc %', width: 100, render: (row, index) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateRow(index, { discount_percent, net_rate: Number(row.rate || 0) - (Number(row.rate || 0) * Number(discount_percent || 0) / 100) })} /> },
        { key: 'net_rate', title: 'Net Rate', width: 120, render: (row, index) => <InputNumber min={0} value={row.net_rate} onChange={(net_rate) => updateRow(index, { net_rate })} /> },
        { key: 'amount', title: 'Amount', className: 'line-money-cell', width: 130, render: (row) => <Money value={amount(row)} /> },
    ];

    const listColumns = [
        { title: 'Return No', dataIndex: 'return_no', field: 'return_no', sorter: true, width: 170 },
        { title: 'Date', dataIndex: 'return_date', field: 'return_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Type', dataIndex: 'return_type', field: 'return_type', sorter: true, width: 130, render: (value) => <PharmaBadge tone={value === 'expiry' ? 'warning' : 'info'}>{value === 'expiry' ? 'Expiry' : 'Regular'}</PharmaBadge> },
        { title: 'Customer', dataIndex: 'customer_name', width: 220, render: (v, row) => v || row.customer?.name || 'Walk-in' },
        { title: 'Invoice', dataIndex: 'invoice_no', width: 180, render: (v, row) => v || row.sales_invoice?.invoice_no || 'Manual' },
        { title: 'Items', dataIndex: 'items_count', width: 80, align: 'center' },
        { title: 'Total', dataIndex: 'total_amount', field: 'total_amount', sorter: true, align: 'right', width: 130, render: (v, row) => <Money value={v || row.grand_total} /> },
        { title: 'Reason', dataIndex: 'reason', ellipsis: true, width: 160 },
        {
            title: 'Action',
            fixed: 'right',
            width: 240,
            render: (_, row) => (
                row.deleted_at ? (
                    <PharmaBadge tone="deleted">Deleted</PharmaBadge>
                ) : (
                    <Space>
                        <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => editReturn(row)} />
                        <Button icon={<PrinterOutlined />} onClick={() => window.open(appUrl(`/sales/returns/${row.id}/print`), '_blank')}>Print</Button>
                        <Button danger icon={<DeleteOutlined />} onClick={() => deleteReturn(row)} />
                    </Space>
                )
            ),
        },
    ];

    return (
        <div className="page-stack">
            {view === 'form' ? (
                <Card title={editing ? `Edit ${editing.return_no || 'Sales Return'}` : 'Sales Return Entry'}>
                    <div ref={returnFormRef} data-keyboard-flow="true">
                        <Form form={form} layout="vertical" onFinish={submit}>
                            <div className="form-grid form-grid-4">
                                <Form.Item name="customer_id" label="Customer" rules={[{ required: true }]}>
                                    <Select
                                        showSearch
                                        optionFilterProp="label"
                                        options={customers.map((item) => ({ value: item.id, label: item.name }))}
                                        dropdownRender={(menu) => (
                                            <>
                                                {menu}
                                                <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickCustomerOpen(true)}>Quick add customer</Button>
                                            </>
                                        )}
                                    />
                                </Form.Item>
                                <Form.Item name="return_mode" label="Return Mode" rules={[{ required: true }]}>
                                    <Select options={[
                                        { value: 'invoice', label: 'By Sales Invoice' },
                                        { value: 'product', label: 'By Product / Batch' },
                                    ]} />
                                </Form.Item>
                                <Form.Item name="return_type" label="Return Type" rules={[{ required: true }]}>
                                    <Select options={[
                                        { value: 'regular', label: 'Sales Return' },
                                        { value: 'expiry', label: 'Sales Expiry Return' },
                                    ]} />
                                </Form.Item>
                                <Form.Item name="return_date" label="Return Date" rules={[{ required: true }]}>
                                    <SmartDatePicker className="full-width" />
                                </Form.Item>
                                <Form.Item name="reason" label="Reason"><Input /></Form.Item>
                            </div>
                            <div className="form-grid">
                                <Form.Item name="notes" label="Notes"><Input.TextArea rows={1} /></Form.Item>
                            </div>
                            {returnMode === 'invoice' && (
                                <div className="form-grid">
                                    <Form.Item name="sales_invoice_id" label="Sales Invoice" rules={[{ required: returnMode === 'invoice' }]}>
                                        <Select
                                            showSearch
                                            optionFilterProp="label"
                                            options={invoices.map((inv) => ({
                                                value: inv.id,
                                                label: `${inv.invoice_no} - NPR ${inv.grand_total}`,
                                            }))}
                                        />
                                    </Form.Item>
                                    <Form.Item label=" "><Button icon={<ReloadOutlined />} onClick={loadInvoiceItems}>Load Invoice Items</Button></Form.Item>
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
                                        <Button onClick={() => { setView('list'); setEditing(null); setItems([]); form.resetFields(); form.setFieldsValue({ return_date: dayjs(), return_mode: 'invoice', return_type: defaultReturnType() }); }}>Cancel</Button>
                                        <Button type="primary" htmlType="submit" loading={saving}>{editing ? 'Update Return' : 'Post Return'}</Button>
                                    </Space>
                                )}
                            />
                        </Form>
                    </div>
                </Card>
            ) : (
                <Card title="Sales Return List" extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => { setEditing(null); setItems([]); form.resetFields(); form.setFieldsValue({ return_date: dayjs(), return_mode: 'invoice', return_type: defaultReturnType() }); setView('form'); }}>New Return</Button>}>
                    <div className="table-toolbar table-toolbar-wide">
                        <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search return, customer or invoice" allowClear />
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Customer"
                            options={customers.map((item) => ({ value: item.id, label: item.name }))}
                            onChange={(customer_id) => table.setFilters((filters) => ({ ...filters, customer_id }))}
                        />
                        <Select
                            allowClear
                            placeholder="Return Type"
                            options={[
                                { value: 'regular', label: 'Regular' },
                                { value: 'expiry', label: 'Expiry' },
                            ]}
                            onChange={(return_type) => table.setFilters((filters) => ({ ...filters, return_type }))}
                        />
                        <SmartDatePicker.RangePicker value={range} onChange={setRange} />
                        <label className="table-switch">
                            <Switch
                                checked={deletedMode}
                                onChange={(deleted) => table.setFilters((filters) => ({ ...filters, deleted: deleted ? 1 : undefined }))}
                            />
                            <span>View Deleted</span>
                        </label>
                        <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                    </div>
                    <ServerTable table={table} columns={listColumns} />
                </Card>
            )}

            <Modal
                title="Quick Add Customer"
                open={quickCustomerOpen}
                onCancel={() => setQuickCustomerOpen(false)}
                onOk={() => customerForm.submit()}
                destroyOnHidden
            >
                <Form form={customerForm} layout="vertical" onFinish={submitCustomer}>
                    <Form.Item name="name" label="Customer Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
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
