import React from 'react';
import { Card, Col, Row, Timeline } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';

const books = [
    ['Vouchers', 'Payment in/out and manual adjustment vouchers with balanced entries.'],
    ['Day book', 'Date-wise transaction book for sales, purchases, payments and expenses.'],
    ['Cash and bank book', 'Separate movement views for cash and bank accounts.'],
    ['Ledger and trial balance', 'Party/product/account ledgers with room to mature into stricter double-entry.'],
];

export function AccountingPage() {
    return (
        <div className="page-stack">
            <PageHeader title="Accounting" description="Reliable vouchers, payment books, ledgers and trial-balance-ready structure" />
            <Row gutter={[16, 16]}>
                <Col xs={24} xl={14}>
                    <Card title="Accounting Surface">
                        <Timeline items={books.map(([title, children]) => ({ color: 'green', children: <div><strong>{title}</strong><p>{children}</p></div> }))} />
                    </Card>
                </Col>
                <Col xs={24} xl={10}>
                    <Card title="Posting Discipline">
                        <ul className="plain-list">
                            <li>Sales, purchase, return and payment postings should not live in controllers.</li>
                            <li>Voucher writes require transactions and validation of party/account references.</li>
                            <li>Reports read from indexed books and never load all rows into React.</li>
                        </ul>
                    </Card>
                </Col>
            </Row>
        </div>
    );
}
