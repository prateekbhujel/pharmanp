import React, { useEffect, useRef, useState } from 'react';
import { App, Button, Card, Descriptions, Form, Input, InputNumber, Modal, Select, Space, Table } from 'antd';
import { DollarOutlined, EyeOutlined, PrinterOutlined } from '@ant-design/icons';
import { DateText } from '../../core/components/DateText';
import { PaymentStatusBadge, PharmaBadge, StatusBadge } from '../../core/components/PharmaBadge';
import { Money } from '../../core/components/Money';
import { ServerTable } from '../../core/components/ServerTable';
import { ExportButtons } from '../../core/components/ListToolbarActions';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useKeyboardFlow } from '../../core/hooks/useKeyboardFlow';
import { paymentStatusOptions } from '../../core/utils/accountCatalog';
import { appUrl , backendUrl } from '../../core/utils/url';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';
import { openAuthenticatedDocument } from '../../core/utils/documents';

export function SalesInvoicesPanel({ customers, medicalRepresentatives, paymentModes }) {
    const { notification } = App.useApp();
    const [invoiceRange, setInvoiceRange] = useState([]);
    const [viewingInvoice, setViewingInvoice] = useState(null);
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const paymentUpdateRef = useRef(null);
    const [paymentForm] = Form.useForm();

    const invoiceTable = useServerTable({
        endpoint: endpoints.salesInvoices,
        defaultSort: { field: 'invoice_date', order: 'desc' },
    });

    useEffect(() => {
        invoiceTable.setFilters((current) => applyDateRangeFilter(current, invoiceRange));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [invoiceRange]);

    useKeyboardFlow(paymentUpdateRef, {
        enabled: paymentModalOpen,
        autofocus: paymentModalOpen,
        onSubmit: () => paymentForm.submit(),
        resetKey: paymentModalOpen,
    });

    async function viewInvoice(row) {
        try {
            const { data } = await http.get(`${endpoints.salesInvoices}/${row.id}`);
            setViewingInvoice(data.data);
            return data.data;
        } catch (error) {
            notification.error({ message: 'Invoice details failed', description: error?.response?.data?.message || error.message });
            return null;
        }
    }

    async function openInvoicePayment(row) {
        const invoice = await viewInvoice(row);
        if (invoice) {
            openPaymentUpdate(invoice);
        }
    }

    function openPaymentUpdate(invoice = viewingInvoice) {
        if (!invoice) return;
        paymentForm.resetFields();
        paymentForm.setFieldsValue({
            paid_amount: invoice.paid_amount || 0,
            payment_mode_id: invoice.payment_mode_id || invoice.payment_mode?.id,
        });
        setPaymentModalOpen(true);
    }

    async function submitPayment(values) {
        if (!viewingInvoice) return;

        try {
            const { data } = await http.patch(endpoints.salesInvoicePayment(viewingInvoice.id), values);
            notification.success({ message: data.message || 'Invoice payment updated' });
            setViewingInvoice(data.data);
            setPaymentModalOpen(false);
            invoiceTable.reload();
        } catch (error) {
            paymentForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Payment update failed', description: error?.response?.data?.message || error.message });
        }
    }

    const invoiceColumns = [
        { title: 'Invoice', dataIndex: 'invoice_no', field: 'invoice_no', sorter: true },
        { title: 'Date', dataIndex: 'invoice_date', field: 'invoice_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Due Date', dataIndex: 'due_date', width: 130, render: (value) => value ? <DateText value={value} style="compact" /> : '-' },
        { title: 'Customer', dataIndex: ['customer', 'name'], render: (value) => value || 'Walk-in' },
        { title: 'MR', dataIndex: ['medical_representative', 'name'], render: (value) => value || '-' },
        { title: 'Mode', dataIndex: ['payment_mode', 'name'], width: 130, render: (value, row) => value || row.payment_type || '-' },
        { title: 'Payment', dataIndex: 'payment_status', width: 130, render: (v) => <PaymentStatusBadge value={v} /> },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (v) => <Money value={v} /> },
        { title: 'Status', dataIndex: 'is_active', width: 110, render: (v) => <StatusBadge value={v} /> },
        {
            title: 'Action',
            width: 240,
            render: (_, row) => (
                <Space>
                    <Button icon={<EyeOutlined />} onClick={() => viewInvoice(row)}>View</Button>
                    <Button icon={<DollarOutlined />} onClick={() => openInvoicePayment(row)}>Payment</Button>
                    <Button icon={<PrinterOutlined />} onClick={() => openAuthenticatedDocument(backendUrl(`/sales/invoices/${row.id}/print`))}>Print</Button>
                    <Button onClick={() => openAuthenticatedDocument(backendUrl(`/sales/invoices/${row.id}/pdf`), { accept: 'application/pdf' })}>PDF</Button>
                </Space>
            ),
        },
    ];

    return (
        <>
            <Card title="Sales Invoice List">
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search value={invoiceTable.search} onChange={(event) => invoiceTable.setSearch(event.target.value)} placeholder="Search invoice or customer" allowClear />
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="Payment"
                        value={invoiceTable.filters.payment_status}
                        onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, payment_status: value }))}
                        options={paymentStatusOptions}
                    />
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="Customer"
                        value={invoiceTable.filters.customer_id}
                        onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, customer_id: value }))}
                        options={customers.map((item) => ({ value: item.id, label: item.name }))}
                    />
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="MR"
                        value={invoiceTable.filters.medical_representative_id}
                        onChange={(value) => invoiceTable.setFilters((current) => ({ ...current, medical_representative_id: value }))}
                        options={medicalRepresentatives.map((item) => ({ value: item.id, label: item.name }))}
                    />
                    <SmartDatePicker.RangePicker value={invoiceRange} onChange={setInvoiceRange} />
                    <ExportButtons basePath={endpoints.datasetExport('sales-invoices')} params={{ ...invoiceTable.filters, search: invoiceTable.search, ...applyDateRangeFilter({}, invoiceRange) }} />
                    <Button onClick={invoiceTable.reload}>Refresh</Button>
                </div>
                <ServerTable table={invoiceTable} columns={invoiceColumns} />
            </Card>

            <Modal
                title={`Invoice Details: ${viewingInvoice?.invoice_no || ''}`}
                open={!!viewingInvoice}
                onCancel={() => setViewingInvoice(null)}
                footer={[
                    <Button key="payment" icon={<DollarOutlined />} onClick={() => openPaymentUpdate()}>Update Payment</Button>,
                    <Button key="print" icon={<PrinterOutlined />} onClick={() => openAuthenticatedDocument(backendUrl(`/sales/invoices/${viewingInvoice?.id}/print`))}>Print</Button>,
                    <Button key="close" onClick={() => setViewingInvoice(null)}>Close</Button>,
                ]}
                width={980}
                destroyOnHidden
            >
                {viewingInvoice && (
                    <div className="page-stack">
                        <Descriptions bordered size="small" column={3}>
                            <Descriptions.Item label="Customer">{viewingInvoice.customer?.name || 'Walk-in'}</Descriptions.Item>
                            <Descriptions.Item label="Date"><DateText value={viewingInvoice.invoice_date} style="compact" /></Descriptions.Item>
                            <Descriptions.Item label="Due Date">{viewingInvoice.due_date ? <DateText value={viewingInvoice.due_date} style="compact" /> : '-'}</Descriptions.Item>
                            <Descriptions.Item label="Payment"><PaymentStatusBadge value={viewingInvoice.payment_status} /></Descriptions.Item>
                            <Descriptions.Item label="MR">{viewingInvoice.medical_representative?.name || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Payment Mode">{viewingInvoice.payment_mode?.name || viewingInvoice.payment_type || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Total"><Money value={viewingInvoice.grand_total} /></Descriptions.Item>
                            <Descriptions.Item label="Paid"><Money value={viewingInvoice.paid_amount} /></Descriptions.Item>
                        </Descriptions>
                        <Table
                            rowKey="id"
                            dataSource={viewingInvoice.items || []}
                            pagination={false}
                            size="small"
                            columns={[
                                { title: 'Product', dataIndex: 'product_name' },
                                { title: 'Batch', dataIndex: 'batch_no', width: 120 },
                                { title: 'Expiry', dataIndex: 'expires_at', width: 120, render: (value) => <DateText value={value} style="compact" /> },
                                { title: 'Qty', dataIndex: 'quantity', align: 'right', width: 90 },
                                { title: 'Rate', dataIndex: 'unit_price', align: 'right', width: 110, render: (value) => <Money value={value} /> },
                                { title: 'Discount', dataIndex: 'discount_amount', align: 'right', width: 110, render: (value) => <Money value={value} /> },
                                { title: 'Total', dataIndex: 'line_total', align: 'right', width: 120, render: (value) => <Money value={value} /> },
                            ]}
                        />
                        <Card size="small" title={`Return History (${viewingInvoice.returns?.length || 0})`}>
                            <Table
                                rowKey="id"
                                dataSource={viewingInvoice.returns || []}
                                pagination={false}
                                size="small"
                                columns={[
                                    { title: 'Return No', dataIndex: 'return_no' },
                                    { title: 'Date', dataIndex: 'return_date', render: (value) => <DateText value={value} style="compact" /> },
                                    { title: 'Status', dataIndex: 'status', render: (value) => <PharmaBadge tone={value}>{value || '-'}</PharmaBadge> },
                                    { title: 'Amount', dataIndex: 'total_amount', align: 'right', render: (value) => <Money value={value} /> },
                                    { title: 'Reason', dataIndex: 'reason', render: (value) => value || '-' },
                                ]}
                                locale={{ emptyText: 'No return history for this invoice.' }}
                            />
                        </Card>
                    </div>
                )}
            </Modal>

            <Modal
                title={`Update Payment: ${viewingInvoice?.invoice_no || ''}`}
                open={paymentModalOpen}
                onCancel={() => setPaymentModalOpen(false)}
                onOk={() => paymentForm.submit()}
                okText="Save Payment"
                destroyOnHidden
            >
                <div ref={paymentUpdateRef} data-keyboard-flow="true">
                    <Form form={paymentForm} layout="vertical" onFinish={submitPayment}>
                        <Descriptions size="small" bordered column={1} className="mb-16">
                            <Descriptions.Item label="Invoice Total"><Money value={viewingInvoice?.grand_total || 0} /></Descriptions.Item>
                        </Descriptions>
                        <Form.Item name="paid_amount" label="Paid Amount" rules={[{ required: true }]}>
                            <InputNumber min={0} max={Number(viewingInvoice?.grand_total || 0)} className="full-width" />
                        </Form.Item>
                        <Form.Item name="payment_mode_id" label="Payment Mode">
                            <Select
                                allowClear
                                showSearch
                                optionFilterProp="label"
                                options={paymentModes.map((item) => ({ value: item.id, label: item.name }))}
                            />
                        </Form.Item>
                    </Form>
                </div>
            </Modal>
        </>
    );
}
