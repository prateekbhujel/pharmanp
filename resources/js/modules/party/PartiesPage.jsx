import React from 'react';
import { Card, Col, Row, Timeline } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';

const masters = [
    ['Suppliers', 'Supplier type, PAN, contact, opening balance and purchase outstanding.'],
    ['Customers', 'Retail/wholesale/customer type, credit limit, receivable and invoice history.'],
    ['MRs and territories', 'Representative ownership, visit tracking, target and order value.'],
    ['Quick create', 'Drawer/modal create flows embedded in purchase, sales and payment forms.'],
];

export function PartiesPage() {
    return (
        <div className="page-stack">
            <PageHeader title="Parties" description="Supplier, customer and representative masters with quick-create workflow" />
            <Row gutter={[16, 16]}>
                <Col xs={24} xl={14}>
                    <Card title="Master Data">
                        <Timeline items={masters.map(([title, children]) => ({ color: 'purple', children: <div><strong>{title}</strong><p>{children}</p></div> }))} />
                    </Card>
                </Col>
                <Col xs={24} xl={10}>
                    <Card title="Integrity Rules">
                        <ul className="plain-list">
                            <li>Soft delete parties with historical transactions.</li>
                            <li>Current balance is adjusted through posted transaction services only.</li>
                            <li>Search, filters and ledgers must stay server-side.</li>
                        </ul>
                    </Card>
                </Col>
            </Row>
        </div>
    );
}
