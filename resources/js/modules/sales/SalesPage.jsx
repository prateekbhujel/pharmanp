import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, DatePicker, Form, Input, InputNumber, Modal, Select, Space, Table, Tabs } from 'antd';
import { DeleteOutlined, PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { QuickProductModal } from '../../core/components/QuickProductModal';
import { ServerTable } from '../../core/components/ServerTable';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';

export function SalesPage() {
    const { notification } = App.useApp();
    const [barcode, setBarcode] = useState('');
    const [items, setItems] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [customerId, setCustomerId] = useState(null);
    const [invoiceDate, setInvoiceDate] = useState(dayjs());
    const [paidAmount, setPaidAmount] = useState(0);
    const [lastPrintUrl, setLastPrintUrl] = useState(null);
    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);
    const [quickProductOpen, setQuickProductOpen] = useState(false);
    const [customerForm] = Form.useForm();
    const invoiceTable = useServerTable({ endpoint: endpoints.salesInvoices });

    useEffect(() => {
        loadCustomers();
    }, []);

    async function loadCustomers() {
        const { data } = await http.get(endpoints.customerOptions);
        setCustomers(data.data);
    }

    async function scan(value) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { barcode: value } });
        const product = data.data?.[0];
        const batch = product?.batches?.[0];

        if (!product || !batch) {
            notification.warning({ message: 'No saleable stock found for barcode' });
            return;
        }

        addItem(product, batch);
        setBarcode('');
    }

    async function searchProduct(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        return (data.data || []).flatMap((product) => (product.batches || []).map((batch) => ({
            value: `${product.id}:${batch.id}`,
            label: `${product.name} | ${batch.batch_no} | stock ${batch.quantity_available}`,
            product,
            batch,
        })));
    }

    const [productOptions, setProductOptions] = useState([]);

    function addItem(product, batch) {
        setItems((current) => {
            const existing = current.find((item) => item.product_id === product.id && item.batch_id === batch.id);
            if (existing) {
                return current.map((item) => item === existing ? { ...item, quantity: item.quantity + 1 } : item);
            }

            return [...current, {
                key: `${product.id}:${batch.id}`,
                product_id: product.id,
                batch_id: batch.id,
                name: product.name,
                batch_no: batch.batch_no,
                expires_at: batch.expires_at,
                stock_on_hand: batch.quantity_available,
                quantity: 1,
                unit_price: product.selling_price || batch.mrp || product.mrp,
                discount_percent: 0,
            }];
        });
    }

    function updateItem(row, patch) {
        setItems((current) => current.map((item) => item.key === row.key ? { ...item, ...patch } : item));
    }

    const total = useMemo(() => items.reduce((sum, item) => {
        const gross = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
        return sum + gross - (gross * (Number(item.discount_percent) || 0) / 100);
    }, 0), [items]);

    async function submitInvoice() {
        try {
            const { data } = await http.post(endpoints.salesInvoices, {
                customer_id: customerId,
                invoice_date: invoiceDate.format('YYYY-MM-DD'),
                sale_type: 'pos',
                paid_amount: paidAmount,
                items,
            });
            notification.success({ message: 'Invoice posted and stock deducted' });
            setItems([]);
            setPaidAmount(0);
            setLastPrintUrl(data.print_url);
            invoiceTable.reload();
        } catch (error) {
            notification.error({ message: 'Invoice failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function submitCustomer(values) {
        try {
            const { data } = await http.post(endpoints.customers, values);
            await loadCustomers();
            setCustomerId(data.data.id);
            customerForm.resetFields();
            setQuickCustomerOpen(false);
            notification.success({ message: 'Customer added' });
        } catch (error) {
            customerForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Customer save failed', description: error?.response?.data?.message || error.message });
        }
    }

    const columns = [
        { title: 'Product', dataIndex: 'name' },
        { title: 'Batch', dataIndex: 'batch_no', width: 130 },
        { title: 'Expiry', dataIndex: 'expires_at', width: 120 },
        { title: 'Stock', dataIndex: 'stock_on_hand', align: 'right', width: 100 },
        {
            title: 'Qty',
            dataIndex: 'quantity',
            width: 120,
            render: (value, row) => <InputNumber min={0.001} max={row.stock_on_hand} value={value} onChange={(quantity) => updateItem(row, { quantity })} />,
        },
        { title: 'Rate', dataIndex: 'unit_price', align: 'right', width: 130, render: (value, row) => <InputNumber min={0} value={value} onChange={(unit_price) => updateItem(row, { unit_price })} /> },
        { title: 'Disc %', dataIndex: 'discount_percent', align: 'right', width: 110, render: (value, row) => <InputNumber min={0} max={100} value={value} onChange={(discount_percent) => updateItem(row, { discount_percent })} /> },
        { title: 'Line Total', align: 'right', width: 140, render: (_, row) => <Money value={(row.quantity || 0) * (row.unit_price || 0) * (1 - ((row.discount_percent || 0) / 100))} /> },
        { title: '', width: 70, render: (_, row) => <Button danger icon={<DeleteOutlined />} onClick={() => setItems((current) => current.filter((item) => item.key !== row.key))} /> },
    ];
    const invoiceColumns = [
        { title: 'Invoice', dataIndex: 'invoice_no', field: 'invoice_no', sorter: true },
        { title: 'Date', dataIndex: 'invoice_date', field: 'invoice_date', sorter: true, width: 130 },
        { title: 'Customer', dataIndex: ['customer', 'name'], render: (value) => value || 'Walk-in' },
        { title: 'Payment', dataIndex: 'payment_status', width: 120 },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (value) => <Money value={value} /> },
        { title: '', width: 90, render: (_, row) => <Button icon={<PrinterOutlined />} onClick={() => window.open(`/sales/invoices/${row.id}/print`, '_blank')}>Print</Button> },
    ];

    return (
        <div className="page-stack">
            <PageHeader
                title="Sales / POS"
                description="Barcode-first POS with batch and expiry aware stock deduction"
                actions={(
                    <Space>
                        <Button icon={<PlusOutlined />} onClick={() => setQuickProductOpen(true)}>Quick Product</Button>
                        <Button disabled={!lastPrintUrl} icon={<PrinterOutlined />} onClick={() => window.open(lastPrintUrl, '_blank')}>Print Last Invoice</Button>
                    </Space>
                )}
            />

            <Tabs items={[
                {
                    key: 'pos',
                    label: 'POS',
                    children: (
                        <Card>
                            <div className="pos-toolbar pos-toolbar-wide">
                                <BarcodeInput value={barcode} onChange={setBarcode} onScan={scan} />
                                <Select
                                    allowClear
                                    placeholder="Walk-in Customer"
                                    value={customerId}
                                    onChange={setCustomerId}
                                    options={customers.map((item) => ({ value: item.id, label: item.name }))}
                                    dropdownRender={(menu) => (
                                        <>
                                            {menu}
                                            <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickCustomerOpen(true)}>Quick add customer</Button>
                                        </>
                                    )}
                                />
                                <DatePicker value={invoiceDate} onChange={setInvoiceDate} />
                                <InputNumber min={0} value={paidAmount} onChange={setPaidAmount} placeholder="Paid" />
                            </div>
                            <Select
                                showSearch
                                filterOption={false}
                                placeholder="Search product and batch"
                                className="full-width mb-16"
                                options={productOptions}
                                onSearch={(q) => searchProduct(q).then(setProductOptions)}
                                onFocus={() => searchProduct('').then(setProductOptions)}
                                dropdownRender={(menu) => (
                                    <>
                                        {menu}
                                        <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickProductOpen(true)}>Quick add product</Button>
                                    </>
                                )}
                                onChange={(_, option) => addItem(option.product, option.batch)}
                                value={null}
                            />
                            <Table rowKey="key" columns={columns} dataSource={items} pagination={false} scroll={{ x: 1040 }} />
                            <div className="pos-total">
                                <span>Invoice Total</span>
                                <strong><Money value={total} /></strong>
                                <Button type="primary" disabled={!items.length} onClick={submitInvoice}>Post Invoice</Button>
                            </div>
                        </Card>
                    ),
                },
                {
                    key: 'invoices',
                    label: 'Invoices',
                    children: (
                        <Card>
                            <div className="table-toolbar">
                                <Input.Search value={invoiceTable.search} onChange={(event) => invoiceTable.setSearch(event.target.value)} placeholder="Search invoice or customer" allowClear />
                                <span />
                                <Button onClick={invoiceTable.reload}>Refresh</Button>
                            </div>
                            <ServerTable table={invoiceTable} columns={invoiceColumns} />
                        </Card>
                    ),
                },
            ]} />
            <QuickProductModal
                open={quickProductOpen}
                onClose={() => setQuickProductOpen(false)}
                onCreated={() => searchProduct('').then(setProductOptions)}
            />
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
