import React from 'react';
import { Alert, Card, Col, Row, Steps, Tag, Timeline } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { useApi } from '../../core/hooks/useApi';

const workflow = [
    { title: 'Company and fiscal year', description: 'Confirm company profile, store, branding, current fiscal year and owner access.' },
    { title: 'Masters', description: 'Add companies/manufacturers, units, categories, suppliers, customers and product records.' },
    { title: 'Opening stock', description: 'Import products, batches and opening balances with validation before stock goes live.' },
    { title: 'Transactions', description: 'Post purchases, sales/POS, returns, payments and vouchers with batch-aware stock movement.' },
    { title: 'Controls', description: 'Assign roles, permissions, reports, backups and update policy before production use.' },
];

export function OnboardingPage() {
    const { data } = useApi(endpoints.featureCatalog);
    const modules = data || {};

    return (
        <div className="page-stack">
            <PageHeader
                title="First-Run Setup Guide"
                description="A practical rollout checklist for pharmacy, wholesale and distributor operations"
            />

            <Alert
                type="info"
                showIcon
                message="Use this page as the first tutorial for a new installation."
                description="It keeps implementation focused on real pharmacy workflows: masters first, stock second, transactions third, controls before production."
            />

            <Card title="Rollout Workflow">
                <Steps direction="vertical" items={workflow} />
            </Card>

            <Row gutter={[16, 16]}>
                {Object.entries(modules).map(([module, items]) => (
                    <Col xs={24} lg={12} xl={8} key={module}>
                        <Card title={module} className="feature-card">
                            <Timeline
                                items={items.map((item) => ({
                                    color: item.status === 'foundation' ? 'green' : 'blue',
                                    children: (
                                        <div>
                                            <strong>{item.name}</strong>
                                            <p>{item.description}</p>
                                            <Tag color={item.status === 'foundation' ? 'green' : 'default'}>{item.status}</Tag>
                                            {item.is_billable && <Tag color="gold">billable</Tag>}
                                        </div>
                                    ),
                                }))}
                            />
                        </Card>
                    </Col>
                ))}
            </Row>
        </div>
    );
}
