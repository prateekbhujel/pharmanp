import React, { useEffect, useMemo, useRef, useState } from 'react';
import { App, Button, Card, Descriptions, Form, Input, InputNumber, Modal, Select, Space, Table } from 'antd';
import { DollarOutlined, EyeOutlined, PlusOutlined, PrinterOutlined, QrcodeOutlined, UserOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { DateText } from '../../core/components/DateText';
import { PaymentStatusBadge, PharmaBadge, StatusBadge } from '../../core/components/PharmaBadge';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { QuickProductModal } from '../../core/components/QuickProductModal';
import { ServerTable } from '../../core/components/ServerTable';
import { ExportButtons } from '../../core/components/ListToolbarActions';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { useServerTable } from '../../core/hooks/useServerTable';
import { focusFirstKeyboardField, useKeyboardFlow } from '../../core/hooks/useKeyboardFlow';
import { itemFreeGoodsValue, itemNet, summarizeItems, validationErrorsByLine } from '../../core/utils/lineItems';
import { paymentStatusOptions } from '../../core/utils/accountCatalog';
import { appUrl } from '../../core/utils/url';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';
import { openAuthenticatedDocument } from '../../core/utils/documents';
import { SalesReturnsPanel } from './SalesReturnsPanel';

const fallbackPaymentTypes = [
    { value: 'cash', label: 'Cash' },
    { value: 'credit', label: 'Credit' },
    { value: 'partial', label: 'Partial' },
    { value: 'qr', label: 'QR / Digital Wallet' },
];

function salesSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    if (['invoices', 'returns', 'expiry-returns', 'pos'].includes(section)) {
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
    const [dueDate, setDueDate] = useState(null);
    const [invoiceRange, setInvoiceRange] = useState([]);
    const [paymentType, setPaymentType] = useState('cash');
    const [paymentModeId, setPaymentModeId] = useState(undefined);
    const [paymentModes, setPaymentModes] = useState([]);
    const [paymentTypes, setPaymentTypes] = useState(fallbackPaymentTypes);
    const [paidAmount, setPaidAmount] = useState(0);
    const [lastPrintUrl, setLastPrintUrl] = useState(null);
    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);
    const [quickProductOpen, setQuickProductOpen] = useState(false);
    const [quickMrOpen, setQuickMrOpen] = useState(false);
    const [qrVisible, setQrVisible] = useState(false);
    const [viewingInvoice, setViewingInvoice] = useState(null);
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const [productOptions, setProductOptions] = useState([]);
    const posEntryRef = useRef(null);
    const paymentUpdateRef = useRef(null);
    const [customerForm] = Form.useForm();
    const [mrForm] = Form.useForm();
    const [paymentForm] = Form.useForm();
    const invoiceTable = useServerTable({
        endpoint: endpoints.salesInvoices,
        defaultSort: { field: 'invoice_date', order: 'desc' },
    });

    useEffect(() => {
        loadCustomers();
        loadMedicalRepresentatives();
        loadPaymentLookups();

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
            if (event.altKey && event.key === 'b') {
                event.preventDefault();
                if (section === 'pos') document.getElementById('pos-barcode-input')?.focus();
            }
            if (event.altKey && event.key === 'p') {
                event.preventDefault();
                if (section === 'pos') document.getElementById('pos-paid-amount')?.focus();
            }
            if (event.altKey && event.key === 'q') {
                event.preventDefault();
                if (section === 'pos') setQuickProductOpen(true);
            }
        }

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [section]);

    useKeyboardFlow(posEntryRef, {
        enabled: section === 'pos',
        autofocus: section === 'pos',
        autofocusSelector: '#pos-barcode-input',
        onSubmit: submitInvoice,
        onAddRow: () => {
            searchProduct('').then(setProductOptions);
            focusFirstKeyboardField(posEntryRef.current, '.pos-product-search input');
        },
        resetKey: section,
    });

    useKeyboardFlow(paymentUpdateRef, {
        enabled: paymentModalOpen,
        autofocus: paymentModalOpen,
        onSubmit: () => paymentForm.submit(),
        resetKey: paymentModalOpen,
    });

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

    async function loadPaymentLookups() {
        try {
            const { data } = await http.get(endpoints.dropdownOptions);
            const options = data.data || [];
            const modes = options.filter((item) => item.alias === 'payment_mode' && item.is_active !== false);
            const types = options
                .filter((item) => item.alias === 'payment_type' && item.is_active !== false)
                .map((item) => ({ value: item.code || item.name?.toLowerCase(), label: item.name }))
                .filter((item) => item.value && item.label);

            setPaymentModes(modes);
            setPaymentTypes(types.length ? types : fallbackPaymentTypes);
        } catch {
            setPaymentModes([]);
            setPaymentTypes(fallbackPaymentTypes);
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
                due_date: dueDate?.format('YYYY-MM-DD'),
                sale_type: 'pos',
                paid_amount: paidAmount,
                payment_type: paymentType,
                payment_mode_id: paymentModeId,
                items,
            });
            notification.success({ message: 'Invoice posted and stock deducted' });
            setItems([]);
            setLineErrors({});
            setPaidAmount(0);
            setPaymentType('cash');
            setPaymentModeId(undefined);
            setDueDate(null);
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

    async function submitMr(values) {
        try {
            const { data } = await http.post(endpoints.mrRepresentatives, { ...values, is_active: true });
            await loadMedicalRepresentatives();
            setMedicalRepresentativeId(data.data.id);
            mrForm.resetFields();
            setQuickMrOpen(false);
            notification.success({ message: 'MR added' });
        } catch (error) {
            mrForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'MR save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function viewInvoice(row) {
        try {
            const { data } = await http.get(`${endpoints.salesInvoices}/${row.id}`);
            setViewingInvoice(data.data);
            return data.data;
        } catch (error) {
            notification.error({ message: 'Invoice details failed', description: error?.response?.data?.message || error.message });
            return null;
        }
    }

    async function openInvoicePayment(row) {
        const invoice = await viewInvoice(row);
        if (invoice) {
            openPaymentUpdate(invoice);
        }
    }

    function openPaymentUpdate(invoice = viewingInvoice) {
        if (!invoice) return;
        paymentForm.resetFields();
        paymentForm.setFieldsValue({
            paid_amount: invoice.paid_amount || 0,
            payment_mode_id: invoice.payment_mode_id || invoice.payment_mode?.id,
        });
        setPaymentModalOpen(true);
    }

    async function submitPayment(values) {
        if (!viewingInvoice) return;

        try {
            const { data } = await http.patch(endpoints.salesInvoicePayment(viewingInvoice.id), values);
            notification.success({ message: data.message || 'Invoice payment updated' });
            setViewingInvoice(data.data);
            setPaymentModalOpen(false);
            invoiceTable.reload();
        } catch (error) {
            paymentForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Payment update failed', description: error?.response?.data?.message || error.message });
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
        { title: 'Due Date', dataIndex: 'due_date', width: 130, render: (value) => value ? <DateText value={value} style="compact" /> : '-' },
        { title: 'Customer', dataIndex: ['customer', 'name'], render: (value) => value || 'Walk-in' },
        { title: 'MR', dataIndex: ['medical_representative', 'name'], render: (value) => value || '-' },
        { title: 'Mode', dataIndex: ['payment_mode', 'name'], width: 130, render: (value, row) => value || row.payment_type || '-' },
        { title: 'Payment', dataIndex: 'payment_status', width: 130, render: (v) => <PaymentStatusBadge value={v} /> },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (v) => <Money value={v} /> },
        { title: 'Status', dataIndex: 'is_active', width: 110, render: (v) => <StatusBadge value={v} /> },
        {
            title: 'Action',
            width: 240,
            render: (_, row) => (
                <Space>
                    <Button icon={<EyeOutlined />} onClick={() => viewInvoice(row)}>View</Button>
                    <Button icon={<DollarOutlined />} onClick={() => openInvoicePayment(row)}>Payment</Button>
                    <Button icon={<PrinterOutlined />} onClick={() => openAuthenticatedDocument(appUrl(`/sales/invoices/${row.id}/print`))}>Print</Button>
                    <Button onClick={() => openAuthenticatedDocument(appUrl(`/sales/invoices/${row.id}/pdf`), { accept: 'application/pdf' })}>PDF</Button>
                </Space>
            ),
        },
    ];

    return (
        <div className="page-stack">
            <PageHeader
                actions={(
                    <Space>
                        {section !== 'pos' && <Button type="primary" onClick={() => goToApp('/app/sales/pos')}>New Sales</Button>}
                        {section !== 'invoices' && <Button onClick={() => goToApp('/app/sales')}>Sales</Button>}
                        <Button icon={<PlusOutlined />} onClick={() => setQuickProductOpen(true)}>Quick Product</Button>
                        <Button disabled={!lastPrintUrl} icon={<PrinterOutlined />} onClick={() => openAuthenticatedDocument(lastPrintUrl)}>Print Last Invoice</Button>
                    </Space>
                )}
            />

            {section === 'pos' && (
                <Card>
                    <div ref={posEntryRef} data-keyboard-flow="true">
                        <div className="pos-toolbar pos-toolbar-wide">
                            <BarcodeInput id="pos-barcode-input" value={barcode} onChange={setBarcode} onScan={scan} />
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
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
                                showSearch
                                optionFilterProp="label"
                                placeholder="MR"
                                value={medicalRepresentativeId}
                                onChange={setMedicalRepresentativeId}
                                options={medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))}
                                dropdownRender={(menu) => (
                                    <>
                                        {menu}
                                        <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickMrOpen(true)}>Quick add MR</Button>
                                    </>
                                )}
                            />
                            <SmartDatePicker value={invoiceDate} onChange={setInvoiceDate} className="full-width" placeholder="Invoice Date" />
                            <SmartDatePicker value={dueDate} onChange={setDueDate} className="full-width" placeholder="Due Date" />
                            <Select
                                allowClear
                                placeholder="Payment Type"
                                value={paymentType}
                                onChange={(value) => setPaymentType(value || 'cash')}
                                options={paymentTypes}
                            />
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                placeholder="Payment Mode"
                                value={paymentModeId}
                                onChange={setPaymentModeId}
                                options={paymentModes.map((item) => ({ value: item.id, label: item.name }))}
                            />
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
                            onChange={(_, option) => {
                                addItem(option.product, option.batch);
                                focusFirstKeyboardField(posEntryRef.current, '.pos-product-search input');
                            }}
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
                                focusFirstKeyboardField(posEntryRef.current, '.pos-product-search input');
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
                                    <Button id="pos-submit-btn" type="primary" disabled={!items.length} onClick={submitInvoice}>Post Invoice</Button>
                                </Space>
                            )}
                        />
                    </div>
                </Card>
            )}

            {section === 'invoices' && (
                <Card title="Sales Invoice List">
                    <div className="table-toolbar table-toolbar-wide">
                        <Input.Search value={invoiceTable.search} onChange={(event) => invoiceTable.setSearch(event.target.value)} placeholder="Search invoice or customer" allowClear />
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Payment"
                            value={invoiceTable.filters.payment_status}
                            onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, payment_status: value }))}
                            options={paymentStatusOptions}
                        />
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="Customer"
                            value={invoiceTable.filters.customer_id}
                            onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, customer_id: value }))}
                            options={customers.map((item) => ({ value: item.id, label: item.name }))}
                        />
                        <Select
                            allowClear
                            showSearch
                            optionFilterProp="label"
                            placeholder="MR"
                            value={invoiceTable.filters.medical_representative_id}
                            onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, medical_representative_id: value }))}
                            options={medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))}
                        />
                        <SmartDatePicker.RangePicker value={invoiceRange} onChange={setInvoiceRange} />
                        <ExportButtons basePath={endpoints.datasetExport('sales-invoices')} params={{ ...invoiceTable.filters, search: invoiceTable.search, ...applyDateRangeFilter({}, invoiceRange) }} />
                        <Button onClick={invoiceTable.reload}>Refresh</Button>
                    </div>
                    <ServerTable table={invoiceTable} columns={invoiceColumns} />
                </Card>
            )}

            {['returns', 'expiry-returns'].includes(section) && (
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
                title="Quick Add MR"
                open={quickMrOpen}
                onCancel={() => setQuickMrOpen(false)}
                onOk={() => mrForm.submit()}
                destroyOnClose
            >
                <Form form={mrForm} layout="vertical" onFinish={submitMr}>
                    <Form.Item name="name" label="MR Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name="employee_code" label="Employee Code"><Input placeholder="Auto if blank" /></Form.Item>
                        <Form.Item name="monthly_target" label="Monthly Target"><InputNumber min={0} className="full-width" /></Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                        <Form.Item name="email" label="Email"><Input /></Form.Item>
                    </div>
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

            <Modal
                title={`Invoice Details: ${viewingInvoice?.invoice_no || ''}`}
                open={!!viewingInvoice}
                onCancel={() => setViewingInvoice(null)}
                footer={[
                    <Button key="payment" icon={<DollarOutlined />} onClick={() => openPaymentUpdate()}>Update Payment</Button>,
                    <Button key="print" icon={<PrinterOutlined />} onClick={() => openAuthenticatedDocument(appUrl(`/sales/invoices/${viewingInvoice?.id}/print`))}>Print</Button>,
                    <Button key="close" onClick={() => setViewingInvoice(null)}>Close</Button>,
                ]}
                width={980}
                destroyOnHidden
            >
                {viewingInvoice && (
                    <div className="page-stack">
                        <Descriptions bordered size="small" column={3}>
                            <Descriptions.Item label="Customer">{viewingInvoice.customer?.name || 'Walk-in'}</Descriptions.Item>
                            <Descriptions.Item label="Date"><DateText value={viewingInvoice.invoice_date} style="compact" /></Descriptions.Item>
                            <Descriptions.Item label="Due Date">{viewingInvoice.due_date ? <DateText value={viewingInvoice.due_date} style="compact" /> : '-'}</Descriptions.Item>
                            <Descriptions.Item label="Payment"><PaymentStatusBadge value={viewingInvoice.payment_status} /></Descriptions.Item>
                            <Descriptions.Item label="MR">{viewingInvoice.medical_representative?.name || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Payment Mode">{viewingInvoice.payment_mode?.name || viewingInvoice.payment_type || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Total"><Money value={viewingInvoice.grand_total} /></Descriptions.Item>
                            <Descriptions.Item label="Paid"><Money value={viewingInvoice.paid_amount} /></Descriptions.Item>
                        </Descriptions>
                        <Table
                            rowKey="id"
                            dataSource={viewingInvoice.items || []}
                            pagination={false}
                            size="small"
                            columns={[
                                { title: 'Product', dataIndex: 'product_name' },
                                { title: 'Batch', dataIndex: 'batch_no', width: 120 },
                                { title: 'Expiry', dataIndex: 'expires_at', width: 120, render: (value) => <DateText value={value} style="compact" /> },
                                { title: 'Qty', dataIndex: 'quantity', align: 'right', width: 90 },
                                { title: 'Rate', dataIndex: 'unit_price', align: 'right', width: 110, render: (value) => <Money value={value} /> },
                                { title: 'Discount', dataIndex: 'discount_amount', align: 'right', width: 110, render: (value) => <Money value={value} /> },
                                { title: 'Total', dataIndex: 'line_total', align: 'right', width: 120, render: (value) => <Money value={value} /> },
                            ]}
                        />
                        <Card size="small" title={`Return History (${viewingInvoice.returns?.length || 0})`}>
                            <Table
                                rowKey="id"
                                dataSource={viewingInvoice.returns || []}
                                pagination={false}
                                size="small"
                                columns={[
                                    { title: 'Return No', dataIndex: 'return_no' },
                                    { title: 'Date', dataIndex: 'return_date', render: (value) => <DateText value={value} style="compact" /> },
                                    { title: 'Status', dataIndex: 'status', render: (value) => <PharmaBadge tone={value}>{value || '-'}</PharmaBadge> },
                                    { title: 'Amount', dataIndex: 'total_amount', align: 'right', render: (value) => <Money value={value} /> },
                                    { title: 'Reason', dataIndex: 'reason', render: (value) => value || '-' },
                                ]}
                                locale={{ emptyText: 'No return history for this invoice.' }}
                            />
                        </Card>
                    </div>
                )}
            </Modal>

            <Modal
                title={`Update Payment: ${viewingInvoice?.invoice_no || ''}`}
                open={paymentModalOpen}
                onCancel={() => setPaymentModalOpen(false)}
                onOk={() => paymentForm.submit()}
                okText="Save Payment"
                destroyOnHidden
            >
                <div ref={paymentUpdateRef} data-keyboard-flow="true">
                    <Form form={paymentForm} layout="vertical" onFinish={submitPayment}>
                        <Descriptions size="small" bordered column={1} className="mb-16">
                            <Descriptions.Item label="Invoice Total"><Money value={viewingInvoice?.grand_total || 0} /></Descriptions.Item>
                        </Descriptions>
                        <Form.Item name="paid_amount" label="Paid Amount" rules={[{ required: true }]}>
                            <InputNumber min={0} max={Number(viewingInvoice?.grand_total || 0)} className="full-width" />
                        </Form.Item>
                        <Form.Item name="payment_mode_id" label="Payment Mode">
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                options={paymentModes.map((item) => ({ value: item.id, label: item.name }))}
                            />
                        </Form.Item>
                    </Form>
                </div>
            </Modal>
        </div>
    );
}
