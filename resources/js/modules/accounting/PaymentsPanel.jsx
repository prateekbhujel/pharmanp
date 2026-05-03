import React, { useEffect, useRef, useState } from 'react';
import { App, Button, Card, Descriptions, Form, Input, InputNumber, Modal, Segmented, Select, Space, Switch, Table } from 'antd';
import { DeleteOutlined, EditOutlined, EyeOutlined, PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { Money } from '../../core/components/Money';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { FormModal } from '../../core/components/FormModal';
import { QuickDropdownOptionModal } from '../../core/components/QuickDropdownOptionModal';
import { ExportButtons } from '../../core/components/ListToolbarActions';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { dateRangeParams } from '../../core/utils/dateFilters';
import { useKeyboardFlow } from '../../core/hooks/useKeyboardFlow';

export function PaymentsPanel() {
    const { notification } = App.useApp();
    const initialDirection = ['in', 'out'].includes(new URLSearchParams(window.location.search).get('direction'))
        ? new URLSearchParams(window.location.search).get('direction')
        : undefined;
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, per_page: 20, total: 0 });
    const [lookups, setLookups] = useState({ payment_modes: [] });
    const [loading, setLoading] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [viewingPayment, setViewingPayment] = useState(null);
    const [range, setRange] = useState([]);
    const [direction, setDirection] = useState(initialDirection);
    const [deletedMode, setDeletedMode] = useState(false);
    const [outstandingBills, setOutstandingBills] = useState([]);
    const [allocations, setAllocations] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [suppliers, setSuppliers] = useState([]);
    const [quickPaymentModeOpen, setQuickPaymentModeOpen] = useState(false);
    const [form] = Form.useForm();
    const paymentFormRef = useRef(null);

    useEffect(() => { loadPayments(1); loadParties(); }, [range, direction, deletedMode]);

    useKeyboardFlow(paymentFormRef, {
        enabled: drawerOpen,
        autofocus: drawerOpen,
        onSubmit: () => form.submit(),
        resetKey: drawerOpen,
    });

    async function loadParties() {
        const [{ data: c }, { data: s }] = await Promise.all([
            http.get(endpoints.customerOptions),
            http.get(endpoints.supplierOptions),
        ]);
        setCustomers(c.data || []);
        setSuppliers(s.data || []);
    }

    async function loadPayments(page = 1, pageSize = meta.per_page) {
        setLoading(true);
        try {
            const { data } = await http.get(endpoints.payments, {
                params: {
                    page, per_page: pageSize, direction,
                    deleted: deletedMode ? 1 : undefined,
                    ...dateRangeParams(range),
                },
            });
            setRows(data.data || []);
            setMeta(data.meta || meta);
            setLookups(data.lookups || lookups);
        } finally { setLoading(false); }
    }

    async function openDrawer(record = null) {
        setOutstandingBills([]);
        setAllocations([]);
        form.resetFields();

        if (record?.id) {
            const { data } = await http.get(`${endpoints.payments}/${record.id}`);
            const payment = data.data;
            setEditingId(payment.id);
            form.setFieldsValue({
                ...payment,
                payment_date: dayjs(payment.payment_date),
                payment_mode_id: payment.payment_mode_id || lookups.payment_modes.find((mode) => mode.name === payment.payment_mode)?.id,
            });
            setOutstandingBills(payment.allocations || []);
            setAllocations((payment.allocations || []).map((allocation) => ({
                bill_id: allocation.bill_id,
                bill_type: allocation.bill_type,
                allocated_amount: allocation.allocated_amount,
            })));
        } else {
            setEditingId(null);
            form.setFieldsValue({
                payment_date: dayjs(),
                direction: direction || 'out',
                party_type: (direction || 'out') === 'in' ? 'customer' : 'supplier',
            });
        }

        setDrawerOpen(true);
    }

    async function viewPayment(record) {
        try {
            const { data } = await http.get(`${endpoints.payments}/${record.id}`);
            setViewingPayment(data.data);
        } catch (error) {
            notification.error({ message: 'Payment details failed', description: error?.response?.data?.message || error.message });
        }
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

    function deletePayment(record) {
        confirmDelete({
            title: `Delete ${record.payment_no}?`,
            content: 'Linked bill allocations and accounting postings for this payment will be reversed.',
            onOk: async () => {
                await http.delete(`${endpoints.payments}/${record.id}`);
                notification.success({ message: 'Payment deleted' });
                loadPayments(meta.current_page);
            },
        });
    }

    const partyType = Form.useWatch('party_type', form);
    const partyOptions = partyType === 'customer'
        ? customers.map((c) => ({ value: c.id, label: c.name }))
        : suppliers.map((s) => ({ value: s.id, label: s.name }));

    const columns = [
        {
            title: 'SN',
            key: '__serial',
            width: 68,
            align: 'center',
            className: 'table-serial-cell',
            render: (_, __, index) => ((meta.current_page - 1) * meta.per_page) + index + 1,
        },
        { title: 'No', dataIndex: 'payment_no', width: 120 },
        { title: 'Date', dataIndex: 'payment_date', width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Direction', dataIndex: 'direction_label', width: 130, render: (v, r) => <PharmaBadge tone={r.direction === 'in' ? 'success' : 'warning'} dot>{v}</PharmaBadge> },
        { title: 'Party', dataIndex: 'party_name' },
        { title: 'Mode', dataIndex: 'payment_mode', width: 120 },
        { title: 'Amount', dataIndex: 'amount', align: 'right', width: 140, render: (v) => <Money value={v} /> },
        { title: 'Bills', dataIndex: 'linked_bills', width: 70, align: 'center' },
        {
            title: 'Action', width: 112, fixed: 'right', render: (_, record) => (
                record.deleted_at ? (
                    <PharmaBadge tone="deleted">Deleted</PharmaBadge>
                ) : (
                    <Space className="table-action-buttons">
                        <Button aria-label="View" icon={<EyeOutlined />} onClick={() => viewPayment(record)} />
                        <Button aria-label="Print" icon={<PrinterOutlined />} onClick={() => window.open(record.print_url, '_blank')} />
                        <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openDrawer(record)} />
                        <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => deletePayment(record)} />
                    </Space>
                )
            ),
        },
    ];

    return (
        <div className="page-stack">
            <Card>
                <div className="table-toolbar table-toolbar-payments">
                    <Segmented
                        className="payment-direction-segmented"
                        value={direction || 'all'}
                        onChange={(value) => {
                            const nextDirection = value === 'all' ? undefined : value;
                            setDirection(nextDirection);
                            const url = new URL(window.location.href);
                            if (nextDirection) {
                                url.searchParams.set('direction', nextDirection);
                            } else {
                                url.searchParams.delete('direction');
                            }
                            window.history.replaceState({}, '', url.toString());
                        }}
                        options={[
                            { value: 'all', label: 'All' },
                            { value: 'in', label: 'In' },
                            { value: 'out', label: 'Out' },
                        ]}
                    />
                    <SmartDatePicker.RangePicker value={range} onChange={setRange} />
                    <label className="table-switch">
                        <Switch checked={deletedMode} onChange={setDeletedMode} />
                        <span>View Deleted</span>
                    </label>
                    <ExportButtons basePath={endpoints.datasetExport('payments')} params={{ direction, deleted: deletedMode ? 1 : undefined, ...dateRangeParams(range) }} />
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer()}>New Payment</Button>
                </div>
                <Table rowKey="id" loading={loading} dataSource={rows} columns={columns}
                    pagination={{
                        current: meta.current_page,
                        pageSize: meta.per_page,
                        total: meta.total,
                        showSizeChanger: true,
                        pageSizeOptions: ['10', '15', '20', '25', '50', '100'],
                        onChange: loadPayments,
                    }}
                    scroll={{ x: 'max-content' }}
                />
            </Card>
            <FormModal
                title={editingId ? 'Edit Payment' : 'New Payment'}
                open={drawerOpen}
                onCancel={() => setDrawerOpen(false)}
                onOk={() => form.submit()}
                okText="Save Payment"
                width={860}
                destroyOnHidden
            >
                <div ref={paymentFormRef} data-keyboard-flow="true">
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
                        <Form.Item name="payment_date" label="Date" rules={[{ required: true }]}><SmartDatePicker /></Form.Item>
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
                                    { title: 'Date', dataIndex: 'bill_date', width: 110, render: (value) => <DateText value={value} style="compact" /> },
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
                </div>
            </FormModal>
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
            <Modal
                title={`Payment Details: ${viewingPayment?.payment_no || ''}`}
                open={!!viewingPayment}
                onCancel={() => setViewingPayment(null)}
                footer={[
                    <Button key="print" icon={<PrinterOutlined />} onClick={() => window.open(viewingPayment?.print_url, '_blank')}>Print</Button>,
                    <Button key="close" onClick={() => setViewingPayment(null)}>Close</Button>,
                ]}
                width={780}
                destroyOnHidden
            >
                {viewingPayment && (
                    <div className="page-stack">
                        <Descriptions bordered size="small" column={2}>
                            <Descriptions.Item label="Date"><DateText value={viewingPayment.payment_date} style="compact" /></Descriptions.Item>
                            <Descriptions.Item label="Direction"><PharmaBadge tone={viewingPayment.direction === 'in' ? 'success' : 'warning'} dot>{viewingPayment.direction_label}</PharmaBadge></Descriptions.Item>
                            <Descriptions.Item label="Party">{viewingPayment.party_name}</Descriptions.Item>
                            <Descriptions.Item label="Mode">{viewingPayment.payment_mode}</Descriptions.Item>
                            <Descriptions.Item label="Reference">{viewingPayment.reference_no || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Amount"><Money value={viewingPayment.amount} /></Descriptions.Item>
                            <Descriptions.Item label="Notes" span={2}>{viewingPayment.notes || '-'}</Descriptions.Item>
                        </Descriptions>
                        <Table
                            rowKey={(row) => `${row.bill_type}-${row.bill_id}`}
                            dataSource={viewingPayment.allocations || []}
                            pagination={false}
                            size="small"
                            columns={[
                                { title: 'Bill', dataIndex: 'bill_number' },
                                { title: 'Date', dataIndex: 'bill_date', render: (value) => <DateText value={value} style="compact" /> },
                                { title: 'Total', dataIndex: 'net_amount', align: 'right', render: (value) => <Money value={value} /> },
                                { title: 'Allocated', dataIndex: 'allocated_amount', align: 'right', render: (value) => <Money value={value} /> },
                            ]}
                            locale={{ emptyText: 'No bill allocations. This is an on-account payment.' }}
                        />
                    </div>
                )}
            </Modal>
        </div>
    );
}
