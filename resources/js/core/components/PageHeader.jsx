import React from 'react';
import { Space, Typography } from 'antd';

export function PageHeader({ title, description, actions }) {
    const hasCopy = Boolean(title || description);

    if (!hasCopy && !actions) {
        return null;
    }

    return (
        <div className={`page-header ${!hasCopy ? 'page-header-actions-only' : ''}`}>
            {hasCopy ? (
                <div>
                    {title ? <Typography.Title level={2}>{title}</Typography.Title> : null}
                    {description ? <Typography.Text type="secondary">{description}</Typography.Text> : null}
                </div>
            ) : null}
            {actions ? <Space wrap>{actions}</Space> : null}
        </div>
    );
}
