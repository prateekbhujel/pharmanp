import React, { useState } from 'react';
import { App, Button, Card, DatePicker, Form, Input, InputNumber, Select, Space, Table, Tabs } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';

const emptyEntry = { account_type: 'cash', entry_type: 'debit', amount: 0 };

export function AccountingPage() {
    const { notification } = App.useApp();
    const [entries, setEntries] = useState([{ ...emptyEntry }, { account_type: 'sales', entry_type: 'credit', amount: 0 }]);
    const [form] = Form.useForm();

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
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name: name.split('.'), errors })));
            notification.error({ message: 'Voucher failed', description: error?.response?.data?.message || error.message });
        }
    }

    const debit = entries.filter((entry) => entry.entry_type === 'debit').reduce((sum, entry) => sum + Number(entry.amount || 0), 0);
    const credit = entries.filter((entry) => entry.entry_type === 'credit').reduce((sum, entry) => sum + Number(entry.amount || 0), 0);

    const columns = [
        { title: 'Account', render: (_, row, index) => <Input value={row.account_type} onChange={(event) => updateEntry(index, { account_type: event.target.value })} />, width: 180 },
        { title: 'Type', render: (_, row, index) => <Select value={row.entry_type} onChange={(entry_type) => updateEntry(index, { entry_type })} options={[{ value: 'debit', label: 'Debit' }, { value: 'credit', label: 'Credit' }]} />, width: 120 },
        { title: 'Party Type', render: (_, row, index) => <Select allowClear value={row.party_type} onChange={(party_type) => updateEntry(index, { party_type })} options={[{ value: 'supplier', label: 'Supplier' }, { value: 'customer', label: 'Customer' }, { value: 'other', label: 'Other' }]} />, width: 140 },
        { title: 'Party ID', render: (_, row, index) => <InputNumber min={1} value={row.party_id} onChange={(party_id) => updateEntry(index, { party_id })} />, width: 110 },
        { title: 'Amount', render: (_, row, index) => <InputNumber min={0} value={row.amount} onChange={(amount) => updateEntry(index, { amount })} />, width: 130 },
        { title: 'Notes', render: (_, row, index) => <Input value={row.notes} onChange={(event) => updateEntry(index, { notes: event.target.value })} />, width: 220 },
        { title: '', render: (_, row, index) => <Button danger icon={<DeleteOutlined />} onClick={() => setEntries(entries.filter((_, rowIndex) => rowIndex !== index))} />, width: 70 },
    ];

    return (
        <div className="page-stack">
            <PageHeader title="Accounting" description="Balanced vouchers and book-ready transaction posting" />
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
                                        <Select options={[
                                            { value: 'payment_in', label: 'Payment In' },
                                            { value: 'payment_out', label: 'Payment Out' },
                                            { value: 'journal', label: 'Journal' },
                                            { value: 'contra', label: 'Contra' },
                                        ]} />
                                    </Form.Item>
                                </div>
                                <Form.Item name="notes" label="Notes"><Input.TextArea rows={2} /></Form.Item>
                                <Table rowKey={(_, index) => index} columns={columns} dataSource={entries} pagination={false} scroll={{ x: 980 }} />
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
                    label: 'Books',
                    children: (
                        <Card title="Books API Ready">
                            <ul className="plain-list">
                                <li>Day book reads dated account transactions.</li>
                                <li>Cash and bank book filter `account_type`.</li>
                                <li>Ledger filters party/account and keeps debit/credit shape stable.</li>
                            </ul>
                        </Card>
                    ),
                },
            ]} />
        </div>
    );
}
