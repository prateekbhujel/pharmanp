import React from 'react';
import { Drawer } from 'antd';

export function FormDrawer({ title, open, onClose, children, footer, width = 560 }) {
    return (
        <Drawer
            className="intent-drawer form-drawer"
            title={title}
            open={open}
            onClose={onClose}
            width={width}
            maskClosable={false}
            destroyOnHidden
            footer={footer}
        >
            {children}
        </Drawer>
    );
}
