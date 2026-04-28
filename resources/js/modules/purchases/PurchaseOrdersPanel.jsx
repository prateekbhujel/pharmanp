import React, { useEffect, useState } from 'react';
import { App, Button, Card, Drawer, Form, Input, InputNumber, Modal, Select, Space, Table, Descriptions } from 'antd';
import { CheckCircleOutlined, DollarOutlined, EyeOutlined, PlusOutlined, TruckOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { Money } from '../../core/components/Money';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { ServerTable } from '../../core/components/ServerTable';
import { ExportButtons } from '../../core/components/ListToolbarActions';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { itemNet, summarizeItems, validationErrorsByLine } from '../../core/utils/lineItems';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';

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
    const [receivingOrder, setReceivingOrder] = useState(null);
    const [orderItems, setOrderItems] = useState([{ ...emptyOrderItem }]);
    const [receiveItems, setReceiveItems] = useState([]);
    const [orderLineErrors, setOrderLineErrors] = useState({});
    const [receiveLineErrors, setReceiveLineErrors] = useState({});
    const [orderForm] = Form.useForm();
    const [receiveForm] = Form.useForm();
    const [range, setRange] = useState([]);

    const table = useServerTable({
        endpoint: endpoints.purchaseOrders,
        defaultSort: { field: 'order_date', order: 'desc' },
    });

    useEffect(() => {
        loadSuppliers();
        searchProducts('');
    }, []);

    useEffect(() => {
        table.setFilters((current) => applyDateRangeFilter(current, range));
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

    async function openReceive(order) {
        try {
            const { data } = await http.get(`${endpoints.purchaseOrders}/${order.id}`);
            const fullOrder = data.data;
            setReceivingOrder(fullOrder);
            setReceiveLineErrors({});
            receiveForm.resetFields();
            receiveForm.setFieldsValue({
                purchase_date: dayjs(),
                supplier_invoice_no: fullOrder.order_no,
                paid_amount: 0,
            });
            setReceiveItems((fullOrder.items || []).map((item) => ({
                purchase_order_item_id: item.id,
                product_id: item.product_id,
                product_name: item.product?.name,
                batch_no: '',
                expires_at: null,
                quantity: Number(item.quantity || 0),
                free_quantity: 0,
                purchase_price: Number(item.unit_price || item.product?.purchase_price || 0),
                mrp: Number(item.product?.mrp || item.unit_price || 0),
                cc_rate: Number(item.product?.cc_rate || 0),
                discount_percent: Number(item.discount_percent || 0),
            })));
        } catch (error) {
            notification.error({ message: 'Failed to load receive form' });
        }
    }

    function updateReceiveRow(index, patch) {
        setReceiveItems((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, ...patch } : row));
    }

    const receiveSummary = summarizeItems(receiveItems, 'purchase_price');

    async function submitReceive(values) {
        if (!receivingOrder) return;

        try {
            const { data } = await http.post(endpoints.purchaseOrderReceive(receivingOrder.id), {
                ...values,
                purchase_date: values.purchase_date.format('YYYY-MM-DD'),
                items: receiveItems.map((item) => ({
                    ...item,
                    expires_at: item.expires_at?.format('YYYY-MM-DD'),
                    manufactured_at: item.manufactured_at?.format('YYYY-MM-DD'),
                })),
            });
            notification.success({ message: data.message || 'Order received' });
            setReceivingOrder(null);
            setViewingOrder(data.data);
            table.reload();
            if (data.print_url) {
                window.open(data.print_url, '_blank');
            }
        } catch (error) {
            const errors = validationErrors(error);
            setReceiveLineErrors(validationErrorsByLine(errors, 'items'));
            receiveForm.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Receive failed', description: error?.response?.data?.message || error.message });
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
        { title: 'Date', dataIndex: 'order_date', field: 'order_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Supplier', dataIndex: ['supplier', 'name'] },
        { title: 'Status', dataIndex: 'status', width: 130, render: (val) => <PharmaBadge tone={val} dot>{String(val || '-').toUpperCase()}</PharmaBadge> },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (value) => <Money value={value} /> },
        {
            title: 'Action', width: 80, render: (_, row) => (
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
                    <SmartDatePicker.RangePicker value={range} onChange={setRange} />
                    <ExportButtons basePath={endpoints.datasetExport('purchase-orders')} params={{ ...table.filters, search: table.search, ...applyDateRangeFilter({}, range) }} />
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
                        <Form.Item name="order_date" label="Order Date" rules={[{ required: true }]}><SmartDatePicker className="full-width" /></Form.Item>
                        <Form.Item name="expected_date" label="Expected Date"><SmartDatePicker className="full-width" /></Form.Item>
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
                            <Descriptions.Item label="Date"><DateText value={viewingOrder.order_date} style="compact" /></Descriptions.Item>
                            <Descriptions.Item label="Expected"><DateText value={viewingOrder.expected_date} style="compact" /></Descriptions.Item>
                            <Descriptions.Item label="Status">
                                <PharmaBadge tone={viewingOrder.status} dot>{viewingOrder.status.toUpperCase()}</PharmaBadge>
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
                                <Button type="primary" icon={<TruckOutlined />} onClick={() => openReceive(viewingOrder)}>
                                    Receive Stock
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

            <Modal
                title={`Receive Purchase Order: ${receivingOrder?.order_no || ''}`}
                open={!!receivingOrder}
                onCancel={() => setReceivingOrder(null)}
                footer={null}
                width={1100}
                destroyOnClose
            >
                <Form form={receiveForm} layout="vertical" onFinish={submitReceive}>
                    <div className="form-grid">
                        <Form.Item name="supplier_invoice_no" label="Supplier Bill No"><Input /></Form.Item>
                        <Form.Item name="purchase_date" label="Receive Date" rules={[{ required: true }]}><SmartDatePicker className="full-width" /></Form.Item>
                        <Form.Item name="paid_amount" label="Paid Amount"><InputNumber min={0} className="full-width" /></Form.Item>
                    </div>
                    <Form.Item name="notes" label="Receive Notes"><Input.TextArea rows={2} /></Form.Item>

                    <TransactionLineItems
                        rows={receiveItems}
                        errors={receiveLineErrors}
                        addLabel="Add Line"
                        minRows={1}
                        onAdd={() => setReceiveItems((rows) => [...rows, {
                            purchase_order_item_id: null,
                            product_id: null,
                            product_name: '',
                            batch_no: '',
                            expires_at: null,
                            quantity: 1,
                            free_quantity: 0,
                            purchase_price: 0,
                            mrp: 0,
                            cc_rate: 0,
                            discount_percent: 0,
                        }])}
                        onRemove={(index) => setReceiveItems((rows) => rows.filter((_, rowIndex) => rowIndex !== index))}
                        columns={[
                            { key: 'product', title: 'Product', width: 220, render: (row) => <strong>{row.product_name || row.product_id || '-'}</strong> },
                            { key: 'batch', title: 'Batch', width: 140, render: (row, index) => <Input value={row.batch_no} onChange={(event) => updateReceiveRow(index, { batch_no: event.target.value })} /> },
                            { key: 'expiry', title: 'Expiry', width: 150, render: (row, index) => <SmartDatePicker value={row.expires_at} onChange={(expires_at) => updateReceiveRow(index, { expires_at })} /> },
                            { key: 'quantity', title: 'Qty', width: 100, render: (row, index) => <InputNumber min={0.001} value={row.quantity} onChange={(quantity) => updateReceiveRow(index, { quantity })} /> },
                            { key: 'free_quantity', title: 'Free', width: 90, render: (row, index) => <InputNumber min={0} value={row.free_quantity} onChange={(free_quantity) => updateReceiveRow(index, { free_quantity })} /> },
                            { key: 'purchase_price', title: 'Rate', width: 110, render: (row, index) => <InputNumber min={0} value={row.purchase_price} onChange={(purchase_price) => updateReceiveRow(index, { purchase_price })} /> },
                            { key: 'mrp', title: 'MRP', width: 110, render: (row, index) => <InputNumber min={0} value={row.mrp} onChange={(mrp) => updateReceiveRow(index, { mrp })} /> },
                            { key: 'discount_percent', title: 'Disc %', width: 100, render: (row, index) => <InputNumber min={0} max={100} value={row.discount_percent} onChange={(discount_percent) => updateReceiveRow(index, { discount_percent })} /> },
                            { key: 'amount', title: 'Amount', className: 'line-money-cell', width: 120, render: (row) => <Money value={itemNet(row, 'purchase_price')} /> },
                        ]}
                        summary={[
                            { label: 'Subtotal', value: <Money value={receiveSummary.subtotal} /> },
                            { label: 'Discount', value: <Money value={receiveSummary.discount} /> },
                            { label: 'Grand Total', value: <Money value={receiveSummary.grandTotal} />, strong: true },
                        ]}
                        actions={<Button type="primary" htmlType="submit">Receive and Post Purchase</Button>}
                    />
                </Form>
            </Modal>
        </div>
    );
}
