import React from 'react';
import { Card, Descriptions, Tabs, Tag } from 'antd';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { useApi } from '../../core/hooks/useApi';

export function SystemUpdatePage() {
    const { data, loading } = useApi(endpoints.updateCheck);

    return (
        <div className="page-stack">
            <PageHeader title="System Update" description="Backup-first update information for shared hosting and VPS deployments" />
            <Card loading={loading}>
                <Tabs
                    items={[
                        {
                            key: 'status',
                            label: 'Status',
                            children: (
                                <Descriptions bordered column={1}>
                                    <Descriptions.Item label="Current Version">{data?.current_version}</Descriptions.Item>
                                    <Descriptions.Item label="Channel"><Tag>{data?.channel}</Tag></Descriptions.Item>
                                    <Descriptions.Item label="Strategy">{data?.strategy}</Descriptions.Item>
                                </Descriptions>
                            ),
                        },
                        {
                            key: 'commands',
                            label: 'CLI Commands',
                            children: (
                                <div className="command-stack">
                                    <code>{data?.commands?.backup}</code>
                                    <code>{data?.commands?.update}</code>
                                </div>
                            ),
                        },
                    ]}
                />
            </Card>
        </div>
    );
}
