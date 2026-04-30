import React from 'react';
import { Drawer } from 'antd';

export function FormDrawer({
    title,
    open,
    onClose,
    children,
    footer,
    size = 'large',
}) {
    return (
        <Drawer
            className="intent-drawer form-drawer"
            title={title}
            open={open}
            onClose={onClose}
            size={size}
            maskClosable={false}
            destroyOnHidden
            footer={footer}
        >
            {children}
        </Drawer>
    );
}
