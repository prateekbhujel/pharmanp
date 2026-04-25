import React from 'react';
import { Drawer } from 'antd';

export function FormDrawer({ title, open, onClose, children, footer }) {
    return (
        <Drawer
            title={title}
            open={open}
            onClose={onClose}
            width={560}
            destroyOnHidden
            footer={footer}
        >
            {children}
        </Drawer>
    );
}
