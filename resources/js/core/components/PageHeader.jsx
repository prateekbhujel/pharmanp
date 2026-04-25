import React from 'react';
import { Space, Typography } from 'antd';

export function PageHeader({ title, description, actions }) {
    return (
        <div className="page-header">
            <div>
                <Typography.Title level={2}>{title}</Typography.Title>
                {description ? <Typography.Text type="secondary">{description}</Typography.Text> : null}
            </div>
            {actions ? <Space wrap>{actions}</Space> : null}
        </div>
    );
}
