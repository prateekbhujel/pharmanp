import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Select, Space } from 'antd';
import { PlusOutlined, PrinterOutlined, QrcodeOutlined, UserOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { DateText } from '../../core/components/DateText';
import { PaymentStatusBadge, PharmaBadge, StatusBadge } from '../../core/components/PharmaBadge';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { QuickProductModal } from '../../core/components/QuickProductModal';
import { ServerTable } from '../../core/components/ServerTable';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { useServerTable } from '../../core/hooks/useServerTable';
import { itemFreeGoodsValue, itemNet, summarizeItems, validationErrorsByLine } from '../../core/utils/lineItems';
import { paymentStatusOptions } from '../../core/utils/accountCatalog';
import { appUrl } from '../../core/utils/url';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';
import { SalesReturnsPanel } from './SalesReturnsPanel';

function salesSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    if (['invoices', 'returns', 'pos'].includes(section)) {
        return section;
    }

    return 'invoices';
}

function goToApp(path) {
    window.history.pushState({}, '', appUrl(path));
    window.dispatchEvent(new PopStateEvent('popstate'));
}

export function SalesPage() {
    const { notification } = App.useApp();
    const section = salesSection();
    const [barcode, setBarcode] = useState('');
    const [items, setItems] = useState([]);
    const [lineErrors, setLineErrors] = useState({});
    const [customers, setCustomers] = useState([]);
    const [medicalRepresentatives, setMedicalRepresentatives] = useState([]);
    const [customerId, setCustomerId] = useState(null);
    const [medicalRepresentativeId, setMedicalRepresentativeId] = useState(undefined);
    const [invoiceDate, setInvoiceDate] = useState(dayjs());
    const [invoiceRange, setInvoiceRange] = useState([]);
    const [paidAmount, setPaidAmount] = useState(0);
    const [lastPrintUrl, setLastPrintUrl] = useState(null);
    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);
    const [quickProductOpen, setQuickProductOpen] = useState(false);
    const [qrVisible, setQrVisible] = useState(false);
    const [customerForm] = Form.useForm();
    const invoiceTable = useServerTable({
        endpoint: endpoints.salesInvoices,
        defaultSort: { field: 'invoice_date', order: 'desc' },
    });

    useEffect(() => {
        loadCustomers();
        loadMedicalRepresentatives();

        if (section === 'pos') {
            setTimeout(() => document.getElementById('pos-barcode-input')?.focus(), 300);
        }

        function handleKeyDown(event) {
            if (event.altKey && event.key === 's') {
                event.preventDefault();
                if (section === 'pos') submitInvoice();
            }
            if (event.altKey && event.key === 'n') {
                event.preventDefault();
                goToApp('/app/sales/pos');
            }
            if (event.altKey && event.key === 'a') {
                event.preventDefault();
                if (section === 'pos') {
                    searchProduct('').then(setProductOptions);
                    document.querySelector('.pos-product-search input')?.focus();
                }
            }
            if (section !== 'pos') return;
            switch (event.key) {
                case 'F2':
                    event.preventDefault();
                    document.getElementById('pos-barcode-input')?.focus();
                    break;
                case 'F3':
                    event.preventDefault();
                    document.querySelector('.pos-product-search input')?.focus();
                    break;
                case 'F4':
                    event.preventDefault();
                    document.getElementById('pos-paid-amount')?.focus();
                    break;
                case 'F8':
                    event.preventDefault();
                    submitInvoice();
                    break;
                case 'F9':
                    event.preventDefault();
                    setQuickProductOpen(true);
                    break;
            }
        }

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [section]);

    useEffect(() => {
        invoiceTable.setFilters((current) => applyDateRangeFilter(current, invoiceRange));
    }, [invoiceRange]);

    async function loadCustomers() {
        const { data } = await http.get(endpoints.customerOptions);
        setCustomers(data.data);
    }

    async function loadMedicalRepresentatives() {
        try {
            const { data } = await http.get(endpoints.mrOptions);
            setMedicalRepresentatives(data.data || []);
        } catch {
            setMedicalRepresentatives([]);
        }
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
                free_quantity: 0,
                mrp: batch.mrp || product.mrp || 0,
                unit_price: product.selling_price || batch.mrp || product.mrp,
                cc_rate: product.cc_rate || 0,
                discount_percent: 0,
            }];
        });
    }

    function updateItem(row, patch) {
        setItems((current) => current.map((item) => item.key === row.key ? { ...item, ...patch } : item));
    }

    const invoiceSummary = useMemo(() => summarizeItems(items, 'unit_price'), [items]);

    async function submitInvoice() {
        try {
            const { data } = await http.post(endpoints.salesInvoices, {
                customer_id: customerId,
                medical_representative_id: medicalRepresentativeId,
                invoice_date: invoiceDate.format('YYYY-MM-DD'),
                sale_type: 'pos',
                paid_amount: paidAmount,
                items,
            });
            notification.success({ message: 'Invoice posted and stock deducted' });
            setItems([]);
            setLineErrors({});
            setPaidAmount(0);
            setCustomerId(null);
            setLastPrintUrl(data.print_url);
            invoiceTable.reload();
        } catch (error) {
            setLineErrors(validationErrorsByLine(validationErrors(error), 'items'));
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
        {
            key: 'product',
            title: 'Product',
            width: 260,
            render: (row) => (
                <div>
                    <strong>{row.name}</strong>
                    <span className="line-muted-note">Batch {row.batch_no || '-'} | Expiry <DateText value={row.expires_at} style="compact" /> | Stock {row.stock_on_hand}</span>
                </div>
            ),
        },
        { key: 'quantity', title: 'Qty', render: (row) => <InputNumber min={0.001} max={row.stock_on_hand} value={row.quantity} onChange={(quantity) => updateItem(row, { quantity })} />, width: 105 },
        { key: 'free_quantity', title: 'Free Qty', render: (row) => <InputNumber min={0} value={row.free_quantity} onChange={(free_quantity) => updateItem(row, { free_quantity })} />, width: 105 },
        { key: 'mrp', title: 'MRP', render: (row) => <InputNumber min={0} value={row.mrp} onChange={(mrp) => updateItem(row, { mrp })} />, width: 115 },
        { key: 'rate', title: 'Rate', render: (row) => <InputNumber min={0} value={row.unit_price} onChange={(unit_price) => updateItem(row, { unit_price })} />, width: 115 },
        { key: 'cc_rate', title: 'CC %', render: (row) => <InputNumber min={0} max={100} value={row.cc_rate} onChange={(cc_rate) => updateItem(row, { cc_rate })} />, width: 95 },
        { key: 'discount_percent', title: 'Disc %', render: (row) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateItem(row, { discount_percent })} />, width: 95 },
        { key: 'free_goods', title: 'Free Goods', className: 'line-money-cell', render: (row) => <Money value={itemFreeGoodsValue(row)} />, width: 120 },
        { key: 'line_total', title: 'Line Total', className: 'line-money-cell', render: (row) => <Money value={itemNet(row, 'unit_price')} />, width: 130 },
    ];
    const invoiceColumns = [
        { title: 'Invoice', dataIndex: 'invoice_no', field: 'invoice_no', sorter: true },
        { title: 'Date', dataIndex: 'invoice_date', field: 'invoice_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Customer', dataIndex: ['customer', 'name'], render: (value) => value || 'Walk-in' },
        { title: 'MR', dataIndex: ['medical_representative', 'name'], render: (value) => value || '-' },
        { title: 'Payment', dataIndex: 'payment_status', width: 130, render: (v) => <PaymentStatusBadge value={v} /> },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (v) => <Money value={v} /> },
        { title: 'Status', dataIndex: 'is_active', width: 110, render: (v) => <StatusBadge value={v} /> },
        {
            title: 'Action',
            width: 150,
            render: (_, row) => (
                <Space>
                    <Button icon={<PrinterOutlined />} onClick={() => window.open(appUrl(`/sales/invoices/${row.id}/print`), '_blank')}>Print</Button>
                    <Button onClick={() => window.open(appUrl(`/sales/invoices/${row.id}/pdf`), '_blank')}>PDF</Button>
                </Space>
            ),
        },
    ];

    return (
        <div className="page-stack">
            <PageHeader
                title={section === 'invoices' ? 'Sales' : section === 'returns' ? 'Sales Return' : 'New Sales Invoice'}
                actions={(
                    <Space>
                        {section !== 'pos' && <Button type="primary" onClick={() => goToApp('/app/sales/pos')}>New Sales</Button>}
                        {section !== 'invoices' && <Button onClick={() => goToApp('/app/sales')}>Sales</Button>}
                        <Button icon={<PlusOutlined />} onClick={() => setQuickProductOpen(true)}>Quick Product</Button>
                        <Button disabled={!lastPrintUrl} icon={<PrinterOutlined />} onClick={() => window.open(lastPrintUrl, '_blank')}>Print Last Invoice</Button>
                    </Space>
                )}
            />

            {section === 'pos' && (
                <Card>
                    <div className="pos-toolbar pos-toolbar-wide">
                        <BarcodeInput id="pos-barcode-input" value={barcode} onChange={setBarcode} onScan={scan} />
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
                        <Select
                            allowClear
                            placeholder="MR"
                            value={medicalRepresentativeId}
                            onChange={setMedicalRepresentativeId}
                            options={medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))}
                        />
                        <SmartDatePicker value={invoiceDate} onChange={setInvoiceDate} className="full-width" placeholder="Invoice Date" />
                        <InputNumber id="pos-paid-amount" min={0} value={paidAmount} onChange={setPaidAmount} placeholder="Paid" />
                    </div>
                    <div className="pos-walkin-strip">
                        <PharmaBadge tone={customerId ? 'info' : 'neutral'} icon={<UserOutlined />}>{customerId ? `Customer #${customerId}` : 'Walk-in customer'}</PharmaBadge>
                        <Button size="small" onClick={() => setCustomerId(null)}>Use Walk-in</Button>
                    </div>
                    <Select
                        showSearch
                        filterOption={false}
                        placeholder="Search product and batch"
                        className="full-width mb-16 pos-product-search"
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
                    <TransactionLineItems
                        rows={items}
                        columns={columns}
                        errors={lineErrors}
                        rowKey={(row) => row.key}
                        addLabel="Add Item"
                        onAdd={() => {
                            searchProduct('').then(setProductOptions);
                            document.querySelector('.pos-product-search input')?.focus();
                        }}
                        onRemove={(index) => setItems((current) => current.filter((_, rowIndex) => rowIndex !== index))}
                        minRows={0}
                        summary={[
                            { label: 'Subtotal', value: <Money value={invoiceSummary.subtotal} /> },
                            { label: 'Discount', value: <Money value={invoiceSummary.discount} /> },
                            { label: 'Tax', value: <Money value={invoiceSummary.tax} /> },
                            { label: 'Free Goods Value', value: <Money value={invoiceSummary.freeGoods} /> },
                            { label: 'Grand Total', value: <Money value={invoiceSummary.grandTotal} />, strong: true },
                        ]}
                        actions={(
                            <Space>
                                <Button
                                    icon={<QrcodeOutlined />}
                                    disabled={!items.length}
                                    onClick={() => setQrVisible(true)}
                                >
                                    Payment QR
                                </Button>
                                <Button id="pos-submit-btn" type="primary" disabled={!items.length} onClick={submitInvoice}>Post Invoice (F8)</Button>
                            </Space>
                        )}
                    />
                </Card>
            )}

            {section === 'invoices' && (
                <Card title="Sales Invoice List">
                    <div className="table-toolbar table-toolbar-wide">
                        <Input.Search value={invoiceTable.search} onChange={(event) => invoiceTable.setSearch(event.target.value)} placeholder="Search invoice or customer" allowClear />
                        <Select
                            allowClear
                            placeholder="Payment"
                            value={invoiceTable.filters.payment_status}
                            onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, payment_status: value }))}
                            options={paymentStatusOptions}
                        />
                        <Select
                            allowClear
                            placeholder="Customer"
                            value={invoiceTable.filters.customer_id}
                            onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, customer_id: value }))}
                            options={customers.map((item) => ({ value: item.id, label: item.name }))}
                        />
                        <Select
                            allowClear
                            placeholder="MR"
                            value={invoiceTable.filters.medical_representative_id}
                            onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, medical_representative_id: value }))}
                            options={medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))}
                        />
                        <SmartDatePicker.RangePicker value={invoiceRange} onChange={setInvoiceRange} />
                        <Button onClick={invoiceTable.reload}>Refresh</Button>
                    </div>
                    <ServerTable table={invoiceTable} columns={invoiceColumns} />
                </Card>
            )}

            {section === 'returns' && (
                <SalesReturnsPanel />
            )}
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
                destroyOnClose
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

            <Modal
                title="Payment QR Code"
                open={qrVisible}
                onCancel={() => setQrVisible(false)}
                footer={[<Button key="close" onClick={() => setQrVisible(false)}>Close</Button>]}
                centered
                width={320}
            >
                <div style={{ textAlign: 'center', padding: '20px 0' }}>
                    <p style={{ marginBottom: 16, fontSize: 16 }}>Scan to pay <strong><Money value={invoiceSummary.grandTotal} /></strong></p>
                    <div style={{ background: '#fff', padding: 12, borderRadius: 12, display: 'inline-block', border: '1px solid #eee' }}>
                        <img
                            src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(`Payment for Invoice: ${invoiceSummary.grandTotal} NPR`)}`}
                            alt="QR Code"
                            style={{ width: 200, height: 200 }}
                        />
                    </div>
                    <p style={{ marginTop: 16, color: '#64748b', fontSize: 12 }}>Accepts all major fonepay/QR apps</p>
                </div>
            </Modal>
        </div>
    );
}
