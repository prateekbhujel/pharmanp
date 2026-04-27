import React, { useState } from 'react';
import { Alert, App, Button, Card, Descriptions, Input, Space, Table, Tag, Upload } from 'antd';
import { CloseCircleOutlined, FileTextOutlined, InboxOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { appUrl } from '../../core/utils/url';

const OCR_DRAFT_STORAGE_KEY = 'pharmanp-purchase-ocr-draft';

export function OcrImportPage() {
    const { notification } = App.useApp();
    const [file, setFile] = useState(null);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState(null);

    async function extract() {
        if (!file) {
            notification.warning({ message: 'Choose an invoice image first' });
            return;
        }

        const formData = new FormData();
        formData.append('image', file);
        setLoading(true);

        try {
            const { data } = await http.post(endpoints.purchaseOcrExtract, formData);
            setResult(data.data);
            notification.success({ message: data.message });
        } catch (error) {
            const payload = error?.response?.data?.data;
            setResult(payload || null);
            notification.error({ message: error?.response?.data?.message || 'OCR extraction failed' });
        } finally {
            setLoading(false);
        }
    }

    function loadIntoPurchase() {
        if (!result) return;

        const draft = {
            supplier_id: result.analysis?.supplier_id || null,
            supplier_name: result.analysis?.supplier_name || null,
            supplier_invoice_no: result.analysis?.invoice_no || '',
            purchase_date: result.analysis?.invoice_date || null,
            notes: result.text || '',
            matches: result.matches || [],
            analysis: result.analysis || {},
        };

        window.sessionStorage.setItem(OCR_DRAFT_STORAGE_KEY, JSON.stringify(draft));
        window.history.pushState({}, '', appUrl('/app/purchases/entry'));
        window.dispatchEvent(new PopStateEvent('popstate'));
    }

    return (
        <div className="page-stack">
            <PageHeader title="OCR Purchase Helper" />

            <Card title="Upload Bill">
                <div className="import-grid">
                    <div className="import-dropzone">
                        <Upload.Dragger
                            maxCount={1}
                            showUploadList={false}
                            beforeUpload={(nextFile) => {
                                setFile(nextFile);
                                return false;
                            }}
                            onRemove={() => setFile(null)}
                            accept=".jpg,.jpeg,.png,.pdf"
                        >
                            <p className="ant-upload-drag-icon"><InboxOutlined /></p>
                            <p className="ant-upload-text">Drop invoice image or PDF here</p>
                        </Upload.Dragger>
                        {file?.name && (
                            <div className="selected-file-chip" title={file.name}>
                                <FileTextOutlined />
                                <span>{file.name}</span>
                                <Button size="small" type="text" icon={<CloseCircleOutlined />} onClick={() => setFile(null)} />
                            </div>
                        )}
                    </div>
                    <Button type="primary" loading={loading} onClick={extract}>Extract OCR</Button>
                </div>
            </Card>

            {result && (
                <>
                    {result.extraction_status !== 'success' && (
                        <Alert type="warning" showIcon message={result.failure_message || 'OCR needs manual review'} />
                    )}

                    <Card
                        title="OCR Summary"
                        extra={
                            <Space>
                                <Tag color={result.extraction_status === 'success' ? 'success' : 'warning'}>
                                    {result.analysis?.next_action || 'manual_review'}
                                </Tag>
                                <Button type="primary" onClick={loadIntoPurchase}>Load Into Purchase Entry</Button>
                            </Space>
                        }
                    >
                        <Descriptions bordered size="small" column={2}>
                            <Descriptions.Item label="Supplier">{result.analysis?.supplier_name || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Invoice No">{result.analysis?.invoice_no || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Invoice Date">{result.analysis?.invoice_date || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Document Type">{result.analysis?.document_type || '-'}</Descriptions.Item>
                            <Descriptions.Item label="Total Amount">{result.analysis?.total_amount ?? '-'}</Descriptions.Item>
                            <Descriptions.Item label="Confidence">{result.analysis?.confidence ?? 0}%</Descriptions.Item>
                        </Descriptions>
                    </Card>

                    <Card title="Matching Purchase Bills">
                        <Table
                            rowKey="id"
                            pagination={false}
                            dataSource={result.matches || []}
                            columns={[
                                { title: 'Bill', dataIndex: 'purchase_no' },
                                { title: 'Invoice', dataIndex: 'invoice_no' },
                                { title: 'Supplier', dataIndex: 'supplier_name' },
                                { title: 'Date', dataIndex: 'purchase_date' },
                                { title: 'Total', dataIndex: 'grand_total' },
                                { title: 'Payment', dataIndex: 'payment_status' },
                            ]}
                            locale={{ emptyText: 'No close purchase bills found' }}
                        />
                    </Card>

                    <Card title="Extracted Text">
                        <Input.TextArea value={result.text || ''} rows={16} readOnly />
                    </Card>
                </>
            )}
        </div>
    );
}
