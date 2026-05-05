import React, { useMemo, useRef, useState } from 'react';
import { Alert, App, Button, Card, Form, Input, InputNumber, Select } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { Money } from '../../core/components/Money';
import { QuickProductModal } from '../../core/components/QuickProductModal';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { useKeyboardFlow } from '../../core/hooks/useKeyboardFlow';
import { itemFreeGoodsValue, itemGross, summarizeItems, validationErrorsByLine } from '../../core/utils/lineItems';

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

export function PurchaseEntryPanel({
    suppliers,
    paymentModes,
    paymentTypes,
    ocrDraft,
    clearOcrDraft,
    onSuccess,
    searchProducts,
    products,
    setProducts,
    setQuickSupplierOpen,
}) {
    const { notification } = App.useApp();
    const [purchaseForm] = Form.useForm();
    const purchaseEntryRef = useRef(null);
    const [purchaseItems, setPurchaseItems] = useState([{ ...emptyPurchaseItem }]);
    const [purchaseLineErrors, setPurchaseLineErrors] = useState({});
    const [quickProductOpen, setQuickProductOpen] = useState(false);

    useKeyboardFlow(purchaseEntryRef, {
        enabled: true,
        autofocus: true,
        onSubmit: () => purchaseForm.submit(),
        onAddRow: addPurchaseRow,
        resetKey: 'entry',
    });

    function productOptions() {
        return products.map((product) => ({
            value: product.id,
            label: `${product.name} ${product.sku ? `(${product.sku})` : ''}`,
            product,
        }));
    }

    function updateRow(index, patch) {
        setPurchaseItems((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, ...patch } : row));
    }

    function addPurchaseRow() {
        setPurchaseItems((rows) => [...rows, { ...emptyPurchaseItem }]);
    }

    function removeRow(index) {
        const nextRows = purchaseItems.filter((_, rowIndex) => rowIndex !== index);
        setPurchaseItems(nextRows.length ? nextRows : [{ ...emptyPurchaseItem }]);
    }

    const purchaseSummary = useMemo(() => summarizeItems(purchaseItems, 'purchase_price'), [purchaseItems]);

    async function submitPurchase(values) {
        try {
            const payload = {
                ...values,
                purchase_date: values.purchase_date.format('YYYY-MM-DD'),
                due_date: values.due_date?.format('YYYY-MM-DD'),
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
            onSuccess(data.print_url);
        } catch (error) {
            const errors = validationErrors(error);
            setPurchaseLineErrors(validationErrorsByLine(errors, 'items'));
            purchaseForm.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Purchase failed', description: error?.response?.data?.message || error.message });
        }
    }

    const productSelect = (row, index) => (
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
                updateRow(index, {
                    product_id,
                    purchase_price: product.purchase_price || product.selling_price || product.mrp || 0,
                    mrp: product.mrp || row.mrp || 0,
                    cc_rate: product.cc_rate || row.cc_rate || 0,
                });
            }}
            className="full-width"
        />
    );

    const purchaseColumns = [
        { key: 'product', title: 'Product', render: (row, index) => productSelect(row, index), width: 280 },
        { key: 'batch', title: 'Batch', render: (row, index) => <Input value={row.batch_no} onChange={(event) => updateRow(index, { batch_no: event.target.value })} />, width: 150 },
        { key: 'expiry', title: 'Expiry', render: (row, index) => <SmartDatePicker value={row.expires_at} onChange={(expires_at) => updateRow(index, { expires_at })} />, width: 150 },
        { key: 'quantity', title: 'Qty', render: (row, index) => <InputNumber min={0.001} value={row.quantity} onChange={(quantity) => updateRow(index, { quantity })} />, width: 100 },
        { key: 'free_quantity', title: 'Free Qty', render: (row, index) => <InputNumber min={0} value={row.free_quantity} onChange={(free_quantity) => updateRow(index, { free_quantity })} />, width: 105 },
        { key: 'mrp', title: 'MRP', render: (row, index) => <InputNumber min={0} value={row.mrp} onChange={(mrp) => updateRow(index, { mrp })} />, width: 115 },
        { key: 'rate', title: 'Rate', render: (row, index) => <InputNumber min={0} value={row.purchase_price} onChange={(purchase_price) => updateRow(index, { purchase_price })} />, width: 115 },
        { key: 'cc_rate', title: 'CC %', render: (row, index) => <InputNumber min={0} max={100} value={row.cc_rate} onChange={(cc_rate) => updateRow(index, { cc_rate })} />, width: 95 },
        { key: 'discount_percent', title: 'Disc %', render: (row, index) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateRow(index, { discount_percent })} />, width: 95 },
        { key: 'free_goods', title: 'Free Goods', className: 'line-money-cell', render: (row) => <Money value={itemFreeGoodsValue(row)} />, width: 120 },
        { key: 'amount', title: 'Amount', className: 'line-money-cell', render: (row) => <Money value={itemGross(row, 'purchase_price')} />, width: 120 },
    ];

    return (
        <>
            <Card>
                <div ref={purchaseEntryRef} data-keyboard-flow="true">
                    <Form form={purchaseForm} layout="vertical" onFinish={submitPurchase} initialValues={{ purchase_date: dayjs(), paid_amount: 0, payment_type: 'credit' }}>
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
                            <Form.Item name="supplier_invoice_no" label="Supplier Bill No"><Input /></Form.Item>
                            <Form.Item name="purchase_date" label="Purchase Date" rules={[{ required: true }]}><SmartDatePicker /></Form.Item>
                            <Form.Item name="due_date" label="Due Date"><SmartDatePicker /></Form.Item>
                        </div>
                        <div className="form-grid form-grid-3">
                            <Form.Item name="payment_type" label="Payment Type"><Select options={paymentTypes} /></Form.Item>
                            <Form.Item name="payment_mode_id" label="Payment Mode">
                                <Select
                                    allowClear
                                    showSearch
                                    optionFilterProp="label"
                                    options={paymentModes.map((item) => ({ value: item.id, label: item.name }))}
                                />
                            </Form.Item>
                            <Form.Item name="paid_amount" label="Paid Amount"><InputNumber min={0} className="full-width" /></Form.Item>
                        </div>
                        <Form.Item name="notes" label="Remarks"><Input.TextArea rows={3} /></Form.Item>
                        <TransactionLineItems
                            rows={purchaseItems}
                            columns={purchaseColumns}
                            errors={purchaseLineErrors}
                            addLabel="Add Item"
                            onAdd={addPurchaseRow}
                            onRemove={removeRow}
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
                </div>
            </Card>

            <QuickProductModal
                open={quickProductOpen}
                onClose={() => setQuickProductOpen(false)}
                onCreated={(product) => setProducts((current) => [product, ...current.filter((item) => item.id !== product.id)])}
            />
        </>
    );
}
