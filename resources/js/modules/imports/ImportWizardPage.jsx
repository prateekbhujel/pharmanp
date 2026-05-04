import React, { useEffect, useMemo, useState } from 'react';
import { Alert, App, Button, Card, Select, Space, Steps, Table, Upload } from 'antd';
import { CloseCircleOutlined, FileTextOutlined, InboxOutlined } from '@ant-design/icons';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { downloadAuthenticatedDocument } from '../../core/utils/documents';

export function ImportWizardPage() {
    const { notification } = App.useApp();
    const [targets, setTargets] = useState([]);
    const [target, setTarget] = useState(new URLSearchParams(window.location.search).get('target') || 'products');
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [mapping, setMapping] = useState({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        http.get(endpoints.importTargets).then(({ data }) => setTargets(data.data));
    }, []);

    const selectedTarget = useMemo(() => targets.find((item) => item.target === target), [targets, target]);

    async function runPreview() {
        if (!file) {
            notification.warning({ message: 'Choose a file first' });
            return;
        }

        const formData = new FormData();
        formData.append('target', target);
        formData.append('file', file);
        setLoading(true);

        try {
            const { data } = await http.post(endpoints.importPreview, formData);
            setPreview(data.data);
            setMapping(Object.fromEntries((data.data.detected_columns || []).map((column) => [column, guessField(column, data.data.system_fields)])));
        } finally {
            setLoading(false);
        }
    }

    async function confirmMapping() {
        setLoading(true);
        try {
            const { data } = await http.post(endpoints.importConfirm, {
                import_job_id: preview.id,
                mapping,
            });
            setPreview(data.data);
            notification.success({ message: data.data.status === 'completed' ? 'Import completed' : 'Import finished with review items' });
        } finally {
            setLoading(false);
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
    ];

    return (
        <div className="page-stack">
            <Card>
                <Steps
                    current={preview ? ((preview.status || '').startsWith('completed') ? 2 : 1) : 0}
                    items={[{ title: 'Upload' }, { title: 'Map & Validate' }, { title: 'Ready' }]}
                />
            </Card>

            <Card title="Upload">
                <div className="import-grid">
                    <Select value={target} onChange={setTarget} options={targets.map((item) => ({ value: item.target, label: item.target.replace('_', ' ') }))} />
                    <div className="import-dropzone">
                        <Upload.Dragger
                            maxCount={1}
                            showUploadList={false}
                            beforeUpload={(nextFile) => {
                                setFile(nextFile);
                                return false;
                            }}
                            onRemove={() => setFile(null)}
                        >
                            <p className="ant-upload-drag-icon"><InboxOutlined /></p>
                            <p className="ant-upload-text">Drop CSV/XLSX here</p>
                        </Upload.Dragger>
                        {file?.name && (
                            <div className="selected-file-chip" title={file.name}>
                                <FileTextOutlined />
                                <span>{file.name}</span>
                                <Button size="small" type="text" icon={<CloseCircleOutlined />} onClick={() => setFile(null)} />
                            </div>
                        )}
                    </div>
                    <Space>
                        <Button onClick={() => downloadAuthenticatedDocument(endpoints.importSample(target), `${target}-sample.xlsx`)}>Sample Template</Button>
                        <Button type="primary" loading={loading} onClick={runPreview}>Preview File</Button>
                    </Space>
                </div>
            </Card>

            {preview && (
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
                        <Button type="primary" loading={loading} onClick={confirmMapping}>Confirm Import</Button>
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

            {preview && (
                <Card title="Preview Rows">
                    <Table rowKey="row_number" columns={previewColumns} dataSource={preview.rows} pagination={{ pageSize: 10 }} scroll={{ x: 1000 }} />
                </Card>
            )}
        </div>
    );
}

function guessField(column, fields) {
    return fields.includes(column) ? column : undefined;
}
