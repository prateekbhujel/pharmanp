import React, { useEffect, useMemo, useState } from 'react';
import { App, Badge, Button, Card, Col, DatePicker, Form, Input, InputNumber, Row, Select, Space, Statistic, Table } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { Money } from '../../core/components/Money';
import { FormDrawer } from '../../core/components/FormDrawer';
import { QuickDropdownOptionModal } from '../../core/components/QuickDropdownOptionModal';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';

export function ExpensesPanel() {
    const { notification } = App.useApp();
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState({ current_page: 1, per_page: 20, total: 0 });
    const [summary, setSummary] = useState(null);
    const [lookups, setLookups] = useState({ expense_categories: [], payment_modes: [] });
    const [loading, setLoading] = useState(false);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [quickAlias, setQuickAlias] = useState(null);
    const [range, setRange] = useState([dayjs().startOf('month'), dayjs()]);
    const [form] = Form.useForm();

    useEffect(() => { loadExpenses(1); }, [range]);

    async function loadExpenses(page = 1) {
        setLoading(true);
        try {
            const { data } = await http.get(endpoints.expenses, {
                params: {
                    page,
                    per_page: meta.per_page,
                    from: range?.[0]?.format('YYYY-MM-DD'),
                    to: range?.[1]?.format('YYYY-MM-DD'),
                },
            });
            setRows(data.data || []);
            setMeta(data.meta || meta);
            setSummary(data.summary || null);
            setLookups(data.lookups || lookups);
        } finally {
            setLoading(false);
        }
    }

    function openDrawer(record = null) {
        setEditingId(record?.id || null);
        form.resetFields();
        form.setFieldsValue(record ? {
            ...record,
            expense_date: dayjs(record.expense_date),
        } : {
            expense_date: dayjs(),
        });
        setDrawerOpen(true);
    }

    async function submit(values) {
        try {
            await http.post(endpoints.expenses, {
                ...values,
                id: editingId || undefined,
                expense_date: values.expense_date.format('YYYY-MM-DD'),
            });
            notification.success({ message: editingId ? 'Expense updated' : 'Expense added' });
            setDrawerOpen(false);
            loadExpenses(1);
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function deleteExpense(record) {
        try {
            await http.delete(`${endpoints.expenses}/${record.id}`);
            notification.success({ message: 'Expense deleted' });
            loadExpenses(meta.current_page);
        } catch (error) {
            notification.error({ message: error?.response?.data?.message || 'Delete failed' });
        }
    }

    const columns = [
        { title: 'Date', dataIndex: 'expense_date_display', width: 130 },
        { title: 'Category', dataIndex: 'category' },
        { title: 'Vendor', dataIndex: 'vendor_name', render: (v) => v || '-' },
        { title: 'Mode', dataIndex: 'payment_mode', width: 130 },
        { title: 'Amount', dataIndex: 'amount', align: 'right', width: 140, render: (v) => <Money value={v} /> },
        { title: 'Notes', dataIndex: 'notes', ellipsis: true },
        {
            title: '', width: 80, render: (_, record) => (
                <Space>
                    <Button size="small" onClick={() => openDrawer(record)}>Edit</Button>
                    <Button size="small" danger icon={<DeleteOutlined />} onClick={() => deleteExpense(record)} />
                </Space>
            ),
        },
    ];

    return (
        <div className="page-stack">
            <Card>
                <div className="table-toolbar table-toolbar-wide">
                    <DatePicker.RangePicker value={range} onChange={setRange} />
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => openDrawer()}>New Expense</Button>
                </div>
                <Table
                    rowKey="id"
                    loading={loading}
                    dataSource={rows}
                    columns={columns}
                    pagination={{
                        current: meta.current_page,
                        pageSize: meta.per_page,
                        total: meta.total,
                        onChange: loadExpenses,
                    }}
                    scroll={{ x: 'max-content' }}
                />
            </Card>
            <FormDrawer
                title={editingId ? 'Edit Expense' : 'New Expense'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                footer={<Button type="primary" onClick={() => form.submit()} block>Save Expense</Button>}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="expense_date" label="Date" rules={[{ required: true }]}><DatePicker className="full-width" /></Form.Item>
                    <Form.Item name="expense_category_id" label="Category" rules={[{ required: true }]}>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            options={lookups.expense_categories.map((c) => ({ value: c.id, label: c.name }))}
                            dropdownRender={(menu) => (
                                <>
                                    {menu}
                                    <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickAlias('expense_category')}>Quick add category</Button>
                                </>
                            )}
                        />
                    </Form.Item>
                    <Form.Item name="vendor_name" label="Vendor / Payee"><Input /></Form.Item>
                    <Form.Item name="payment_mode_id" label="Payment Mode" rules={[{ required: true }]}>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            options={lookups.payment_modes.map((m) => ({ value: m.id, label: m.name }))}
                            dropdownRender={(menu) => (
                                <>
                                    {menu}
                                    <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickAlias('payment_mode')}>Quick add payment mode</Button>
                                </>
                            )}
                        />
                    </Form.Item>
                    <Form.Item name="amount" label="Amount" rules={[{ required: true }]}><InputNumber min={0.01} className="full-width" /></Form.Item>
                    <Form.Item name="notes" label="Notes"><Input.TextArea rows={3} /></Form.Item>
                </Form>
            </FormDrawer>
            <QuickDropdownOptionModal
                alias={quickAlias || 'payment_mode'}
                open={Boolean(quickAlias)}
                onClose={() => setQuickAlias(null)}
                onCreated={(option) => {
                    if (option.alias === 'payment_mode') {
                        setLookups((current) => ({
                            ...current,
                            payment_modes: [option, ...current.payment_modes.filter((item) => item.id !== option.id)],
                        }));
                        form.setFieldValue('payment_mode_id', option.id);
                    }

                    if (option.alias === 'expense_category') {
                        setLookups((current) => ({
                            ...current,
                            expense_categories: [option, ...current.expense_categories.filter((item) => item.id !== option.id)],
                        }));
                        form.setFieldValue('expense_category_id', option.id);
                    }
                }}
            />
        </div>
    );
}
