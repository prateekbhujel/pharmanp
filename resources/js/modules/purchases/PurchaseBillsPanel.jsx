import React, { useEffect, useState } from 'react';
import { Button, Card, Input, Select, Space } from 'antd';
import { PrinterOutlined } from '@ant-design/icons';
import { DateText } from '../../core/components/DateText';
import { PaymentStatusBadge } from '../../core/components/PharmaBadge';
import { Money } from '../../core/components/Money';
import { ServerTable } from '../../core/components/ServerTable';
import { ExportButtons } from '../../core/components/ListToolbarActions';
import { endpoints } from '../../core/api/endpoints';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { useServerTable } from '../../core/hooks/useServerTable';
import { paymentStatusOptions } from '../../core/utils/accountCatalog';
import { appUrl , backendUrl } from '../../core/utils/url';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';
import { openAuthenticatedDocument } from '../../core/utils/documents';

export function PurchaseBillsPanel({ suppliers }) {
    const [billRange, setBillRange] = useState([]);
    const purchaseTable = useServerTable({
        endpoint: endpoints.purchases,
        defaultSort: { field: 'purchase_date', order: 'desc' },
    });

    useEffect(() => {
        purchaseTable.setFilters((current) => applyDateRangeFilter(current, billRange));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [billRange]);

    const billColumns = [
        { title: 'Bill', dataIndex: 'purchase_no', field: 'purchase_no', sorter: true },
        { title: 'Date', dataIndex: 'purchase_date', field: 'purchase_date', sorter: true, width: 130, render: (value) => <DateText value={value} style="compact" /> },
        { title: 'Supplier Bill', dataIndex: 'supplier_invoice_no', width: 150 },
        { title: 'Supplier', dataIndex: ['supplier', 'name'] },
        { title: 'Due Date', dataIndex: 'due_date', width: 130, render: (value) => value ? <DateText value={value} style="compact" /> : '-' },
        { title: 'Mode', dataIndex: ['payment_mode', 'name'], width: 130, render: (value, row) => value || row.payment_type || '-' },
        { title: 'Payment', dataIndex: 'payment_status', width: 130, render: (value) => <PaymentStatusBadge value={value} /> },
        { title: 'Total', dataIndex: 'grand_total', align: 'right', width: 140, render: (value) => <Money value={value} /> },
        {
            title: 'Action',
            width: 150,
            render: (_, row) => (
                <Space>
                    <Button icon={<PrinterOutlined />} onClick={() => openAuthenticatedDocument(backendUrl(`/purchases/${row.id}/print`))}>Print</Button>
                    <Button onClick={() => openAuthenticatedDocument(backendUrl(`/purchases/${row.id}/pdf`), { accept: 'application/pdf' })}>PDF</Button>
                </Space>
            ),
        },
    ];

    return (
        <Card title="Purchase Bill List">
            <div className="table-toolbar table-toolbar-wide">
                <Input.Search value={purchaseTable.search} onChange={(event) => purchaseTable.setSearch(event.target.value)} placeholder="Search purchase or supplier" allowClear />
                <Select
                    allowClear
                    placeholder="Supplier"
                    value={purchaseTable.filters.supplier_id}
                    onChange={(value) => purchaseTable.setFilters((current) => ({ ...current, supplier_id: value }))}
                    options={suppliers.map((item) => ({ value: item.id, label: item.name }))}
                />
                <Select
                    allowClear
                    placeholder="Payment"
                    value={purchaseTable.filters.payment_status}
                    onChange={(value) => purchaseTable.setFilters((current) => ({ ...current, payment_status: value }))}
                    options={paymentStatusOptions}
                />
                <SmartDatePicker.RangePicker value={billRange} onChange={setBillRange} />
                <ExportButtons basePath={endpoints.datasetExport('purchases')} params={{ ...purchaseTable.filters, search: purchaseTable.search, ...applyDateRangeFilter({}, billRange) }} />
                <Button onClick={purchaseTable.reload}>Refresh</Button>
            </div>
            <ServerTable table={purchaseTable} columns={billColumns} />
        </Card>
    );
}
