import React, { useState } from 'react';
import { App, Button, Card, Col, Descriptions, Drawer, Form, Input, InputNumber, Modal, Row, Select, Space, Statistic, Switch, Table, Tabs } from 'antd';
import { BookOutlined, DeleteOutlined, EditOutlined, PlusOutlined, UndoOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { ExportButtons } from '../../core/components/ListToolbarActions';
import { ServerTable } from '../../core/components/ServerTable';
import { FormDrawer } from '../../core/components/FormDrawer';
import { Money } from '../../core/components/Money';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { StatusTag } from '../../core/components/StatusTag';
import { StatusToggle } from '../../core/components/StatusToggle';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { dateRangeParams } from '../../core/utils/dateFilters';
import { appUrl } from '../../core/utils/url';

function PartyTab({ type, onViewLedger }) {
    const { notification } = App.useApp();
    const endpoint = type === 'suppliers' ? endpoints.suppliers : endpoints.customers;
    const restoreEndpoint = type === 'suppliers' ? endpoints.supplierRestore : endpoints.customerRestore;
    const typeEndpoint = type === 'suppliers' ? endpoints.supplierTypes : endpoints.partyTypes;
    const typeField = type === 'suppliers' ? 'supplier_type_id' : 'party_type_id';
    const typeLabel = type === 'suppliers' ? 'Supplier Type' : 'Customer Type';
    const typeNameField = type === 'suppliers' ? 'supplier_type_name' : 'party_type_name';
    const table = useServerTable({ endpoint });
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [typeOptions, setTypeOptions] = useState([]);
    const [quickTypeOpen, setQuickTypeOpen] = useState(false);
    const [form] = Form.useForm();
    const [typeForm] = Form.useForm();
    const deletedMode = Boolean(table.filters.deleted);

    React.useEffect(() => {
        loadTypeOptions();
    }, [type]);

    async function loadTypeOptions() {
        try {
            const { data } = await http.get(typeEndpoint);
            setTypeOptions(data.data || []);
        } catch {
            setTypeOptions([]);
        }
    }

    function open(record = null) {
        setEditing(record);
        form.resetFields();
        form.setFieldsValue(record || { is_active: true, opening_balance: 0, credit_limit: 0 });
        setDrawerOpen(true);
    }

    async function submit(values) {
        try {
            if (editing) {
                await http.put(`${endpoint}/${editing.id}`, values);
                notification.success({ message: 'Party updated' });
            } else {
                await http.post(endpoint, values);
                notification.success({ message: 'Party created' });
            }
            setDrawerOpen(false);
            table.reload();
        } catch (error) {
            form.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Save failed', description: error?.response?.data?.message || error.message });
        }
    }

    async function submitType(values) {
        try {
            const { data } = await http.post(typeEndpoint, values);
            setTypeOptions((current) => [data.data, ...current.filter((item) => item.id !== data.data.id)]);
            form.setFieldValue(typeField, data.data.id);
            typeForm.resetFields();
            setQuickTypeOpen(false);
            notification.success({ message: `${typeLabel} added` });
        } catch (error) {
            typeForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: `${typeLabel} save failed`, description: error?.response?.data?.message || error.message });
        }
    }

    function remove(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'Existing transactions remain; this only disables the party for future use.',
            onOk: async () => {
                await http.delete(`${endpoint}/${record.id}`);
                table.reload();
            },
        });
    }

    function restore(record) {
        confirmDelete({
            title: `Restore ${record.name}?`,
            content: 'This party will return to the active master list.',
            okText: 'Restore',
            danger: false,
            onOk: async () => {
                await http.post(restoreEndpoint(record.id));
                notification.success({ message: 'Party restored' });
                table.reload();
            },
        });
    }

    const columns = [
        { title: 'Name', dataIndex: 'name', sorter: true, field: 'name' },
        { title: 'Type', dataIndex: typeNameField, width: 160, render: (value) => value || '-' },
        { title: 'Phone', dataIndex: 'phone', width: 140 },
        { title: 'PAN', dataIndex: 'pan_number', width: 120 },
        { title: 'Balance', dataIndex: 'current_balance', sorter: true, field: 'current_balance', align: 'right', width: 130, render: (value) => <Money value={value} /> },
        { title: 'Status', dataIndex: 'is_active', width: 150, render: (value, record) => record.deleted_at ? <StatusTag active={false} falseText="Deleted" /> : <StatusToggle value={value} id={record.id} endpoint={endpoint} /> },
        {
            title: 'Action', width: type === 'customers' ? 160 : 112, render: (_, record) => (
                record.deleted_at ? (
                    <Button icon={<UndoOutlined />} onClick={() => restore(record)}>Restore</Button>
                ) : (
                    <Space>
                        {type === 'customers' && <Button icon={<BookOutlined />} onClick={() => onViewLedger?.(record)}>Ledger</Button>}
                        <Button icon={<EditOutlined />} onClick={() => open(record)} />
                        <Button danger icon={<DeleteOutlined />} onClick={() => remove(record)} />
                    </Space>
                )
            )
        },
    ];

    return (
        <Card>
            <div className="table-toolbar table-toolbar-legacy">
                <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder={`Search ${type}`} allowClear />
                <div className="table-switch">
                    <Switch
                        checked={deletedMode}
                        onChange={(deleted) => table.setFilters((filters) => ({ ...filters, deleted: deleted ? 1 : undefined }))}
                    />
                    <span>View Deleted</span>
                </div>
                <ExportButtons basePath={endpoints.datasetExport(type)} params={{ search: table.search, deleted: deletedMode ? 1 : undefined }} />
                <Button type="primary" icon={<PlusOutlined />} onClick={() => open()}>New {type === 'suppliers' ? 'Supplier' : 'Customer'}</Button>
            </div>
            <ServerTable table={table} columns={columns} />

            <FormDrawer
                title={editing ? 'Edit Party' : 'New Party'}
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                footer={<Button type="primary" onClick={() => form.submit()} block>Save</Button>}
            >
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="contact_person" label="Contact Person"><Input /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name={typeField} label={typeLabel}>
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                options={typeOptions.map((item) => ({ value: item.id, label: item.name }))}
                                dropdownRender={(menu) => (
                                    <>
                                        {menu}
                                        <Button type="link" icon={<PlusOutlined />} onClick={() => setQuickTypeOpen(true)}>Quick add {typeLabel.toLowerCase()}</Button>
                                    </>
                                )}
                            />
                        </Form.Item>
                        <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                        <Form.Item name="email" label="Email"><Input /></Form.Item>
                    </div>
                    <Form.Item name="pan_number" label="PAN"><Input /></Form.Item>
                    <Form.Item name="address" label="Address"><Input.TextArea rows={2} /></Form.Item>
                    {type === 'customers' && <Form.Item name="credit_limit" label="Credit Limit"><InputNumber min={0} className="full-width" /></Form.Item>}
                    <Form.Item name="opening_balance" label="Opening Balance"><InputNumber className="full-width" /></Form.Item>
                    <Form.Item name="is_active" label="Active" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </FormDrawer>

            <Modal
                title={`Quick Add ${typeLabel}`}
                open={quickTypeOpen}
                onCancel={() => setQuickTypeOpen(false)}
                onOk={() => typeForm.submit()}
                destroyOnHidden
            >
                <Form form={typeForm} layout="vertical" onFinish={submitType}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    <Form.Item name="code" label="Code"><Input /></Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}

export function PartiesPage() {
    const [ledgerOpen, setLedgerOpen] = useState(false);
    const [ledgerCustomer, setLedgerCustomer] = useState(null);
    const [ledgerData, setLedgerData] = useState(null);
    const [ledgerLoading, setLedgerLoading] = useState(false);
    const [ledgerRange, setLedgerRange] = useState([]);

    async function loadLedger(customer = ledgerCustomer, range = ledgerRange) {
        if (! customer) {
            return;
        }

        setLedgerLoading(true);
        try {
            const { data } = await http.get(endpoints.customerLedger(customer.id), {
                params: dateRangeParams(range),
            });
            setLedgerData(data);
        } finally { setLedgerLoading(false); }
    }

    async function viewLedger(customer) {
        setLedgerCustomer(customer);
        setLedgerOpen(true);
        loadLedger(customer);
    }

    function updateLedgerRange(range) {
        const nextRange = range || [];
        setLedgerRange(nextRange);
        loadLedger(ledgerCustomer, nextRange);
    }

    return (
        <div className="page-stack">
            <PageHeader title="Parties" />
            <Tabs items={[
                { key: 'suppliers', label: 'Suppliers', children: <PartyTab type="suppliers" /> },
                { key: 'customers', label: 'Customers', children: <PartyTab type="customers" onViewLedger={viewLedger} /> },
            ]} />

            <Drawer title={`Ledger — ${ledgerCustomer?.name || ''}`} open={ledgerOpen} onClose={() => setLedgerOpen(false)} width={720} loading={ledgerLoading}>
                {ledgerData && (
                    <div className="page-stack">
                        <div className="table-toolbar">
                            <SmartDatePicker.RangePicker value={ledgerRange} onChange={updateLedgerRange} />
                            <Button onClick={() => window.open(`${appUrl(`/customers/${ledgerCustomer.id}/ledger/print`)}?${new URLSearchParams(dateRangeParams(ledgerRange)).toString()}`, '_blank')}>Print</Button>
                            <Button onClick={() => window.open(`${appUrl(`/customers/${ledgerCustomer.id}/ledger/pdf`)}?${new URLSearchParams(dateRangeParams(ledgerRange)).toString()}`, '_blank')}>PDF</Button>
                        </div>
                        <Row gutter={[12, 12]}>
                            <Col span={6}><Statistic title="Invoiced" value={ledgerData.summary?.total_invoiced} prefix="NPR" /></Col>
                            <Col span={6}><Statistic title="Returned" value={ledgerData.summary?.total_returned} prefix="NPR" /></Col>
                            <Col span={6}><Statistic title="Paid" value={ledgerData.summary?.total_paid} prefix="NPR" /></Col>
                            <Col span={6}><Statistic title="Balance" value={ledgerData.summary?.balance} prefix="NPR" /></Col>
                        </Row>
                        <Tabs size="small" items={[
                            {
                                key: 'invoices', label: `Invoices (${ledgerData.invoices?.length || 0})`, children: (
                                    <Table rowKey="id" dataSource={ledgerData.invoices} pagination={false} size="small" columns={[
                                        { title: 'Invoice', dataIndex: 'invoice_no' },
                                        { title: 'Date', dataIndex: 'date' },
                                        { title: 'Total', dataIndex: 'grand_total', align: 'right', render: (v) => <Money value={v} /> },
                                        { title: 'Paid', dataIndex: 'paid_amount', align: 'right', render: (v) => <Money value={v} /> },
                                        { title: 'Due', dataIndex: 'due', align: 'right', render: (v) => <Money value={v} /> },
                                    ]} />
                                )
                            },
                            {
                                key: 'returns', label: `Returns (${ledgerData.returns?.length || 0})`, children: (
                                    <Table rowKey="id" dataSource={ledgerData.returns} pagination={false} size="small" columns={[
                                        { title: 'Return', dataIndex: 'return_no' },
                                        { title: 'Date', dataIndex: 'date' },
                                        { title: 'Invoice', dataIndex: 'invoice_no' },
                                        { title: 'Amount', dataIndex: 'total_amount', align: 'right', render: (v) => <Money value={v} /> },
                                    ]} />
                                )
                            },
                            {
                                key: 'payments', label: `Payments (${ledgerData.payments?.length || 0})`, children: (
                                    <Table rowKey="id" dataSource={ledgerData.payments} pagination={false} size="small" columns={[
                                        { title: 'Payment', dataIndex: 'payment_no' },
                                        { title: 'Date', dataIndex: 'date' },
                                        { title: 'Mode', dataIndex: 'payment_mode' },
                                        { title: 'Amount', dataIndex: 'amount', align: 'right', render: (v) => <Money value={v} /> },
                                    ]} />
                                )
                            },
                        ]} />
                    </div>
                )}
            </Drawer>
        </div>
    );
}
