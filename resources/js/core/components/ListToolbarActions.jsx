import React from 'react';
import { Button, Space } from 'antd';
import { FileExcelOutlined, FilePdfOutlined, UploadOutlined } from '@ant-design/icons';
import { appUrl, basePath } from '../utils/url';
import { downloadAuthenticatedDocument, openDocumentDirectly } from '../utils/documents';

function urlWithParams(path, params = {}) {
    const alreadyScoped = basePath && path.startsWith(`${basePath}/`);
    const resolvedPath = alreadyScoped || path.startsWith('http') ? path : appUrl(path);
    const url = new URL(resolvedPath, window.location.origin);

    Object.entries(params || {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, value);
        }
    });

    return `${url.pathname}${url.search}`;
}

export function ExportButtons({ basePath, params = {} }) {
    return (
        <Space wrap>
            <Button icon={<FileExcelOutlined />} onClick={() => downloadAuthenticatedDocument(urlWithParams(`${basePath}/xlsx`, params), 'pharmanp-export.xlsx')}>Excel</Button>
            <Button icon={<FilePdfOutlined />} onClick={() => openDocumentDirectly(urlWithParams(`${basePath}/pdf`, params), { accept: 'application/pdf' })}>PDF</Button>
        </Space>
    );
}

export function ImportButton({ target }) {
    function openImport() {
        window.history.pushState({}, '', appUrl(`/app/imports?target=${encodeURIComponent(target)}`));
        window.dispatchEvent(new PopStateEvent('popstate'));
    }

    return <Button icon={<UploadOutlined />} onClick={openImport}>Import</Button>;
}
