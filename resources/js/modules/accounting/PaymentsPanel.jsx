import React, { useEffect, useState } from 'react';
import { App, Badge, Button, Card, DatePicker, Form, Input, InputNumber, Select, Space, Table } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { Money } from '../../core/components/Money';
import { FormDrawer } from '../../core/components/FormDrawer';
import { QuickDropdownOptionModal } from '../../core/components/QuickDropdownOptionModal';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';

export function PaymentsPanel() {
    const { notification } = App.useApp();
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, per_page: 20, total: 0 });
    const [lookups, setLookups] = useState({ payment_modes: [] });
    const [loading, setLoading] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [direction, setDirection] = useState(undefined);
    const [outstandingBills, setOutstandingBills] = useState([]);
    const [allocations, setAllocations] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [suppliers, setSuppliers] = useState([]);
    const [quickPaymentModeOpen, setQuickPaymentModeOpen] = useState(false);
    const [form] = Form.useForm();

    useEffect(() => { loadPayments(1); loadParties(); }, [range, direction]);

    async function loadParties() {
        const [{ data: c }, { data: s }] = await Promise.all([
            http.get(endpoints.customerOptions),
            http.get(endpoints.supplierOptions),
        ]);
        setCustomers(c.data || []);
        setSuppliers(s.data || []);
    }

    async function loadPayments(page = 1) {
        setLoading(true);
        try {
            const { data } = await http.get(endpoints.payments, {
                params: {
                    page, per_page: meta.per_page, direction,
                    from: range?.[0]?.format('YYYY-MM-DD'),
                    to: range?.[1]?.format('YYYY-MM-DD'),
                },
            });
            setRows(data.data || []);
            setMeta(data.meta || meta);
            setLookups(data.lookups || lookups);
        } finally { setLoading(false); }
    }

    function openDrawer(record = null) {
        setEditingId(record?.id || null);
        setOutstandingBills([]);
        setAllocations([]);
        form.resetFields();
        form.setFieldsValue(record ? {
            ...record,
            payment_date: dayjs(record.payment_date),
            payment_mode_id: record.payment_mode_id || lookups.payment_modes.find((mode) => mode.name === record.payment_mode)?.id,
        } : { payment_date: dayjs(), direction: 'out', party_type: 'supplier' });
        setDrawerOpen(true);
    }

    async function loadBills(partyId, partyType) {
        if (!partyId || !partyType) return;
        try {
            const { data } = await http.get(endpoints.paymentOutstandingBills, { params: { party_id: partyId, party_type: partyType } });
            setOutstandingBills(data.data || []);
            setAllocations([]);
        } catch { setOutstandingBills([]); }
    }

    function updateAllocation(billId, billType, amount) {
        setAllocations((prev) => {
            const existing = prev.filter((a) => a.bill_id !== billId);
            if (amount > 0) existing.push({ bill_id: billId, bill_type: billType, allocated_amount: amount });
            return existing;
        });
    }

    async function submit(values) {
        try {
            await http.post(endpoints.payments, {
                ...values,
                id: editingId || undefined,
                payment_date: values.payment_date.format('YYYY-MM-DD'),
                payment_mode_id: values.payment_mode_id,
                allocations: allocations.filter((a) => a.allocated_amount > 0),
            });
            notification.success({ message: 'Payment saved' });
            setDrawerOpen(false);
            loadPayments(1);
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Save failed', description: error?.response?.data?.message || error.message });
        }
    }

    const partyType = Form.useWatch('party_type', form);
    const partyOptions = partyType === 'customer'
        ? customers.map((c) => ({ value: c.id, label: c.name }))
        : suppliers.map((s) => ({ value: s.id, label: s.name }));

    const columns = [
        { title: 'No', dataIndex: 'payment_no', width: 120 },
        { title: 'Date', dataIndex: 'payment_date_display', width: 130 },
        { title: 'Direction', dataIndex: 'direction_label', width: 120, render: (v, r) => <Badge status={r.direction === 'in' ? 'success' : 'warning'} text={v} /> },
        { title: 'Party', dataIndex: 'party_name' },
        { title: 'Mode', dataIndex: 'payment_mode', width: 120 },
        { title: 'Amount', dataIndex: 'amount', align: 'right', width: 140, render: (v) => <Money value={v} /> },
        { title: 'Bills', dataIndex: 'linked_bills', width: 70, align: 'center' },
        {
            title: '', width: 80, render: (_, record) => (
                <Button size="small" onClick={() => openDrawer(record)}>Edit</Button>
            ),
        },
    ];

    return (
        <div className="page-stack">
            <Card>
                <div className="table-toolbar table-toolbar-wide">
                    <Select allowClear placeholder="Direction" value={direction} onChange={setDirection} style={{ width: 140 }} options={[{ value: 'in', label: 'Payment In' }, { value: 'out', label: 'Payment Out' }]} />
                    <DatePicker.RangePicker value={range} onChange={setRange} />
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer()}>New Payment</Button>
                </div>
                <Table rowKey="id" loading={loading} dataSource={rows} columns={columns}
                    pagination={{ current: meta.current_page, pageSize: meta.per_page, total: meta.total, onChange: loadPayments }}
                    scroll={{ x: 'max-content' }}
                />
            </Card>
            <FormDrawer
                title={editingId ? 'Edit Payment' : 'New Payment'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                width={580}
                footer={<Button type="primary" onClick={() => form.submit()} block>Save Payment</Button>}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="direction" label="Direction" rules={[{ required: true }]}>
                        <Select options={[{ value: 'in', label: 'Payment In (Receive)' }, { value: 'out', label: 'Payment Out (Pay)' }]} />
                    </Form.Item>
                    <Form.Item name="party_type" label="Party Type" rules={[{ required: true }]}>
                        <Select options={[{ value: 'customer', label: 'Customer' }, { value: 'supplier', label: 'Supplier' }]}
                            onChange={() => { form.setFieldValue('party_id', undefined); setOutstandingBills([]); setAllocations([]); }}
                        />
                    </Form.Item>
                    <Form.Item name="party_id" label="Party" rules={[{ required: true }]}>
                        <Select showSearch optionFilterProp="label" options={partyOptions}
                            onChange={(id) => loadBills(id, form.getFieldValue('party_type'))}
                        />
                    </Form.Item>
                    <Form.Item name="payment_date" label="Date" rules={[{ required: true }]}><DatePicker className="full-width" /></Form.Item>
                    <Form.Item name="amount" label="Amount" rules={[{ required: true }]}><InputNumber min={0.01} className="full-width" /></Form.Item>
                    <Form.Item name="payment_mode_id" label="Mode" rules={[{ required: true }]}>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            options={lookups.payment_modes.map((m) => ({ value: m.id, label: m.name }))}
                            dropdownRender={(menu) => (
                                <>
                                    {menu}
                                    <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickPaymentModeOpen(true)}>Quick add payment mode</Button>
                                </>
                            )}
                        />
                    </Form.Item>
                    <Form.Item name="reference_no" label="Reference #"><Input /></Form.Item>
                    <Form.Item name="notes" label="Notes"><Input.TextArea rows={2} /></Form.Item>
                </Form>

                {outstandingBills.length > 0 && (
                    <Card size="small" title="Allocate to Outstanding Bills" style={{ marginTop: 16 }}>
                        <Table rowKey="bill_id" dataSource={outstandingBills} pagination={false} size="small"
                            columns={[
                                { title: 'Bill', dataIndex: 'bill_number', width: 120 },
                                { title: 'Date', dataIndex: 'bill_date', width: 110 },
                                { title: 'Total', dataIndex: 'net_amount', align: 'right', width: 100, render: (v) => <Money value={v} /> },
                                { title: 'Outstanding', dataIndex: 'outstanding', align: 'right', width: 110, render: (v) => <Money value={v} /> },
                                {
                                    title: 'Allocate', width: 120, render: (_, bill) => (
                                        <InputNumber size="small" min={0} max={bill.outstanding} className="full-width"
                                            value={allocations.find((a) => a.bill_id === bill.bill_id)?.allocated_amount || 0}
                                            onChange={(v) => updateAllocation(bill.bill_id, bill.bill_type, v || 0)}
                                        />
                                    ),
                                },
                            ]}
                        />
                    </Card>
                )}
            </FormDrawer>
            <QuickDropdownOptionModal
                alias="payment_mode"
                open={quickPaymentModeOpen}
                onClose={() => setQuickPaymentModeOpen(false)}
                onCreated={(option) => {
                    setLookups((current) => ({
                        ...current,
                        payment_modes: [option, ...current.payment_modes.filter((item) => item.id !== option.id)],
                    }));
                    form.setFieldValue('payment_mode_id', option.id);
                }}
            />
        </div>
    );
}
