import React, { useEffect, useState } from 'react';
import { App, Badge, Button, Card, DatePicker, Drawer, Form, Input, InputNumber, Modal, Select, Space, Table, Descriptions } from 'antd';
import { CheckCircleOutlined, DollarOutlined, EyeOutlined, PlusOutlined, TruckOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { Money } from '../../core/components/Money';
import { ServerTable } from '../../core/components/ServerTable';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { itemNet, summarizeItems, validationErrorsByLine } from '../../core/utils/lineItems';

const emptyOrderItem = {
    product_id: null,
    quantity: 1,
    unit_price: 0,
    discount_percent: 0,
};

export function PurchaseOrdersPanel() {
    const { notification } = App.useApp();
    const [suppliers, setSuppliers] = useState([]);
    const [products, setProducts] = useState([]);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [viewingOrder, setViewingOrder] = useState(null);
    const [orderItems, setOrderItems] = useState([{ ...emptyOrderItem }]);
    const [orderLineErrors, setOrderLineErrors] = useState({});
    const [orderForm] = Form.useForm();
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);

    const table = useServerTable({
        endpoint: endpoints.purchaseOrders,
        defaultSort: { field: 'order_date', order: 'desc' },
        defaultFilters: {
            from: range[0].format('YYYY-MM-DD'),
            to: range[1].format('YYYY-MM-DD'),
        },
    });

    useEffect(() => {
        loadSuppliers();
        searchProducts('');
    }, []);

    useEffect(() => {
        table.setFilters((current) => ({
            ...current,
            from: range?.[0]?.format('YYYY-MM-DD'),
            to: range?.[1]?.format('YYYY-MM-DD'),
        }));
    }, [range]);

    async function loadSuppliers() {
        const { data } = await http.get(endpoints.supplierOptions);
        setSuppliers(data.data || []);
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

    function updateRow(index, patch) {
        setOrderItems((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, ...patch } : row));
    }

    function removeRow(index) {
        setOrderItems((rows) => {
            const nextRows = rows.filter((_, rowIndex) => rowIndex !== index);
            return nextRows.length ? nextRows : [{ ...emptyOrderItem }];
        });
    }

    const orderSummary = summarizeItems(orderItems, 'unit_price');

    function openNewOrder() {
        orderForm.resetFields();
        orderForm.setFieldsValue({ order_date: dayjs() });
        setOrderItems([{ ...emptyOrderItem }]);
        setOrderLineErrors({});
        setDrawerOpen(true);
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
            setDrawerOpen(false);
            table.reload();
        } catch (error) {
            const errors = validationErrors(error);
            setOrderLineErrors(validationErrorsByLine(errors, 'items'));
            orderForm.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Order failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function viewOrder(record) {
        try {
            const { data } = await http.get(`${endpoints.purchaseOrders}/${record.id}`);
            setViewingOrder(data.data);
        } catch (error) {
            notification.error({ message: 'Failed to load order details' });
        }
    }

    async function processAction(action, endpoint) {
        try {
            const { data } = await http.post(endpoint);
            notification.success({ message: data.message });
            setViewingOrder(data.data);
            table.reload();
        } catch (error) {
            notification.error({ message: `Failed to ${action} order` });
        }
    }

    const orderColumns = [
        {
            key: 'product', title: 'Product', width: 360, render: (row, index) => (
                <Select
                    showSearch
                    value={row.product_id}
                    filterOption={false}
                    onSearch={searchProducts}
                    options={productOptions()}
                    onChange={(product_id, option) => {
                        const product = option.product;
                        updateRow(index, {
                            product_id,
                            unit_price: product.purchase_price || product.selling_price || product.mrp || 0,
                        });
                    }}
                    className="full-width"
                />
            ),
        },
        { key: 'quantity', title: 'Qty', width: 120, render: (row, index) => <InputNumber min={0.001} value={row.quantity} onChange={(quantity) => updateRow(index, { quantity })} /> },
        { key: 'rate', title: 'Rate', width: 120, render: (row, index) => <InputNumber min={0} value={row.unit_price} onChange={(unit_price) => updateRow(index, { unit_price })} /> },
        { key: 'discount_percent', title: 'Discount %', width: 130, render: (row, index) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateRow(index, { discount_percent })} /> },
        { key: 'amount', title: 'Amount', className: 'line-money-cell', width: 130, render: (row) => <Money value={itemNet(row, 'unit_price')} /> },
    ];

    const listColumns = [
        { title: 'Order No', dataIndex: 'order_no', field: 'order_no', sorter: true, width: 150 },
        { title: 'Date', dataIndex: 'order_date', field: 'order_date', sorter: true, width: 130 },
        { title: 'Supplier', dataIndex: ['supplier', 'name'] },
        {
            title: 'Status', dataIndex: 'status', width: 120, render: (val) => {
                const color = val === 'ordered' ? 'blue' : val === 'approved' ? 'cyan' : val === 'received' ? 'green' : val === 'paid' ? 'gold' : 'default';
                return <Badge status="processing" color={color} text={val.toUpperCase()} />;
            },
        },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (value) => <Money value={value} /> },
        {
            title: '', width: 80, render: (_, row) => (
                <Button icon={<EyeOutlined />} onClick={() => viewOrder(row)}>View</Button>
            ),
        },
    ];

    return (
        <div>
            <Card title="Purchase Orders">
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search order or supplier" allowClear />
                    <Select
                        allowClear
                        placeholder="Supplier"
                        value={table.filters.supplier_id}
                        onChange={(value) => table.setFilters((current) => ({ ...current, supplier_id: value }))}
                        options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                    />
                    <Select
                        allowClear
                        placeholder="Status"
                        value={table.filters.status}
                        onChange={(value) => table.setFilters((current) => ({ ...current, status: value }))}
                        options={[
                            { value: 'ordered', label: 'Ordered' },
                            { value: 'approved', label: 'Approved' },
                            { value: 'received', label: 'Received' },
                            { value: 'paid', label: 'Paid' },
                        ]}
                    />
                    <DatePicker.RangePicker value={range} onChange={setRange} />
                    <Button type="primary" icon={<PlusOutlined />} onClick={openNewOrder}>New Order</Button>
                </div>
                <ServerTable table={table} columns={listColumns} />
            </Card>

            <Drawer
                title="New Purchase Order"
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                width={800}
                destroyOnClose
            >
                <Form form={orderForm} layout="vertical" onFinish={submitOrder} initialValues={{ order_date: dayjs() }}>
                    <div className="form-grid">
                        <Form.Item name="supplier_id" label="Supplier" rules={[{ required: true }]}>
                            <Select
                                showSearch
                                optionFilterProp="label"
                                options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                            />
                        </Form.Item>
                        <Form.Item name="order_date" label="Order Date" rules={[{ required: true }]}><DatePicker className="full-width" /></Form.Item>
                        <Form.Item name="expected_date" label="Expected Date"><DatePicker className="full-width" /></Form.Item>
                    </div>
                    <Form.Item name="notes" label="Notes"><Input.TextArea rows={2} /></Form.Item>

                    <TransactionLineItems
                        rows={orderItems}
                        columns={orderColumns}
                        errors={orderLineErrors}
                        addLabel="Add Item"
                        onAdd={() => setOrderItems([...orderItems, { ...emptyOrderItem }])}
                        onRemove={removeRow}
                        summary={[
                            { label: 'Subtotal', value: <Money value={orderSummary.subtotal} /> },
                            { label: 'Discount', value: <Money value={orderSummary.discount} /> },
                            { label: 'Tax', value: <Money value={orderSummary.tax} /> },
                            { label: 'Grand Total', value: <Money value={orderSummary.grandTotal} />, strong: true },
                        ]}
                        actions={<Button type="primary" htmlType="submit">Create Order</Button>}
                    />
                </Form>
            </Drawer>

            <Modal
                title={`Order Details: ${viewingOrder?.order_no || ''}`}
                open={!!viewingOrder}
                onCancel={() => setViewingOrder(null)}
                footer={null}
                width={700}
                destroyOnClose
            >
                {viewingOrder && (
                    <div className="page-stack">
                        <Descriptions bordered size="small" column={2}>
                            <Descriptions.Item label="Supplier">{viewingOrder.supplier?.name}</Descriptions.Item>
                            <Descriptions.Item label="Date">{viewingOrder.order_date}</Descriptions.Item>
                            <Descriptions.Item label="Expected">{viewingOrder.expected_date || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Status">
                                <Badge status="processing" text={viewingOrder.status.toUpperCase()} />
                            </Descriptions.Item>
                            <Descriptions.Item label="Total"><Money value={viewingOrder.grand_total} /></Descriptions.Item>
                            <Descriptions.Item label="Notes">{viewingOrder.notes || '-'}</Descriptions.Item>
                        </Descriptions>

                        <Table
                            dataSource={viewingOrder.items || []}
                            rowKey="id"
                            pagination={false}
                            size="small"
                            columns={[
                                { title: 'Product', dataIndex: ['product', 'name'] },
                                { title: 'Qty', dataIndex: 'quantity', align: 'right' },
                                { title: 'Rate', dataIndex: 'unit_price', align: 'right', render: (v) => <Money value={v} /> },
                                { title: 'Disc %', dataIndex: 'discount_percent', align: 'right' },
                                { title: 'Total', dataIndex: 'line_total', align: 'right', render: (v) => <Money value={v} /> },
                            ]}
                        />

                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 16 }}>
                            {viewingOrder.status === 'ordered' && (
                                <Button type="primary" icon={<CheckCircleOutlined />} onClick={() => processAction('approve', endpoints.purchaseOrderApprove(viewingOrder.id))}>
                                    Approve Order
                                </Button>
                            )}
                            {viewingOrder.status === 'approved' && (
                                <Button type="primary" icon={<TruckOutlined />} onClick={() => processAction('receive', endpoints.purchaseOrderReceive(viewingOrder.id))}>
                                    Mark Received
                                </Button>
                            )}
                            {viewingOrder.status === 'received' && (
                                <Button type="primary" icon={<DollarOutlined />} onClick={() => processAction('pay', endpoints.purchaseOrderPay(viewingOrder.id))}>
                                    Mark Paid
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
