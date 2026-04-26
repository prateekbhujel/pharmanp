import { Modal } from 'antd';

export function confirmDelete({ title = 'Delete record?', content, onOk, okText = 'Delete', danger = true }) {
    Modal.confirm({
        title,
        content,
        okText,
        okButtonProps: { danger },
        onOk,
    });
}
