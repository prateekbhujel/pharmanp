import React, { useEffect, useMemo, useRef, useState } from 'react';
import { App, Button, Card, Form, Input, InputNumber, Select, Space, Table } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, RollbackOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { TransactionLineItems } from '../../core/components/TransactionLineItems';
import { DateText } from '../../core/components/DateText';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { accountCatalog, voucherTypeOptions } from '../../core/utils/accountCatalog';
import { validationErrorsByLine } from '../../core/utils/lineItems';
import { appUrl } from '../../core/utils/url';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { dateRangeParams } from '../../core/utils/dateFilters';
import { useKeyboardFlow } from '../../core/hooks/useKeyboardFlow';
import { ExpensesPanel } from './ExpensesPanel';
import { PaymentsPanel } from './PaymentsPanel';

const emptyEntry = { account_type: 'cash', entry_type: 'debit', amount: 0 };

function accountingRouteState() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    if (section === 'payments') {
        return { tab: 'payments', book: 'day-book' };
    }

    if (section === 'expenses') {
        return { tab: 'expenses', book: 'day-book' };
    }

    if (['day-book', 'cash-book', 'bank-book', 'ledger', 'trial-balance'].includes(section)) {
        return { tab: 'books', book: section };
    }

    if (section === 'vouchers') {
        return { tab: 'voucher', book: 'day-book' };
    }

    return { tab: 'voucher', book: 'day-book' };
}

function goToAccounting(path) {
    window.history.pushState({}, '', appUrl(path));
    window.dispatchEvent(new PopStateEvent('popstate'));
}

function defaultVoucherEntries() {
    return [{ ...emptyEntry }, { account_type: 'sales', entry_type: 'credit', amount: 0 }];
}

export function AccountingPage() {
    const { notification } = App.useApp();
    const [routeKey, setRouteKey] = useState(0);
    const routeState = useMemo(() => accountingRouteState(), [routeKey]);
    const [entries, setEntries] = useState(defaultVoucherEntries());
    const [entryErrors, setEntryErrors] = useState({});
    const [customers, setCustomers] = useState([]);
    const [suppliers, setSuppliers] = useState([]);
    const [voucherMode, setVoucherMode] = useState('list');
    const [editingVoucher, setEditingVoucher] = useState(null);
    const [voucherRows, setVoucherRows] = useState([]);
    const [voucherMeta, setVoucherMeta] = useState({ current_page: 1, per_page: 15, total: 0 });
    const [voucherLoading, setVoucherLoading] = useState(false);
    const [voucherSearch, setVoucherSearch] = useState('');
    const [voucherRange, setVoucherRange] = useState([]);
    const [form] = Form.useForm();
    const voucherFormRef = useRef(null);

    useEffect(() => {
        const handleRoute = () => setRouteKey((value) => value + 1);
        window.addEventListener('popstate', handleRoute);

        return () => window.removeEventListener('popstate', handleRoute);
    }, []);

    useEffect(() => {
        loadParties();
        const handleKeys = (event) => {
            if (event.altKey && event.key === 's' && routeState.tab === 'voucher' && voucherMode === 'form') {
                event.preventDefault();
                form.submit();
            }
            if (event.altKey && event.key === 'n') {
                event.preventDefault();
                goToAccounting('/app/accounting/vouchers');
                openVoucher();
            }
            if (event.altKey && event.key === 'a' && routeState.tab === 'voucher' && voucherMode === 'form') {
                event.preventDefault();
                setEntries((current) => [...current, { ...emptyEntry }]);
            }
        };
        window.addEventListener('keydown', handleKeys);

        return () => window.removeEventListener('keydown', handleKeys);
    }, [routeState.tab, voucherMode]);

    useEffect(() => {
        if (routeState.tab === 'voucher') {
            loadVouchers(1);
        }
    }, [routeState.tab, voucherSearch, voucherRange]);

    useKeyboardFlow(voucherFormRef, {
        enabled: routeState.tab === 'voucher' && voucherMode === 'form',
        autofocus: routeState.tab === 'voucher' && voucherMode === 'form',
        onSubmit: () => {
            if (debit === credit && debit > 0) {
                form.submit();
            }
        },
        onAddRow: addVoucherEntry,
        resetKey: voucherMode,
    });

    async function loadParties() {
        const [{ data: customerData }, { data: supplierData }] = await Promise.all([
            http.get(endpoints.customerOptions),
            http.get(endpoints.supplierOptions),
        ]);
        setCustomers(customerData.data || []);
        setSuppliers(supplierData.data || []);
    }

    async function loadVouchers(page = 1, pageSize = voucherMeta.per_page) {
        setVoucherLoading(true);
        try {
            const { data } = await http.get(endpoints.vouchers, {
                params: {
                    page,
                    per_page: pageSize,
                    search: voucherSearch || undefined,
                    ...dateRangeParams(voucherRange),
                },
            });
            setVoucherRows(data.data || []);
            setVoucherMeta(data.meta || voucherMeta);
        } catch (error) {
            notification.error({ message: 'Voucher list failed', description: error?.response?.data?.message || error.message });
        } finally {
            setVoucherLoading(false);
        }
    }

    function updateEntry(index, patch) {
        setEntries(entries.map((entry, rowIndex) => rowIndex === index ? { ...entry, ...patch } : entry));
    }

    function addVoucherEntry() {
        setEntries((rows) => [...rows, { ...emptyEntry }]);
    }

    function resetVoucherForm() {
        setEntryErrors({});
        setEditingVoucher(null);
        setEntries(defaultVoucherEntries());
        form.resetFields();
        form.setFieldsValue({ voucher_date: dayjs(), voucher_type: 'journal' });
    }

    async function openVoucher(record = null) {
        resetVoucherForm();

        if (record?.id) {
            setVoucherLoading(true);
            try {
                const { data } = await http.get(`${endpoints.vouchers}/${record.id}`);
                const voucher = data.data;
                setEditingVoucher(voucher);
                form.setFieldsValue({
                    voucher_date: voucher.voucher_date ? dayjs(voucher.voucher_date) : dayjs(),
                    voucher_type: voucher.voucher_type || 'journal',
                    notes: voucher.notes,
                });
                setEntries((voucher.entries || []).map((entry) => ({
                    account_type: entry.account_type,
                    party_type: entry.party_type,
                    party_id: entry.party_id,
                    entry_type: entry.entry_type,
                    amount: entry.amount,
                    notes: entry.notes,
                })));
            } catch (error) {
                notification.error({ message: 'Voucher load failed', description: error?.response?.data?.message || error.message });
                return;
            } finally {
                setVoucherLoading(false);
            }
        }

        setVoucherMode('form');
    }

    async function submit(values) {
        try {
            const payload = {
                ...values,
                voucher_date: values.voucher_date.format('YYYY-MM-DD'),
                entries,
            };

            if (editingVoucher) {
                await http.put(`${endpoints.vouchers}/${editingVoucher.id}`, payload);
                notification.success({ message: 'Voucher updated' });
            } else {
                await http.post(endpoints.vouchers, payload);
                notification.success({ message: 'Voucher posted' });
            }

            resetVoucherForm();
            setVoucherMode('list');
            loadVouchers(1);
        } catch (error) {
            const errors = validationErrors(error);
            setEntryErrors(validationErrorsByLine(errors, 'entries'));
            form.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.split('.'), errors: messages })));
            notification.error({ message: 'Voucher failed', description: error?.response?.data?.message || error.message });
        }
    }

    function deleteVoucher(record) {
        confirmDelete({
            title: `Delete ${record.voucher_no}?`,
            content: 'The voucher and its accounting ledger postings will be removed.',
            onOk: async () => {
                await http.delete(`${endpoints.vouchers}/${record.id}`);
                notification.success({ message: 'Voucher deleted' });
                loadVouchers(voucherMeta.current_page);
            },
        });
    }

    const debit = useMemo(() => entries.filter((entry) => entry.entry_type === 'debit').reduce((sum, entry) => sum + Number(entry.amount || 0), 0), [entries]);
    const credit = useMemo(() => entries.filter((entry) => entry.entry_type === 'credit').reduce((sum, entry) => sum + Number(entry.amount || 0), 0), [entries]);

    const voucherEntryColumns = [
        {
            key: 'account',
            title: 'Account',
            render: (row, index) => (
                <Select
                    value={row.account_type}
                    onChange={(account_type) => updateEntry(index, { account_type })}
                    options={accountCatalog}
                    className="full-width"
                    optionFilterProp="label"
                    showSearch
                />
            ),
            width: 220,
        },
        { key: 'type', title: 'Type', render: (row, index) => <Select value={row.entry_type} onChange={(entry_type) => updateEntry(index, { entry_type })} options={[{ value: 'debit', label: 'Debit' }, { value: 'credit', label: 'Credit' }]} />, width: 120 },
        {
            key: 'party_type',
            title: 'Party Type',
            render: (row, index) => (
                <Select
                    allowClear
                    value={row.party_type}
                    onChange={(party_type) => updateEntry(index, { party_type, party_id: undefined })}
                    options={[{ value: 'supplier', label: 'Supplier' }, { value: 'customer', label: 'Customer' }, { value: 'other', label: 'Other' }]}
                />
            ),
            width: 140,
        },
        {
            key: 'party',
            title: 'Party',
            render: (row, index) => (
                row.party_type === 'customer' ? (
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        value={row.party_id}
                        onChange={(party_id) => updateEntry(index, { party_id })}
                        options={customers.map((item) => ({ value: item.id, label: item.name }))}
                    />
                ) : row.party_type === 'supplier' ? (
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        value={row.party_id}
                        onChange={(party_id) => updateEntry(index, { party_id })}
                        options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                    />
                ) : (
                    <Input disabled placeholder="Optional" />
                )
            ),
            width: 180,
        },
        { key: 'amount', title: 'Amount', render: (row, index) => <InputNumber min={0} value={row.amount} onChange={(amount) => updateEntry(index, { amount })} className="full-width" />, width: 140 },
        { key: 'notes', title: 'Notes', render: (row, index) => <Input value={row.notes} onChange={(event) => updateEntry(index, { notes: event.target.value })} />, width: 220 },
    ];

    const voucherListColumns = [
        {
            title: 'SN',
            key: '__serial',
            width: 68,
            align: 'center',
            className: 'table-serial-cell',
            render: (_, __, index) => ((voucherMeta.current_page - 1) * voucherMeta.per_page) + index + 1,
        },
        { title: 'Voucher No', dataIndex: 'voucher_no', width: 160 },
        { title: 'Date', dataIndex: 'voucher_date', width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Type', dataIndex: 'voucher_type_label', width: 160, render: (value) => <PharmaBadge tone="info">{value}</PharmaBadge> },
        { title: 'Entries', dataIndex: 'entries_count', width: 90, align: 'center' },
        { title: 'Amount', dataIndex: 'total_amount', width: 150, align: 'right', render: (value) => <Money value={value} /> },
        { title: 'Notes', dataIndex: 'notes', ellipsis: true, render: (value) => value || '-' },
        {
            title: 'Action',
            key: 'actions',
            fixed: 'right',
            width: 112,
            render: (_, record) => (
                <Space className="table-action-buttons">
                    <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openVoucher(record)} />
                    <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => deleteVoucher(record)} />
                </Space>
            ),
        },
    ];

    function renderVoucherForm() {
        return (
            <Card
                title={editingVoucher ? `Edit ${editingVoucher.voucher_no}` : 'New Voucher Entry'}
                extra={<Button icon={<RollbackOutlined />} onClick={() => { resetVoucherForm(); setVoucherMode('list'); }}>Back to List</Button>}
            >
                <div ref={voucherFormRef} data-keyboard-flow="true">
                    <Form form={form} layout="vertical" onFinish={submit} initialValues={{ voucher_date: dayjs(), voucher_type: 'journal' }}>
                        <div className="form-grid">
                            <Form.Item name="voucher_date" label="Voucher Date" rules={[{ required: true }]}><SmartDatePicker /></Form.Item>
                            <Form.Item name="voucher_type" label="Voucher Type" rules={[{ required: true }]}>
                                <Select options={voucherTypeOptions} />
                            </Form.Item>
                        </div>
                        <Form.Item name="notes" label="Narration"><Input.TextArea rows={2} /></Form.Item>
                        <TransactionLineItems
                            rows={entries}
                            columns={voucherEntryColumns}
                            errors={entryErrors}
                            addLabel="Add Entry"
                            minRows={2}
                            onAdd={addVoucherEntry}
                            onRemove={(index) => setEntries(entries.filter((_, rowIndex) => rowIndex !== index))}
                            summary={[
                                { label: 'Debit', value: <Money value={debit} /> },
                                { label: 'Credit', value: <Money value={credit} /> },
                                { label: 'Difference', value: <Money value={Math.abs(debit - credit)} />, strong: true },
                            ]}
                            actions={<Button type="primary" htmlType="submit" disabled={debit !== credit || debit <= 0}>{editingVoucher ? 'Update Voucher' : 'Post Voucher'}</Button>}
                        />
                    </Form>
                </div>
            </Card>
        );
    }

    function renderVoucherList() {
        return (
            <Card title="Voucher List">
                <div className="table-toolbar table-toolbar-vouchers">
                    <Input.Search value={voucherSearch} onChange={(event) => setVoucherSearch(event.target.value)} placeholder="Search voucher no, type or narration" allowClear />
                    <SmartDatePicker.RangePicker value={voucherRange} onChange={(range) => setVoucherRange(range || [])} />
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => openVoucher()}>New Voucher</Button>
                </div>
                <Table
                    rowKey="id"
                    loading={voucherLoading}
                    dataSource={voucherRows}
                    columns={voucherListColumns}
                    pagination={{
                        current: voucherMeta.current_page,
                        pageSize: voucherMeta.per_page,
                        total: voucherMeta.total,
                        showSizeChanger: true,
                        pageSizeOptions: ['10', '15', '20', '25', '50', '100'],
                        onChange: loadVouchers,
                    }}
                    scroll={{ x: 'max-content' }}
                />
            </Card>
        );
    }

    function renderContent() {
        if (routeState.tab === 'payments') return <PaymentsPanel />;
        if (routeState.tab === 'expenses') return <ExpensesPanel />;
        if (routeState.tab === 'books') return <div className="screen-center">Redirecting to Unified Reports...</div>;

        return voucherMode === 'form' ? renderVoucherForm() : renderVoucherList();
    }

    return (
        <div className="page-stack">
            <PageHeader
                actions={routeState.tab === 'books' && (
                    <Button type="primary" onClick={() => goToAccounting('/app/accounting/vouchers')}>Vouchers</Button>
                )}
            />
            {renderContent()}
        </div>
    );
}
