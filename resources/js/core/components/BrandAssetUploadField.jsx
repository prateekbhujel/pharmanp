import React from 'react';
import { PlusOutlined } from '@ant-design/icons';
import { Form, Upload } from 'antd';

function normalizeFile(event) {
    return Array.isArray(event) ? event : event?.fileList;
}

export function BrandAssetUploadField({ form, name, label, url = null, hint, accept = 'image/*' }) {
    const fileList = Form.useWatch(name, form) || [];
    const selectedFileName = fileList?.[0]?.name;

    return (
        <div className="branding-box">
            <div style={{ marginBottom: 8, fontSize: 13, fontWeight: 600 }}>{label}</div>
            <Form.Item name={name} valuePropName="fileList" getValueFromEvent={normalizeFile} noStyle>
                <Upload
                    beforeUpload={() => false}
                    maxCount={1}
                    accept={accept}
                    showUploadList={false}
                >
                    <div className="smart-image-upload-wrapper">
                        {url ? (
                            <>
                                <img src={url} alt={label} className="smart-image-preview" />
                                <div className="smart-image-overlay">
                                    <PlusOutlined />
                                    <span>Change Asset</span>
                                </div>
                            </>
                        ) : (
                            <div className="smart-image-placeholder">
                                <PlusOutlined />
                                <span>Upload</span>
                            </div>
                        )}
                    </div>
                </Upload>
            </Form.Item>
            {selectedFileName ? <div className="upload-file-name" title={selectedFileName}>{selectedFileName}</div> : null}
            {hint ? <div style={{ marginTop: 8, fontSize: 11, color: '#94a3b8' }}>{hint}</div> : null}
        </div>
    );
}
