import { Modal } from 'antd';

export function confirmDelete({ title = 'Delete record?', content, onOk }) {
    Modal.confirm({
        title,
        content,
        okText: 'Delete',
        okButtonProps: { danger: true },
        onOk,
    });
}
