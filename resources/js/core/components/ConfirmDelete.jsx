import React from 'react';
import { ExclamationCircleFilled } from '@ant-design/icons';
import { Modal } from 'antd';

export function confirmDelete({
    title = 'Delete record?',
    content,
    onOk,
    okText = 'Delete',
    danger = true,
}) {
    Modal.confirm({
        centered: true,
        className: 'intent-modal confirm-intent-modal',
        icon: (
            <ExclamationCircleFilled
                className={danger ? 'confirm-intent-icon-danger' : 'confirm-intent-icon'}
            />
        ),
        title,
        content,
        okText,
        cancelText: 'Cancel',
        okButtonProps: {
            danger,
            className: danger ? 'btn-intent-danger' : 'btn-intent-primary',
        },
        cancelButtonProps: {
            className: 'btn-intent-secondary',
        },
        onOk,
    });
}
