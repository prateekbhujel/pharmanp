import React from 'react';
import { Modal } from 'antd';

export function FormModal(props) {
    return <Modal centered className="intent-modal" destroyOnHidden {...props} />;
}
