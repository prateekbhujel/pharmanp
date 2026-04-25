import React from 'react';
import { Card, Col, Row, Timeline } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';

const flows = [
    ['Purchase order', 'Supplier selection, product rows, approval and receive workflow.'],
    ['Purchase entry', 'Supplier bill, batch creation, expiry, MRP, purchase price and stock movement posting.'],
    ['Purchase return', 'Return supplier goods by batch and reduce payable/outstanding cleanly.'],
    ['Supplier payment', 'Payment-out allocation against open bills with printable voucher.'],
];

export function PurchasesPage() {
    return (
        <div className="page-stack">
            <PageHeader title="Purchase" description="Supplier orders, purchase bills, batch intake, returns and outstanding control" />
            <Row gutter={[16, 16]}>
                <Col xs={24} xl={14}>
                    <Card title="Transaction Workflow">
                        <Timeline items={flows.map(([title, children]) => ({ color: 'blue', children: <div><strong>{title}</strong><p>{children}</p></div> }))} />
                    </Card>
                </Col>
                <Col xs={24} xl={10}>
                    <Card title="Implementation Rules">
                        <ul className="plain-list">
                            <li>Every stock-impacting purchase action runs in a database transaction.</li>
                            <li>Batch, expiry, purchase price and MRP are captured before stock is posted.</li>
                            <li>Lists must stay server-side paginated and export-ready.</li>
                            <li>Quick-add supplier, product, unit and payment mode stays inside the flow.</li>
                        </ul>
                    </Card>
                </Col>
            </Row>
        </div>
    );
}
