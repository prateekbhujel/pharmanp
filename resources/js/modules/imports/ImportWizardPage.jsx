import React, { useEffect, useMemo, useState } from 'react';
import { Alert, App, Button, Card, Empty, Select, Space, Steps, Table, Upload } from 'antd';
import { CloseCircleOutlined, FileTextOutlined, InboxOutlined } from '@ant-design/icons';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { showRequestError, showRequestSuccess } from '../../core/api/feedback';
import { downloadAuthenticatedDocument } from '../../core/utils/documents';

const acceptedExtensions = ['csv', 'txt', 'xlsx', 'xls'];

function fileExtension(fileName = '') {
    return String(fileName).split('.').pop()?.toLowerCase() || '';
}

function acceptedImportFile(file) {
    return acceptedExtensions.includes(fileExtension(file?.name));
}

export function ImportWizardPage() {
    const { notification } = App.useApp();
    const [targets, setTargets] = useState([]);
    const [target, setTarget] = useState(new URLSearchParams(window.location.search).get('target') || 'products');
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [importResult, setImportResult] = useState(null);
    const [mapping, setMapping] = useState({});
    const [previewing, setPreviewing] = useState(false);
    const [importing, setImporting] = useState(false);
    const [sampleLoading, setSampleLoading] = useState(false);

    useEffect(() => {
        http.get(endpoints.importTargets)
            .then(({ data }) => setTargets(data.data))
            .catch((error) => showRequestError(notification, error, 'Import targets failed'));
    }, []);

    const selectedTarget = useMemo(() => targets.find((item) => item.target === target), [targets, target]);
    const currentStep = importResult ? 2 : preview ? 1 : 0;

    function resetPreviewState() {
        setPreview(null);
        setImportResult(null);
        setMapping({});
    }

    function clearFile() {
        setFile(null);
        resetPreviewState();
    }

    async function runPreview() {
        if (!file) {
            notification.warning({ message: 'Choose a file first' });
            return;
        }

        if (!acceptedImportFile(file)) {
            clearFile();
            notification.error({
                message: 'Unsupported import file',
                description: 'Use CSV, TXT, XLSX or XLS files only.',
            });
            return;
        }

        const formData = new FormData();
        formData.append('target', target);
        formData.append('file', file);
        setPreviewing(true);

        try {
            const { data } = await http.post(endpoints.importPreview, formData);
            setPreview(data.data);
            setImportResult(null);
            setMapping(Object.fromEntries((data.data.detected_columns || []).map((column) => [column, guessField(column, data.data.system_fields)])));
            showRequestSuccess(notification, { data }, 'Import preview ready');
        } catch (error) {
            resetPreviewState();
            showRequestError(notification, error, 'Import preview failed');
        } finally {
            setPreviewing(false);
        }
    }

    async function confirmMapping() {
        if (!preview?.id) {
            notification.warning({ message: 'Preview the file before importing' });
            return;
        }

        setImporting(true);
        try {
            const { data } = await http.post(endpoints.importConfirm, {
                import_job_id: preview.id,
                mapping,
            });
            setPreview(data.data);
            setImportResult(data.data);
            showRequestSuccess(notification, { data }, data.data.status === 'completed' ? 'Import completed' : 'Import finished with review items');
        } catch (error) {
            showRequestError(notification, error, 'Import failed');
        } finally {
            setImporting(false);
        }
    }

    async function downloadSampleTemplate() {
        setSampleLoading(true);
        try {
            await downloadAuthenticatedDocument(endpoints.importSample(target), `${target}-sample.csv`);
            showRequestSuccess(notification, null, 'Sample template downloaded');
        } catch (error) {
            showRequestError(notification, error, 'Sample template failed');
        } finally {
            setSampleLoading(false);
        }
    }

    const previewColumns = [
        { title: '#', dataIndex: 'row_number', width: 70 },
        ...(preview?.detected_columns || []).map((column) => ({
            title: column,
            dataIndex: ['raw_data', column],
            ellipsis: true,
        })),
        { title: 'Status', dataIndex: 'status', width: 120 },
        {
            title: 'Errors',
            dataIndex: 'errors',
            width: 260,
            render: (errors) => {
                if (!errors || !Object.keys(errors).length) {
                    return '-';
                }

                return Object.values(errors).flat().join(', ');
            },
        },
    ];

    return (
        <div className="page-stack">
            <Card>
                <Steps
                    current={currentStep}
                    items={[
                        { title: 'Upload' },
                        { title: 'Map & Validate', disabled: !preview },
                        { title: 'Ready', disabled: !importResult },
                    ]}
                />
            </Card>

            {currentStep === 0 && (
                <Card title="Upload">
                <div className="import-grid">
                    <Select
                        value={target}
                        onChange={(value) => {
                            setTarget(value);
                            clearFile();
                        }}
                        options={targets.map((item) => ({ value: item.target, label: item.target.replace('_', ' ') }))}
                    />
                    <div className="import-dropzone">
                        <Upload.Dragger
                            accept=".csv,.txt,.xlsx,.xls"
                            maxCount={1}
                            showUploadList={false}
                            beforeUpload={(nextFile) => {
                                if (!acceptedImportFile(nextFile)) {
                                    clearFile();
                                    notification.error({
                                        message: 'Unsupported import file',
                                        description: 'Use CSV, TXT, XLSX or XLS files only.',
                                    });

                                    return Upload.LIST_IGNORE;
                                }

                                setFile(nextFile);
                                resetPreviewState();
                                notification.success({ message: `${nextFile.name} selected` });
                                return false;
                            }}
                            onRemove={clearFile}
                        >
                            <p className="ant-upload-drag-icon"><InboxOutlined /></p>
                            <p className="ant-upload-text">Drop CSV/XLSX here</p>
                            <p className="ant-upload-hint">Accepted formats: .csv, .txt, .xlsx, .xls</p>
                        </Upload.Dragger>
                        {file?.name && (
                            <div className="selected-file-chip" title={file.name}>
                                <FileTextOutlined />
                                <span>{file.name}</span>
                                <Button size="small" type="text" icon={<CloseCircleOutlined />} onClick={clearFile} />
                            </div>
                        )}
                    </div>
                    <Space>
                        <Button loading={sampleLoading} disabled={sampleLoading || previewing} onClick={downloadSampleTemplate}>Sample Template</Button>
                        <Button type="primary" loading={previewing} disabled={!file || previewing} onClick={runPreview}>Preview File</Button>
                    </Space>
                </div>
                </Card>
            )}

            {currentStep === 1 && preview && (
                <Card title="Column Mapping">
                    <div className="mapping-grid">
                        {preview.detected_columns.map((column) => (
                            <div className="mapping-row" key={column}>
                                <span>{column}</span>
                                <Select
                                    allowClear
                                    value={mapping[column]}
                                    options={preview.system_fields.map((field) => ({
                                        value: field,
                                        label: selectedTarget?.required?.includes(field) ? `${field} *` : field,
                                    }))}
                                    onChange={(value) => setMapping((current) => ({ ...current, [column]: value }))}
                                />
                            </div>
                        ))}
                    </div>
                    <Space className="mt-16">
                        <Button disabled={importing} onClick={clearFile}>Back to Upload</Button>
                        <Button type="primary" loading={importing} disabled={importing} onClick={confirmMapping}>Confirm Import</Button>
                        <span>{preview.valid_rows} imported / {preview.invalid_rows} invalid / {preview.total_rows} total</span>
                    </Space>
                    <Alert
                        className="mt-16"
                        type="info"
                        showIcon
                        message="Preview rows are sampled"
                        description="The table below shows a sample preview. Confirm import processes the full stored file, not just these rows."
                    />
                    {preview.invalid_rows > 0 && (
                        <Alert
                            className="mt-16"
                            type="warning"
                            showIcon
                            message={`${preview.invalid_rows} rejected rows`}
                            description={<a href={`/api/v1/imports/${preview.id}/rejected.csv`} target="_blank" rel="noreferrer">Download rejected rows</a>}
                        />
                    )}
                </Card>
            )}

            {currentStep === 1 && preview && (
                <Card title="Preview Rows">
                    {(preview.rows || []).length ? (
                        <Table rowKey="row_number" columns={previewColumns} dataSource={preview.rows} pagination={{ pageSize: 10 }} scroll={{ x: 1000 }} />
                    ) : (
                        <Empty description="No preview rows found in the uploaded file" />
                    )}
                </Card>
            )}

            {currentStep === 2 && importResult && (
                <Card title="Ready">
                    <Space direction="vertical" size={16}>
                        <Alert
                            type={importResult.invalid_rows > 0 ? 'warning' : 'success'}
                            showIcon
                            message={importResult.status === 'completed' ? 'Import completed' : 'Import completed with review items'}
                            description={`${importResult.valid_rows} valid / ${importResult.invalid_rows} invalid / ${importResult.total_rows} total rows.`}
                        />
                        <Space>
                            <Button onClick={clearFile}>Import Another File</Button>
                            {importResult.invalid_rows > 0 && (
                                <Button href={`/api/v1/imports/${importResult.id}/rejected.csv`} target="_blank">Download Rejected Rows</Button>
                            )}
                        </Space>
                    </Space>
                </Card>
            )}
        </div>
    );
}

function guessField(column, fields) {
    return fields.includes(column) ? column : undefined;
}
