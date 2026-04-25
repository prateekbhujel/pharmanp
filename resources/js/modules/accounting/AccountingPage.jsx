import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, DatePicker, Form, Input, InputNumber, Select, Space, Statistic, Table, Tabs } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { accountCatalog, voucherTypeOptions } from '../../core/utils/accountCatalog';

const emptyEntry = { account_type: 'cash', entry_type: 'debit', amount: 0 };
const bookOptions = [
    { value: 'day-book', label: 'Day Book' },
    { value: 'cash-book', label: 'Cash Book' },
    { value: 'bank-book', label: 'Bank Book' },
    { value: 'ledger', label: 'Ledger' },
    { value: 'trial-balance', label: 'Trial Balance' },
];

export function AccountingPage() {
    const { notification } = App.useApp();
    const [entries, setEntries] = useState([{ ...emptyEntry }, { account_type: 'sales', entry_type: 'credit', amount: 0 }]);
    const [bookReport, setBookReport] = useState('day-book');
    const [bookRange, setBookRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [bookFilters, setBookFilters] = useState({});
    const [bookRows, setBookRows] = useState([]);
    const [bookSummary, setBookSummary] = useState(null);
    const [bookMeta, setBookMeta] = useState({ current_page: 1, per_page: 20, total: 0 });
    const [bookLoading, setBookLoading] = useState(false);
    const [customers, setCustomers] = useState([]);
    const [suppliers, setSuppliers] = useState([]);
    const [form] = Form.useForm();

    useEffect(() => {
        loadParties();
    }, []);

    useEffect(() => {
        loadBook(1);
    }, [bookReport, bookRange, bookFilters]);

    async function loadParties() {
        const [{ data: customerData }, { data: supplierData }] = await Promise.all([
            http.get(endpoints.customerOptions),
            http.get(endpoints.supplierOptions),
        ]);
        setCustomers(customerData.data || []);
        setSuppliers(supplierData.data || []);
    }

    function updateEntry(index, patch) {
        setEntries(entries.map((entry, rowIndex) => rowIndex === index ? { ...entry, ...patch } : entry));
    }

    async function submit(values) {
        try {
            await http.post(endpoints.vouchers, {
                ...values,
                voucher_date: values.voucher_date.format('YYYY-MM-DD'),
                entries,
            });
            notification.success({ message: 'Voucher posted' });
            form.resetFields();
            setEntries([{ ...emptyEntry }, { account_type: 'sales', entry_type: 'credit', amount: 0 }]);
            loadBook(1);
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name: name.split('.'), errors })));
            notification.error({ message: 'Voucher failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function loadBook(page = 1) {
        setBookLoading(true);
        try {
            const { data } = await http.get(`${endpoints.reports}/${bookReport}`, {
                params: {
                    page,
                    per_page: bookMeta.per_page,
                    from: bookRange?.[0]?.format('YYYY-MM-DD'),
                    to: bookRange?.[1]?.format('YYYY-MM-DD'),
                    ...bookFilters,
                },
            });
            setBookRows(data.data || []);
            setBookMeta(data.meta || bookMeta);
            setBookSummary(data.summary || null);
        } finally {
            setBookLoading(false);
        }
    }

    const debit = useMemo(() => entries.filter((entry) => entry.entry_type === 'debit').reduce((sum, entry) => sum + Number(entry.amount || 0), 0), [entries]);
    const credit = useMemo(() => entries.filter((entry) => entry.entry_type === 'credit').reduce((sum, entry) => sum + Number(entry.amount || 0), 0), [entries]);

    const columns = [
        {
            title: 'Account',
            render: (_, row, index) => (
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
        { title: 'Type', render: (_, row, index) => <Select value={row.entry_type} onChange={(entry_type) => updateEntry(index, { entry_type })} options={[{ value: 'debit', label: 'Debit' }, { value: 'credit', label: 'Credit' }]} />, width: 120 },
        {
            title: 'Party Type',
            render: (_, row, index) => (
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
            title: 'Party',
            render: (_, row, index) => (
                row.party_type === 'customer' ? (
                    <Select
                        allowClear
                        value={row.party_id}
                        onChange={(party_id) => updateEntry(index, { party_id })}
                        options={customers.map((item) => ({ value: item.id, label: item.name }))}
                    />
                ) : row.party_type === 'supplier' ? (
                    <Select
                        allowClear
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
        { title: 'Amount', render: (_, row, index) => <InputNumber min={0} value={row.amount} onChange={(amount) => updateEntry(index, { amount })} className="full-width" />, width: 140 },
        { title: 'Notes', render: (_, row, index) => <Input value={row.notes} onChange={(event) => updateEntry(index, { notes: event.target.value })} />, width: 220 },
        { title: '', render: (_, row, index) => <Button danger icon={<DeleteOutlined />} onClick={() => setEntries(entries.filter((_, rowIndex) => rowIndex !== index))} />, width: 70 },
    ];
    const bookColumns = Object.keys(bookRows[0] || {}).map((key) => ({
        title: key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()),
        dataIndex: key,
        render: (value) => typeof value === 'number' ? value.toLocaleString() : value,
    }));

    return (
        <div className="page-stack">
            <PageHeader title="Accounting" description="Balanced vouchers, books, ledger and trial balance on posted transactions" />
            <Tabs items={[
                {
                    key: 'voucher',
                    label: 'Voucher Entry',
                    children: (
                        <Card>
                            <Form form={form} layout="vertical" onFinish={submit} initialValues={{ voucher_date: dayjs(), voucher_type: 'journal' }}>
                                <div className="form-grid">
                                    <Form.Item name="voucher_date" label="Voucher Date" rules={[{ required: true }]}><DatePicker className="full-width" /></Form.Item>
                                    <Form.Item name="voucher_type" label="Voucher Type" rules={[{ required: true }]}>
                                        <Select options={voucherTypeOptions} />
                                    </Form.Item>
                                </div>
                                <Form.Item name="notes" label="Notes"><Input.TextArea rows={2} /></Form.Item>
                                <Table rowKey={(_, index) => index} columns={columns} dataSource={entries} pagination={false} scroll={{ x: 1080 }} />
                                <div className="transaction-footer">
                                    <Button icon={<PlusOutlined />} onClick={() => setEntries([...entries, { ...emptyEntry }])}>Add Entry</Button>
                                    <Space>
                                        <span>Debit <strong><Money value={debit} /></strong></span>
                                        <span>Credit <strong><Money value={credit} /></strong></span>
                                        <Button type="primary" htmlType="submit" disabled={debit !== credit || debit <= 0}>Post Voucher</Button>
                                    </Space>
                                </div>
                            </Form>
                        </Card>
                    ),
                },
                {
                    key: 'books',
                    label: 'Books & Trial Balance',
                    children: (
                        <div className="page-stack">
                            {bookSummary && (
                                <Row gutter={[16, 16]}>
                                    {Object.entries(bookSummary).map(([key, value]) => (
                                        <Col xs={24} sm={12} xl={6} key={key}>
                                            <Card><Statistic title={key.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())} value={value || 0} /></Card>
                                        </Col>
                                    ))}
                                </Row>
                            )}
                            <Card>
                                <div className="report-filter-grid">
                                    <Select value={bookReport} onChange={(value) => { setBookReport(value); setBookFilters({}); }} options={bookOptions} />
                                    <DatePicker.RangePicker value={bookRange} onChange={setBookRange} />
                                    {bookReport === 'ledger' && (
                                        <>
                                            <Select allowClear placeholder="Account" value={bookFilters.account_type} onChange={(value) => setBookFilters((current) => ({ ...current, account_type: value }))} options={accountCatalog} />
                                            <Select allowClear placeholder="Party Type" value={bookFilters.party_type} onChange={(value) => setBookFilters((current) => ({ ...current, party_type: value, party_id: undefined }))} options={[
                                                { value: 'customer', label: 'Customer' },
                                                { value: 'supplier', label: 'Supplier' },
                                            ]} />
                                            {bookFilters.party_type === 'customer' && <Select allowClear placeholder="Customer" value={bookFilters.party_id} onChange={(value) => setBookFilters((current) => ({ ...current, party_id: value }))} options={customers.map((item) => ({ value: item.id, label: item.name }))} />}
                                            {bookFilters.party_type === 'supplier' && <Select allowClear placeholder="Supplier" value={bookFilters.party_id} onChange={(value) => setBookFilters((current) => ({ ...current, party_id: value }))} options={suppliers.map((item) => ({ value: item.id, label: item.name }))} />}
                                        </>
                                    )}
                                </div>
                                <Table
                                    loading={bookLoading}
                                    rowKey={(_, index) => index}
                                    columns={bookColumns}
                                    dataSource={bookRows}
                                    pagination={{
                                        current: bookMeta.current_page,
                                        pageSize: bookMeta.per_page,
                                        total: bookMeta.total,
                                        onChange: loadBook,
                                    }}
                                    scroll={{ x: true }}
                                />
                            </Card>
                        </div>
                    ),
                },
            ]} />
        </div>
    );
}
