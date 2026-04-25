import React from 'react';
import { Drawer } from 'antd';

export function FormDrawer({ title, open, onClose, children, footer, width = 560 }) {
    return (
        <Drawer
            title={title}
            open={open}
            onClose={onClose}
            width={width}
            destroyOnHidden
            footer={footer}
        >
            {children}
        </Drawer>
    );
}
