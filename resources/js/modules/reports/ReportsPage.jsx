import React from 'react';
import { Card, Col, Row, Tag } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';

const reports = [
    'Sales report',
    'Purchase report',
    'Stock report',
    'Low stock report',
    'Expiry report',
    'Supplier performance',
    'Customer ledger',
    'Product movement',
    'MR performance',
];

export function ReportsPage() {
    return (
        <div className="page-stack">
            <PageHeader title="Reports" description="Server-side filtered operational and finance reporting surface" />
            <Row gutter={[16, 16]}>
                <Col xs={24}>
                    <Card title="Report Catalog">
                        <div className="tag-cloud">
                            {reports.map((report) => <Tag key={report} color="blue">{report}</Tag>)}
                        </div>
                    </Card>
                </Col>
                <Col xs={24}>
                    <Card title="Report Standards">
                        <ul className="plain-list">
                            <li>Every report accepts date, company/store and status filters from the backend.</li>
                            <li>Exports use CSV/XLSX for data work and PDF for printable statements.</li>
                            <li>Large reports use pagination/chunked export paths instead of loading everything in memory.</li>
                        </ul>
                    </Card>
                </Col>
            </Row>
        </div>
    );
}
