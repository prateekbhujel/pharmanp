import React, { useEffect, useMemo, useState } from 'react';
import { Alert, App, Button, Card, Form, Input, InputNumber, Modal, Select, Space } from 'antd';
import { PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { PaymentStatusBadge } from '../../core/components/PharmaBadge';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { QuickProductModal } from '../../core/components/QuickProductModal';
import { ServerTable } from '../../core/components/ServerTable';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { useServerTable } from '../../core/hooks/useServerTable';
import { itemFreeGoodsValue, itemGross, itemNet, summarizeItems, validationErrorsByLine } from '../../core/utils/lineItems';
import { paymentStatusOptions } from '../../core/utils/accountCatalog';
import { appUrl } from '../../core/utils/url';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';
import { PurchaseReturnsPanel } from './PurchaseReturnsPanel';
import { PurchaseOrdersPanel } from './PurchaseOrdersPanel';

const emptyPurchaseItem = {
    product_id: null,
    batch_no: '',
    expires_at: null,
    quantity: 1,
    free_quantity: 0,
    purchase_price: 0,
    mrp: 0,
    cc_rate: 0,
    discount_percent: 0,
};

const emptyOrderItem = {
    product_id: null,
    quantity: 1,
    unit_price: 0,
    discount_percent: 0,
};
const OCR_DRAFT_STORAGE_KEY = 'pharmanp-purchase-ocr-draft';

function purchaseSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    if (['entry', 'orders', 'returns'].includes(section)) {
        return section;
    }

    return 'bills';
}

function goToApp(path) {
    window.history.pushState({}, '', appUrl(path));
    window.dispatchEvent(new PopStateEvent('popstate'));
}

export function PurchasesPage() {
    const { notification } = App.useApp();
    const section = purchaseSection();
    const [suppliers, setSuppliers] = useState([]);
    const [products, setProducts] = useState([]);
    const [purchaseItems, setPurchaseItems] = useState([{ ...emptyPurchaseItem }]);
    const [orderItems, setOrderItems] = useState([{ ...emptyOrderItem }]);
    const [purchaseLineErrors, setPurchaseLineErrors] = useState({});
    const [orderLineErrors, setOrderLineErrors] = useState({});
    const [quickSupplierOpen, setQuickSupplierOpen] = useState(false);
    const [quickProductOpen, setQuickProductOpen] = useState(false);
    const [lastPurchasePrintUrl, setLastPurchasePrintUrl] = useState(null);
    const [ocrDraft, setOcrDraft] = useState(null);
    const [billRange, setBillRange] = useState([]);
    const [purchaseForm] = Form.useForm();
    const [orderForm] = Form.useForm();
    const [supplierForm] = Form.useForm();
    const purchaseTable = useServerTable({
        endpoint: endpoints.purchases,
        defaultSort: { field: 'purchase_date', order: 'desc' },
    });

    useEffect(() => {
        loadSuppliers();
        searchProducts('');
        loadOcrDraft();
    }, []);

    useEffect(() => {
        purchaseTable.setFilters((current) => applyDateRangeFilter(current, billRange));
    }, [billRange]);

    async function loadSuppliers() {
        const { data } = await http.get(endpoints.supplierOptions);
        setSuppliers(data.data);
    }

    function loadOcrDraft() {
        try {
            const stored = window.sessionStorage.getItem(OCR_DRAFT_STORAGE_KEY);
            if (!stored) return;

            const draft = JSON.parse(stored);
            setOcrDraft(draft);
            purchaseForm.setFieldsValue({
                supplier_id: draft.supplier_id || undefined,
                supplier_invoice_no: draft.supplier_invoice_no || '',
                purchase_date: draft.purchase_date ? dayjs(draft.purchase_date) : dayjs(),
                notes: draft.notes || '',
            });
        } catch {
            window.sessionStorage.removeItem(OCR_DRAFT_STORAGE_KEY);
        }
    }

    function clearOcrDraft() {
        window.sessionStorage.removeItem(OCR_DRAFT_STORAGE_KEY);
        setOcrDraft(null);
    }

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProducts(data.data || []);
    }

    function productOptions() {
        return products.map((product) => ({
            value: product.id,
            label: `${product.name} ${product.sku ? `(${product.sku})` : ''}`,
            product,
        }));
    }

    function updateRow(rows, setRows, index, patch) {
        setRows(rows.map((row, rowIndex) => rowIndex === index ? { ...row, ...patch } : row));
    }

    function removeRow(rows, setRows, index, emptyRow) {
        const nextRows = rows.filter((_, rowIndex) => rowIndex !== index);
        setRows(nextRows.length ? nextRows : [{ ...emptyRow }]);
    }

    const purchaseSummary = useMemo(() => summarizeItems(purchaseItems, 'purchase_price'), [purchaseItems]);
    const orderSummary = useMemo(() => summarizeItems(orderItems, 'unit_price'), [orderItems]);

    async function submitPurchase(values) {
        try {
            const payload = {
                ...values,
                purchase_date: values.purchase_date.format('YYYY-MM-DD'),
                items: purchaseItems.map((item) => ({
                    ...item,
                    expires_at: item.expires_at?.format('YYYY-MM-DD'),
                    manufactured_at: item.manufactured_at?.format('YYYY-MM-DD'),
                })),
            };
            const { data } = await http.post(endpoints.purchases, payload);
            notification.success({ message: 'Purchase posted and stock received' });
            purchaseForm.resetFields();
            setPurchaseItems([{ ...emptyPurchaseItem }]);
            setPurchaseLineErrors({});
            setLastPurchasePrintUrl(data.print_url);
            clearOcrDraft();
            purchaseTable.reload();
        } catch (error) {
            const errors = validationErrors(error);
            setPurchaseLineErrors(validationErrorsByLine(errors, 'items'));
            purchaseForm.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Purchase failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function submitSupplier(values) {
        try {
            const { data } = await http.post(endpoints.suppliers, values);
            await loadSuppliers();
            purchaseForm.setFieldValue('supplier_id', data.data.id);
            orderForm.setFieldValue('supplier_id', data.data.id);
            supplierForm.resetFields();
            setQuickSupplierOpen(false);
            notification.success({ message: 'Supplier added' });
        } catch (error) {
            supplierForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Supplier save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function submitOrder(values) {
        try {
            await http.post(endpoints.purchaseOrders, {
                ...values,
                order_date: values.order_date.format('YYYY-MM-DD'),
                expected_date: values.expected_date?.format('YYYY-MM-DD'),
                items: orderItems,
            });
            notification.success({ message: 'Purchase order created' });
            orderForm.resetFields();
            setOrderItems([{ ...emptyOrderItem }]);
            setOrderLineErrors({});
        } catch (error) {
            const errors = validationErrors(error);
            setOrderLineErrors(validationErrorsByLine(errors, 'items'));
            orderForm.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Order failed', description: error?.response?.data?.message || error.message });
        }
    }

    const productSelect = (row, index, rows, setRows, priceField) => (
        <Select
            showSearch
            value={row.product_id}
            filterOption={false}
            onSearch={searchProducts}
            options={productOptions()}
            dropdownRender={(menu) => (
                <>
                    {menu}
                    <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickProductOpen(true)}>Quick add product</Button>
                </>
            )}
            onChange={(product_id, option) => {
                const product = option.product;
                updateRow(rows, setRows, index, {
                    product_id,
                    [priceField]: product.purchase_price || product.selling_price || product.mrp || 0,
                    mrp: product.mrp || row.mrp || 0,
                    cc_rate: product.cc_rate || row.cc_rate || 0,
                });
            }}
            className="full-width"
        />
    );

    const purchaseColumns = [
        { key: 'product', title: 'Product', render: (row, index) => productSelect(row, index, purchaseItems, setPurchaseItems, 'purchase_price'), width: 280 },
        { key: 'batch', title: 'Batch', render: (row, index) => <Input value={row.batch_no} onChange={(event) => updateRow(purchaseItems, setPurchaseItems, index, { batch_no: event.target.value })} />, width: 150 },
        { key: 'expiry', title: 'Expiry', render: (row, index) => <SmartDatePicker value={row.expires_at} onChange={(expires_at) => updateRow(purchaseItems, setPurchaseItems, index, { expires_at })} />, width: 150 },
        { key: 'quantity', title: 'Qty', render: (row, index) => <InputNumber min={0.001} value={row.quantity} onChange={(quantity) => updateRow(purchaseItems, setPurchaseItems, index, { quantity })} />, width: 100 },
        { key: 'free_quantity', title: 'Free Qty', render: (row, index) => <InputNumber min={0} value={row.free_quantity} onChange={(free_quantity) => updateRow(purchaseItems, setPurchaseItems, index, { free_quantity })} />, width: 105 },
        { key: 'mrp', title: 'MRP', render: (row, index) => <InputNumber min={0} value={row.mrp} onChange={(mrp) => updateRow(purchaseItems, setPurchaseItems, index, { mrp })} />, width: 115 },
        { key: 'rate', title: 'Rate', render: (row, index) => <InputNumber min={0} value={row.purchase_price} onChange={(purchase_price) => updateRow(purchaseItems, setPurchaseItems, index, { purchase_price })} />, width: 115 },
        { key: 'cc_rate', title: 'CC %', render: (row, index) => <InputNumber min={0} max={100} value={row.cc_rate} onChange={(cc_rate) => updateRow(purchaseItems, setPurchaseItems, index, { cc_rate })} />, width: 95 },
        { key: 'discount_percent', title: 'Disc %', render: (row, index) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateRow(purchaseItems, setPurchaseItems, index, { discount_percent })} />, width: 95 },
        { key: 'free_goods', title: 'Free Goods', className: 'line-money-cell', render: (row) => <Money value={itemFreeGoodsValue(row)} />, width: 120 },
        { key: 'amount', title: 'Amount', className: 'line-money-cell', render: (row) => <Money value={itemGross(row, 'purchase_price')} />, width: 120 },
    ];

    const orderColumns = [
        { key: 'product', title: 'Product', render: (row, index) => productSelect(row, index, orderItems, setOrderItems, 'unit_price'), width: 360 },
        { key: 'quantity', title: 'Qty', render: (row, index) => <InputNumber min={0.001} value={row.quantity} onChange={(quantity) => updateRow(orderItems, setOrderItems, index, { quantity })} />, width: 120 },
        { key: 'rate', title: 'Rate', render: (row, index) => <InputNumber min={0} value={row.unit_price} onChange={(unit_price) => updateRow(orderItems, setOrderItems, index, { unit_price })} />, width: 120 },
        { key: 'discount_percent', title: 'Discount %', render: (row, index) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateRow(orderItems, setOrderItems, index, { discount_percent })} />, width: 130 },
        { key: 'amount', title: 'Amount', className: 'line-money-cell', render: (row) => <Money value={itemNet(row, 'unit_price')} />, width: 130 },
    ];
    const billColumns = [
        { title: 'Bill', dataIndex: 'purchase_no', field: 'purchase_no', sorter: true },
        { title: 'Date', dataIndex: 'purchase_date', field: 'purchase_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Supplier Bill', dataIndex: 'supplier_invoice_no', width: 150 },
        { title: 'Supplier', dataIndex: ['supplier', 'name'] },
        { title: 'Payment', dataIndex: 'payment_status', width: 130, render: (value) => <PaymentStatusBadge value={value} /> },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (value) => <Money value={value} /> },
        {
            title: 'Action',
            width: 150,
            render: (_, row) => (
                <Space>
                    <Button icon={<PrinterOutlined />} onClick={() => window.open(appUrl(`/purchases/${row.id}/print`), '_blank')}>Print</Button>
                    <Button onClick={() => window.open(appUrl(`/purchases/${row.id}/pdf`), '_blank')}>PDF</Button>
                </Space>
            ),
        },
    ];

    return (
        <div className="page-stack">
            <PageHeader
                title={section === 'entry' ? 'Purchase Entry' : section === 'orders' ? 'Purchase Order' : section === 'returns' ? 'Purchase Return' : 'Purchase Bills'}
                actions={(
                    <Space>
                        {section !== 'entry' && <Button type="primary" icon={<PlusOutlined />} onClick={() => goToApp('/app/purchases/entry')}>New Purchase</Button>}
                        {section !== 'bills' && <Button onClick={() => goToApp('/app/purchases/bills')}>Purchase Bills</Button>}
                        <Button icon={<PlusOutlined />} onClick={() => setQuickProductOpen(true)}>Quick Product</Button>
                        <Button disabled={!lastPurchasePrintUrl} icon={<PrinterOutlined />} onClick={() => window.open(lastPurchasePrintUrl, '_blank')}>Print Last Purchase</Button>
                    </Space>
                )}
            />

            {section === 'entry' && (
                <Card>
                    <Form form={purchaseForm} layout="vertical" onFinish={submitPurchase} initialValues={{ purchase_date: dayjs(), paid_amount: 0 }}>
                        {ocrDraft && (
                            <Alert
                                type="info"
                                showIcon
                                className="mb-16"
                                message="OCR draft loaded"
                                description={`Supplier: ${ocrDraft.supplier_name || 'manual review'} | Invoice: ${ocrDraft.supplier_invoice_no || '-'} | ${ocrDraft.matches?.length || 0} possible match(es) found.`}
                                action={<Button size="small" onClick={clearOcrDraft}>Clear</Button>}
                            />
                        )}
                        <div className="form-grid">
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
                            <Form.Item name="supplier_invoice_no" label="Supplier Bill No"><Input /></Form.Item>
                            <Form.Item name="purchase_date" label="Purchase Date" rules={[{ required: true }]}><SmartDatePicker /></Form.Item>
                            <Form.Item name="paid_amount" label="Paid Amount"><InputNumber min={0} className="full-width" /></Form.Item>
                        </div>
                        <Form.Item name="notes" label="RemarksI "><Input.TextArea rows={3} /></Form.Item>
                        <TransactionLineItems
                            rows={purchaseItems}
                            columns={purchaseColumns}
                            errors={purchaseLineErrors}
                            addLabel="Add Item"
                            onAdd={() => setPurchaseItems([...purchaseItems, { ...emptyPurchaseItem }])}
                            onRemove={(index) => removeRow(purchaseItems, setPurchaseItems, index, emptyPurchaseItem)}
                            summary={[
                                { label: 'Subtotal', value: <Money value={purchaseSummary.subtotal} /> },
                                { label: 'Discount', value: <Money value={purchaseSummary.discount} /> },
                                { label: 'Tax', value: <Money value={purchaseSummary.tax} /> },
                                { label: 'Free Goods Value', value: <Money value={purchaseSummary.freeGoods} /> },
                                { label: 'Grand Total', value: <Money value={purchaseSummary.grandTotal} />, strong: true },
                            ]}
                            actions={<Button type="primary" htmlType="submit">Post Purchase</Button>}
                        />
                    </Form>
                </Card>
            )}

            {section === 'bills' && (
                <Card title="Purchase Bill List">
                    <div className="table-toolbar table-toolbar-wide">
                        <Input.Search value={purchaseTable.search} onChange={(event) => purchaseTable.setSearch(event.target.value)} placeholder="Search purchase or supplier" allowClear />
                        <Select
                            allowClear
                            placeholder="Supplier"
                            value={purchaseTable.filters.supplier_id}
                            onChange={(value) => purchaseTable.setFilters((current) => ({ ...current, supplier_id: value }))}
                            options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                        />
                        <Select
                            allowClear
                            placeholder="Payment"
                            value={purchaseTable.filters.payment_status}
                            onChange={(value) => purchaseTable.setFilters((current) => ({ ...current, payment_status: value }))}
                            options={paymentStatusOptions}
                        />
                        <SmartDatePicker.RangePicker value={billRange} onChange={setBillRange} />
                        <Button onClick={purchaseTable.reload}>Refresh</Button>
                    </div>
                    <ServerTable table={purchaseTable} columns={billColumns} />
                </Card>
            )}

            {section === 'orders' && (
                <PurchaseOrdersPanel />
            )}

            {section === 'returns' && (
                <PurchaseReturnsPanel />
            )}
            <QuickProductModal
                open={quickProductOpen}
                onClose={() => setQuickProductOpen(false)}
                onCreated={(product) => setProducts((current) => [product, ...current.filter((item) => item.id !== product.id)])}
            />
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
