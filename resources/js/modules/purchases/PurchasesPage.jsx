import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, DatePicker, Form, Input, InputNumber, Modal, Select, Space, Table, Tabs } from 'antd';
import { DeleteOutlined, PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { QuickProductModal } from '../../core/components/QuickProductModal';
import { ServerTable } from '../../core/components/ServerTable';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { paymentStatusOptions } from '../../core/utils/accountCatalog';
import { appUrl } from '../../core/utils/url';

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
    const [quickSupplierOpen, setQuickSupplierOpen] = useState(false);
    const [quickProductOpen, setQuickProductOpen] = useState(false);
    const [lastPurchasePrintUrl, setLastPurchasePrintUrl] = useState(null);
    const [billRange, setBillRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [purchaseForm] = Form.useForm();
    const [orderForm] = Form.useForm();
    const [supplierForm] = Form.useForm();
    const purchaseTable = useServerTable({
        endpoint: endpoints.purchases,
        defaultSort: { field: 'purchase_date', order: 'desc' },
        defaultFilters: {
            from: billRange[0].format('YYYY-MM-DD'),
            to: billRange[1].format('YYYY-MM-DD'),
        },
    });

    useEffect(() => {
        loadSuppliers();
        searchProducts('');
    }, []);

    useEffect(() => {
        purchaseTable.setFilters((current) => ({
            ...current,
            from: billRange?.[0]?.format('YYYY-MM-DD'),
            to: billRange?.[1]?.format('YYYY-MM-DD'),
        }));
    }, [billRange]);

    async function loadSuppliers() {
        const { data } = await http.get(endpoints.supplierOptions);
        setSuppliers(data.data);
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
            const { data } = await http.post(endpoints.purchases, payload);
            notification.success({ message: 'Purchase posted and stock received' });
            purchaseForm.resetFields();
            setPurchaseItems([{ ...emptyPurchaseItem }]);
            setLastPurchasePrintUrl(data.print_url);
            purchaseTable.reload();
        } catch (error) {
            purchaseForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name: name.split('.'), errors })));
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
    const billColumns = [
        { title: 'Bill', dataIndex: 'purchase_no', field: 'purchase_no', sorter: true },
        { title: 'Date', dataIndex: 'purchase_date', field: 'purchase_date', sorter: true, width: 130 },
        { title: 'Supplier Bill', dataIndex: 'supplier_invoice_no', width: 150 },
        { title: 'Supplier', dataIndex: ['supplier', 'name'] },
        { title: 'Payment', dataIndex: 'payment_status', width: 120 },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (value) => <Money value={value} /> },
        {
            title: '',
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
                title="Purchase"
                description="Purchase orders and batch-creating purchase entry"
                actions={(
                    <Space>
                        <Button icon={<PlusOutlined />} onClick={() => setQuickProductOpen(true)}>Quick Product</Button>
                        <Button disabled={!lastPurchasePrintUrl} icon={<PrinterOutlined />} onClick={() => window.open(lastPurchasePrintUrl, '_blank')}>Print Last Purchase</Button>
                    </Space>
                )}
            />

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
                        key: 'bills',
                        label: 'Purchase Bills',
                        children: (
                            <Card>
                                <div className="table-toolbar">
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
                                    <DatePicker.RangePicker value={billRange} onChange={setBillRange} />
                                    <Button onClick={purchaseTable.reload}>Refresh</Button>
                                </div>
                                <ServerTable table={purchaseTable} columns={billColumns} />
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
