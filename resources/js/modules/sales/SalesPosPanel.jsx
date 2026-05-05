import React, { useMemo, useRef, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Modal, Select, Space } from 'antd';
import { PlusOutlined, QrcodeOutlined, UserOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { Money } from '../../core/components/Money';
import { QuickProductModal } from '../../core/components/QuickProductModal';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { focusFirstKeyboardField, useKeyboardFlow } from '../../core/hooks/useKeyboardFlow';
import { itemFreeGoodsValue, itemNet, summarizeItems, validationErrorsByLine } from '../../core/utils/lineItems';
import { DateText } from '../../core/components/DateText';

export const SalesPosPanel = React.forwardRef(({
    customers,
    medicalRepresentatives,
    paymentModes,
    paymentTypes,
    onCustomerAdded,
    onMrAdded,
    onInvoiceSuccess,
    searchProduct,
}, ref) => {
    const { notification } = App.useApp();
    const [barcode, setBarcode] = useState('');
    const [items, setItems] = useState([]);
    const [lineErrors, setLineErrors] = useState({});
    const [customerId, setCustomerId] = useState(null);
    const [medicalRepresentativeId, setMedicalRepresentativeId] = useState(undefined);
    const [invoiceDate, setInvoiceDate] = useState(dayjs());
    const [dueDate, setDueDate] = useState(null);
    const [paymentType, setPaymentType] = useState('cash');
    const [paymentModeId, setPaymentModeId] = useState(undefined);
    const [paidAmount, setPaidAmount] = useState(0);

    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);
    const [quickProductOpen, setQuickProductOpen] = useState(false);
    const [quickMrOpen, setQuickMrOpen] = useState(false);
    const [qrVisible, setQrVisible] = useState(false);
    const [productOptions, setProductOptions] = useState([]);

    const posEntryRef = useRef(null);
    const [customerForm] = Form.useForm();
    const [mrForm] = Form.useForm();

    useKeyboardFlow(posEntryRef, {
        enabled: true,
        autofocus: true,
        autofocusSelector: '#pos-barcode-input',
        onSubmit: submitInvoice,
        onAddRow: () => {
            searchProduct('').then(setProductOptions);
            focusFirstKeyboardField(posEntryRef.current, '.pos-product-search input');
        },
        resetKey: 'pos',
    });

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
            onInvoiceSuccess(data.print_url);
        } catch (error) {
            setLineErrors(validationErrorsByLine(validationErrors(error), 'items'));
            notification.error({ message: 'Invoice failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function submitCustomer(values) {
        try {
            const { data } = await http.post(endpoints.customers, values);
            if (onCustomerAdded) await onCustomerAdded();
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
            if (onMrAdded) await onMrAdded();
            setMedicalRepresentativeId(data.data.id);
            mrForm.resetFields();
            setQuickMrOpen(false);
            notification.success({ message: 'MR added' });
        } catch (error) {
            mrForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'MR save failed', description: error?.response?.data?.message || error.message });
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

    // Expose imperative handle for parent hotkeys
    React.useImperativeHandle(ref, () => ({
        submitInvoice,
        focusProductSearch: () => {
            searchProduct('').then(setProductOptions);
            document.querySelector('.pos-product-search input')?.focus();
        },
        focusBarcode: () => document.getElementById('pos-barcode-input')?.focus(),
        focusPaidAmount: () => document.getElementById('pos-paid-amount')?.focus(),
        openQuickProduct: () => setQuickProductOpen(true),
    }));

    return (
        <>
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
        </>
    );
});
