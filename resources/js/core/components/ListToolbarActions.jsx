import React, { useState } from 'react';
import { App, Button, Space } from 'antd';
import { FileExcelOutlined, FilePdfOutlined, UploadOutlined } from '@ant-design/icons';
import { appUrl, basePath } from '../utils/url';
import { showRequestError, showRequestSuccess } from '../api/feedback';
import { downloadAuthenticatedDocument, openAuthenticatedDocument } from '../utils/documents';

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
    const { notification } = App.useApp();
    const [exporting, setExporting] = useState(null);
    const excelUrl = urlWithParams(`${basePath}/xlsx`, params);
    const pdfUrl = urlWithParams(`${basePath}/pdf`, params);

    async function downloadExcel() {
        setExporting('xlsx');
        try {
            await downloadAuthenticatedDocument(excelUrl, 'pharmanp-export.xlsx');
            showRequestSuccess(notification, null, 'Excel export downloaded');
        } catch (error) {
            showRequestError(notification, error, 'Excel export failed');
        } finally {
            setExporting(null);
        }
    }

    async function openPdf() {
        setExporting('pdf');
        try {
            await openAuthenticatedDocument(pdfUrl, { accept: 'application/pdf' });
            showRequestSuccess(notification, null, 'PDF export opened');
        } catch (error) {
            showRequestError(notification, error, 'PDF export failed');
        } finally {
            setExporting(null);
        }
    }

    return (
        <Space wrap>
            <Button icon={<FileExcelOutlined />} loading={exporting === 'xlsx'} disabled={!!exporting} onClick={downloadExcel}>Excel</Button>
            <Button icon={<FilePdfOutlined />} loading={exporting === 'pdf'} disabled={!!exporting} onClick={openPdf}>PDF</Button>
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
