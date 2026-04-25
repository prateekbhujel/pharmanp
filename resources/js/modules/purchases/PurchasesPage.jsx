import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, DatePicker, Form, Input, InputNumber, Select, Space, Table, Tabs } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';

const emptyPurchaseItem = {
    product_id: null,
    batch_no: '',
    expires_at: null,
    quantity: 1,
    free_quantity: 0,
    purchase_price: 0,
    mrp: 0,
    discount_percent: 0,
};

const emptyOrderItem = {
    product_id: null,
    quantity: 1,
    unit_price: 0,
    discount_percent: 0,
};

export function PurchasesPage() {
    const { notification } = App.useApp();
    const [suppliers, setSuppliers] = useState([]);
    const [products, setProducts] = useState([]);
    const [purchaseItems, setPurchaseItems] = useState([{ ...emptyPurchaseItem }]);
    const [orderItems, setOrderItems] = useState([{ ...emptyOrderItem }]);
    const [purchaseForm] = Form.useForm();
    const [orderForm] = Form.useForm();

    useEffect(() => {
        http.get(endpoints.supplierOptions).then(({ data }) => setSuppliers(data.data));
        searchProducts('');
    }, []);

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

    const purchaseTotal = useMemo(() => purchaseItems.reduce((sum, item) => {
        const gross = (Number(item.quantity) || 0) * (Number(item.purchase_price) || 0);
        return sum + gross - (gross * (Number(item.discount_percent) || 0) / 100);
    }, 0), [purchaseItems]);

    const orderTotal = useMemo(() => orderItems.reduce((sum, item) => {
        const gross = (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
        return sum + gross - (gross * (Number(item.discount_percent) || 0) / 100);
    }, 0), [orderItems]);

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
            await http.post(endpoints.purchases, payload);
            notification.success({ message: 'Purchase posted and stock received' });
            purchaseForm.resetFields();
            setPurchaseItems([{ ...emptyPurchaseItem }]);
        } catch (error) {
            purchaseForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name: name.split('.'), errors })));
            notification.error({ message: 'Purchase failed', description: error?.response?.data?.message || error.message });
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
        } catch (error) {
            orderForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name: name.split('.'), errors })));
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
            onChange={(product_id, option) => {
                const product = option.product;
                updateRow(rows, setRows, index, {
                    product_id,
                    [priceField]: product.purchase_price || product.selling_price || product.mrp || 0,
                    mrp: product.mrp || row.mrp || 0,
                });
            }}
            className="full-width"
        />
    );

    const purchaseColumns = [
        { title: 'Product', render: (_, row, index) => productSelect(row, index, purchaseItems, setPurchaseItems, 'purchase_price'), width: 260 },
        { title: 'Batch', render: (_, row, index) => <Input value={row.batch_no} onChange={(event) => updateRow(purchaseItems, setPurchaseItems, index, { batch_no: event.target.value })} />, width: 150 },
        { title: 'Expiry', render: (_, row, index) => <DatePicker value={row.expires_at} onChange={(expires_at) => updateRow(purchaseItems, setPurchaseItems, index, { expires_at })} />, width: 150 },
        { title: 'Qty', render: (_, row, index) => <InputNumber min={0.001} value={row.quantity} onChange={(quantity) => updateRow(purchaseItems, setPurchaseItems, index, { quantity })} />, width: 100 },
        { title: 'Free', render: (_, row, index) => <InputNumber min={0} value={row.free_quantity} onChange={(free_quantity) => updateRow(purchaseItems, setPurchaseItems, index, { free_quantity })} />, width: 100 },
        { title: 'Rate', render: (_, row, index) => <InputNumber min={0} value={row.purchase_price} onChange={(purchase_price) => updateRow(purchaseItems, setPurchaseItems, index, { purchase_price })} />, width: 120 },
        { title: 'MRP', render: (_, row, index) => <InputNumber min={0} value={row.mrp} onChange={(mrp) => updateRow(purchaseItems, setPurchaseItems, index, { mrp })} />, width: 120 },
        { title: '', render: (_, row, index) => <Button danger icon={<DeleteOutlined />} onClick={() => removeRow(purchaseItems, setPurchaseItems, index, emptyPurchaseItem)} />, width: 70 },
    ];

    const orderColumns = [
        { title: 'Product', render: (_, row, index) => productSelect(row, index, orderItems, setOrderItems, 'unit_price'), width: 320 },
        { title: 'Qty', render: (_, row, index) => <InputNumber min={0.001} value={row.quantity} onChange={(quantity) => updateRow(orderItems, setOrderItems, index, { quantity })} />, width: 120 },
        { title: 'Rate', render: (_, row, index) => <InputNumber min={0} value={row.unit_price} onChange={(unit_price) => updateRow(orderItems, setOrderItems, index, { unit_price })} />, width: 120 },
        { title: 'Discount %', render: (_, row, index) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateRow(orderItems, setOrderItems, index, { discount_percent })} />, width: 130 },
        { title: '', render: (_, row, index) => <Button danger icon={<DeleteOutlined />} onClick={() => removeRow(orderItems, setOrderItems, index, emptyOrderItem)} />, width: 70 },
    ];

    return (
        <div className="page-stack">
            <PageHeader title="Purchase" description="Purchase orders and batch-creating purchase entry" />

            <Tabs
                items={[
                    {
                        key: 'entry',
                        label: 'Purchase Entry',
                        children: (
                            <Card>
                                <Form form={purchaseForm} layout="vertical" onFinish={submitPurchase} initialValues={{ purchase_date: dayjs(), paid_amount: 0 }}>
                                    <div className="form-grid">
                                        <Form.Item name="supplier_id" label="Supplier" rules={[{ required: true }]}>
                                            <Select showSearch optionFilterProp="label" options={suppliers.map((item) => ({ value: item.id, label: item.name }))} />
                                        </Form.Item>
                                        <Form.Item name="supplier_invoice_no" label="Supplier Bill No"><Input /></Form.Item>
                                        <Form.Item name="purchase_date" label="Purchase Date" rules={[{ required: true }]}><DatePicker className="full-width" /></Form.Item>
                                        <Form.Item name="paid_amount" label="Paid Amount"><InputNumber min={0} className="full-width" /></Form.Item>
                                    </div>
                                    <Table rowKey={(_, index) => index} pagination={false} columns={purchaseColumns} dataSource={purchaseItems} scroll={{ x: 1120 }} />
                                    <div className="transaction-footer">
                                        <Button icon={<PlusOutlined />} onClick={() => setPurchaseItems([...purchaseItems, { ...emptyPurchaseItem }])}>Add Line</Button>
                                        <Space>
                                            <strong>Total <Money value={purchaseTotal} /></strong>
                                            <Button type="primary" htmlType="submit">Post Purchase</Button>
                                        </Space>
                                    </div>
                                </Form>
                            </Card>
                        ),
                    },
                    {
                        key: 'order',
                        label: 'Purchase Order',
                        children: (
                            <Card>
                                <Form form={orderForm} layout="vertical" onFinish={submitOrder} initialValues={{ order_date: dayjs() }}>
                                    <div className="form-grid">
                                        <Form.Item name="supplier_id" label="Supplier" rules={[{ required: true }]}>
                                            <Select showSearch optionFilterProp="label" options={suppliers.map((item) => ({ value: item.id, label: item.name }))} />
                                        </Form.Item>
                                        <Form.Item name="order_date" label="Order Date" rules={[{ required: true }]}><DatePicker className="full-width" /></Form.Item>
                                        <Form.Item name="expected_date" label="Expected Date"><DatePicker className="full-width" /></Form.Item>
                                    </div>
                                    <Table rowKey={(_, index) => index} pagination={false} columns={orderColumns} dataSource={orderItems} scroll={{ x: 760 }} />
                                    <div className="transaction-footer">
                                        <Button icon={<PlusOutlined />} onClick={() => setOrderItems([...orderItems, { ...emptyOrderItem }])}>Add Line</Button>
                                        <Space>
                                            <strong>Total <Money value={orderTotal} /></strong>
                                            <Button type="primary" htmlType="submit">Create Order</Button>
                                        </Space>
                                    </div>
                                </Form>
                            </Card>
                        ),
                    },
                ]}
            />
        </div>
    );
}
