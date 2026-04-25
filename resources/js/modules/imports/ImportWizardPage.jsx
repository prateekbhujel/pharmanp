import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Select, Space, Steps, Table, Upload } from 'antd';
import { InboxOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';

export function ImportWizardPage() {
    const { notification } = App.useApp();
    const [targets, setTargets] = useState([]);
    const [target, setTarget] = useState('products');
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
            notification.success({ message: data.data.status === 'validated' ? 'Import validated' : 'Mapping needs attention' });
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
            <PageHeader title="Import Wizard" description="Upload, preview, map fields, validate, then commit clean data in chunks" />

            <Card>
                <Steps
                    current={preview ? (preview.status === 'validated' ? 2 : 1) : 0}
                    items={[{ title: 'Upload' }, { title: 'Map & Validate' }, { title: 'Ready' }]}
                />
            </Card>

            <Card title="Upload">
                <div className="import-grid">
                    <Select value={target} onChange={setTarget} options={targets.map((item) => ({ value: item.target, label: item.target.replace('_', ' ') }))} />
                    <Upload.Dragger
                        maxCount={1}
                        beforeUpload={(nextFile) => {
                            setFile(nextFile);
                            return false;
                        }}
                        onRemove={() => setFile(null)}
                    >
                        <p className="ant-upload-drag-icon"><InboxOutlined /></p>
                        <p className="ant-upload-text">Drop CSV/XLSX here</p>
                    </Upload.Dragger>
                    <Button type="primary" loading={loading} onClick={runPreview}>Preview Columns</Button>
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
                        <Button type="primary" loading={loading} onClick={confirmMapping}>Validate Mapping</Button>
                        <span>{preview.valid_rows} valid / {preview.invalid_rows} invalid / {preview.total_rows} total</span>
                    </Space>
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
