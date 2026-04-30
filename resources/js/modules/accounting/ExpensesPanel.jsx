import React, { useEffect, useMemo, useRef, useState } from 'react';
import { App, Button, Card, Col, Form, Input, InputNumber, Row, Select, Space, Statistic, Table } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { Money } from '../../core/components/Money';
import { DateText } from '../../core/components/DateText';
import { FormModal } from '../../core/components/FormModal';
import { QuickDropdownOptionModal } from '../../core/components/QuickDropdownOptionModal';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { dateRangeParams } from '../../core/utils/dateFilters';
import { useKeyboardFlow } from '../../core/hooks/useKeyboardFlow';

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
    const [range, setRange] = useState([]);
    const [form] = Form.useForm();
    const expenseFormRef = useRef(null);

    useEffect(() => { loadExpenses(1); }, [range]);

    useKeyboardFlow(expenseFormRef, {
        enabled: drawerOpen,
        autofocus: drawerOpen,
        onSubmit: () => form.submit(),
        resetKey: drawerOpen,
    });

    async function loadExpenses(page = 1, pageSize = meta.per_page) {
        setLoading(true);
        try {
            const { data } = await http.get(endpoints.expenses, {
                params: {
                    page,
                    per_page: pageSize,
                    ...dateRangeParams(range),
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
        confirmDelete({
            title: 'Delete expense?',
            content: 'The expense and its accounting postings will be removed.',
            onOk: async () => {
                await http.delete(`${endpoints.expenses}/${record.id}`);
                notification.success({ message: 'Expense deleted' });
                loadExpenses(meta.current_page);
            },
        });
    }

    const columns = [
        {
            title: 'SN',
            key: '__serial',
            width: 68,
            align: 'center',
            className: 'table-serial-cell',
            render: (_, __, index) => ((meta.current_page - 1) * meta.per_page) + index + 1,
        },
        { title: 'Date', dataIndex: 'expense_date', width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Category', dataIndex: 'category' },
        { title: 'Vendor', dataIndex: 'vendor_name', render: (v) => v || '-' },
        { title: 'Mode', dataIndex: 'payment_mode', width: 130 },
        { title: 'Amount', dataIndex: 'amount', align: 'right', width: 140, render: (v) => <Money value={v} /> },
        { title: 'Notes', dataIndex: 'notes', ellipsis: true },
        {
            title: 'Action', width: 112, fixed: 'right', render: (_, record) => (
                <Space className="table-action-buttons">
                    <Button aria-label="Edit" icon={<EditOutlined />} onClick={() => openDrawer(record)} />
                    <Button aria-label="Delete" danger icon={<DeleteOutlined />} onClick={() => deleteExpense(record)} />
                </Space>
            ),
        },
    ];

    return (
        <div className="page-stack">
            <Card>
                <div className="table-toolbar table-toolbar-wide">
                    <SmartDatePicker.RangePicker value={range} onChange={setRange} />
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
                        showSizeChanger: true,
                        pageSizeOptions: ['10', '15', '20', '25', '50', '100'],
                        onChange: loadExpenses,
                    }}
                    scroll={{ x: 'max-content' }}
                />
            </Card>
            <FormModal
                title={editingId ? 'Edit Expense' : 'New Expense'}
                open={drawerOpen}
                onCancel={() => setDrawerOpen(false)}
                onOk={() => form.submit()}
                okText="Save Expense"
                width={680}
                destroyOnHidden
            >
                <div ref={expenseFormRef} data-keyboard-flow="true">
                    <Form form={form} layout="vertical" onFinish={submit}>
                        <Form.Item name="expense_date" label="Date" rules={[{ required: true }]}><SmartDatePicker className="full-width" /></Form.Item>
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
                </div>
            </FormModal>
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
