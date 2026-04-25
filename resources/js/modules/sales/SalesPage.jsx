import React, { useState } from 'react';
import { App, Button, Card, DatePicker, InputNumber, Select, Space, Table } from 'antd';
import { DeleteOutlined, PrinterOutlined } from '@ant-design/icons';
import { BarcodeInput } from '../../core/components/BarcodeInput';
import { PageHeader } from '../../core/components/PageHeader';
import { Money } from '../../core/components/Money';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';

export function SalesPage() {
    const { notification } = App.useApp();
    const [barcode, setBarcode] = useState('');
    const [items, setItems] = useState([]);

    async function scan(value) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { barcode: value } });
        const product = data.data?.[0];

        if (!product) {
            notification.warning({ message: 'No product found for barcode' });
            return;
        }

        setItems((current) => {
            const existing = current.find((item) => item.id === product.id);
            if (existing) {
                return current.map((item) => item.id === product.id ? { ...item, quantity: item.quantity + 1 } : item);
            }

            return [...current, { ...product, quantity: 1, unit_price: product.selling_price || product.mrp }];
        });
        setBarcode('');
    }

    const columns = [
        { title: 'Product', dataIndex: 'name' },
        { title: 'Batch Stock', dataIndex: 'stock_on_hand', align: 'right', width: 120 },
        {
            title: 'Qty',
            dataIndex: 'quantity',
            width: 120,
            render: (value, row) => (
                <InputNumber min={1} value={value} onChange={(quantity) => setItems((current) => current.map((item) => item.id === row.id ? { ...item, quantity } : item))} />
            ),
        },
        { title: 'Rate', dataIndex: 'unit_price', align: 'right', render: (value) => <Money value={value} /> },
        { title: 'Line Total', align: 'right', render: (_, row) => <Money value={(row.quantity || 0) * (row.unit_price || 0)} /> },
        { title: '', width: 70, render: (_, row) => <Button danger icon={<DeleteOutlined />} onClick={() => setItems((current) => current.filter((item) => item.id !== row.id))} /> },
    ];

    const total = items.reduce((sum, item) => sum + ((item.quantity || 0) * (item.unit_price || 0)), 0);

    return (
        <div className="page-stack">
            <PageHeader
                title="Sales / POS"
                description="Barcode-first sales skeleton with batch-aware product lookup"
                actions={<Button icon={<PrinterOutlined />}>Invoice Print Skeleton</Button>}
            />

            <Card>
                <div className="pos-toolbar">
                    <BarcodeInput value={barcode} onChange={setBarcode} onScan={scan} />
                    <Select placeholder="Walk-in Customer" options={[]} className="customer-select" />
                    <DatePicker />
                </div>
                <Table rowKey="id" columns={columns} dataSource={items} pagination={false} />
                <div className="pos-total">
                    <span>Invoice Total</span>
                    <strong><Money value={total} /></strong>
                </div>
            </Card>
        </div>
    );
}
