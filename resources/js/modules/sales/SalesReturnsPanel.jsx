import React, { useEffect, useState } from 'react';
import { App, Button, Card, DatePicker, Form, Input, InputNumber, Select, Space, Table } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { Money } from '../../core/components/Money';
import { FormDrawer } from '../../core/components/FormDrawer';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';

export function SalesReturnsPanel() {
    const { notification } = App.useApp();
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, per_page: 20, total: 0 });
    const [loading, setLoading] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [invoiceOptions, setInvoiceOptions] = useState([]);
    const [returnItems, setReturnItems] = useState([]);
    const [form] = Form.useForm();

    useEffect(() => { loadReturns(1); }, [range]);

    async function loadReturns(page = 1) {
        setLoading(true);
        try {
            const { data } = await http.get(endpoints.salesReturns, {
                params: { page, per_page: meta.per_page, from: range?.[0]?.format('YYYY-MM-DD'), to: range?.[1]?.format('YYYY-MM-DD') },
            });
            setRows(data.data || []);
            setMeta(data.meta || meta);
        } finally { setLoading(false); }
    }

    async function searchInvoices(q) {
        const { data } = await http.get(endpoints.salesReturnInvoiceOptions, { params: { q } });
        setInvoiceOptions(data.data || []);
    }

    async function loadInvoiceItems(invoiceId) {
        if (!invoiceId) { setReturnItems([]); return; }
        const { data } = await http.get(endpoints.salesReturnInvoiceItems(invoiceId));
        setReturnItems((data.data || []).map((item) => ({
            ...item,
            return_qty: 0,
        })));
    }

    function openDrawer() {
        form.resetFields();
        form.setFieldsValue({ return_date: dayjs() });
        setReturnItems([]);
        setDrawerOpen(true);
        searchInvoices('');
    }

    function updateReturnQty(itemId, qty) {
        setReturnItems((prev) => prev.map((item) => item.id === itemId ? { ...item, return_qty: qty } : item));
    }

    async function submit(values) {
        const items = returnItems
            .filter((item) => item.return_qty > 0)
            .map((item) => ({
                sales_invoice_item_id: item.id,
                product_id: item.product_id,
                batch_id: item.batch_id,
                quantity: item.return_qty,
                unit_price: item.unit_price,
            }));

        if (!items.length) {
            notification.warning({ message: 'Add at least one return item with quantity > 0' });
            return;
        }

        try {
            await http.post(endpoints.salesReturns, {
                ...values,
                return_date: values.return_date.format('YYYY-MM-DD'),
                items,
            });
            notification.success({ message: 'Sales return created' });
            setDrawerOpen(false);
            loadReturns(1);
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function deleteReturn(record) {
        try {
            await http.delete(`${endpoints.salesReturns}/${record.id}`);
            notification.success({ message: 'Return deleted' });
            loadReturns(meta.current_page);
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Delete failed' });
        }
    }

    const returnTotal = returnItems.reduce((sum, item) => sum + (item.return_qty * item.unit_price), 0);

    return (
        <div className="page-stack">
            <Card>
                <div className="table-toolbar table-toolbar-wide">
                    <DatePicker.RangePicker value={range} onChange={setRange} />
                    <Button type="primary" icon={<PlusOutlined />} onClick={openDrawer}>New Sales Return</Button>
                </div>
                <Table rowKey="id" loading={loading} dataSource={rows}
                    pagination={{ current: meta.current_page, pageSize: meta.per_page, total: meta.total, onChange: loadReturns }}
                    columns={[
                        { title: 'Return #', dataIndex: 'return_no', width: 120 },
                        { title: 'Date', dataIndex: 'return_date_display', width: 130 },
                        { title: 'Invoice', dataIndex: 'invoice_no' },
                        { title: 'Customer', dataIndex: 'customer_name' },
                        { title: 'Items', dataIndex: 'items_count', width: 80, align: 'center' },
                        { title: 'Total', dataIndex: 'total_amount', align: 'right', width: 140, render: (v) => <Money value={v} /> },
                        { title: 'Reason', dataIndex: 'reason', ellipsis: true },
                        {
                            title: '', width: 80, render: (_, record) => (
                                <Button size="small" danger icon={<DeleteOutlined />} onClick={() => deleteReturn(record)} />
                            ),
                        },
                    ]}
                    scroll={{ x: 'max-content' }}
                />
            </Card>

            <FormDrawer
                title="New Sales Return"
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                width={640}
                footer={<Button type="primary" onClick={() => form.submit()} block>Create Return (NPR {returnTotal.toFixed(2)})</Button>}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="sales_invoice_id" label="Select Invoice" rules={[{ required: true }]}>
                        <Select showSearch filterOption={false} onSearch={searchInvoices} onFocus={() => searchInvoices('')}
                            onChange={loadInvoiceItems}
                            options={invoiceOptions.map((inv) => ({
                                value: inv.id,
                                label: `${inv.invoice_no} — ${inv.customer_name} — NPR ${inv.grand_total}`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item name="return_date" label="Return Date" rules={[{ required: true }]}><DatePicker className="full-width" /></Form.Item>
                    <Form.Item name="reason" label="Reason"><Input /></Form.Item>
                    <Form.Item name="notes" label="Notes"><Input.TextArea rows={2} /></Form.Item>
                </Form>

                {returnItems.length > 0 && (
                    <Card size="small" title="Invoice Items — Enter Return Qty" style={{ marginTop: 16 }}>
                        <Table rowKey="id" dataSource={returnItems} pagination={false} size="small"
                            columns={[
                                { title: 'Product', dataIndex: 'product_name' },
                                { title: 'Sold Qty', dataIndex: 'quantity', width: 90, align: 'right' },
                                { title: 'Price', dataIndex: 'unit_price', width: 100, align: 'right', render: (v) => <Money value={v} /> },
                                {
                                    title: 'Return Qty', width: 120, render: (_, item) => (
                                        <InputNumber size="small" min={0} max={item.quantity}
                                            value={item.return_qty}
                                            onChange={(v) => updateReturnQty(item.id, v || 0)}
                                            className="full-width"
                                        />
                                    ),
                                },
                                {
                                    title: 'Return Total', width: 110, align: 'right',
                                    render: (_, item) => <Money value={item.return_qty * item.unit_price} />,
                                },
                            ]}
                        />
                    </Card>
                )}
            </FormDrawer>
        </div>
    );
}
