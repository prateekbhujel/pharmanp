import React, { useEffect } from 'react';
import { App, Button, Card, Form, Input, Segmented, Select, Space, Switch } from 'antd';
import { BrandAssetUploadField } from '../../core/components/BrandAssetUploadField';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { countryOptions, countries } from '../../core/utils/countries';

function brandingPayload(values) {
    const payload = new FormData();

    Object.entries(values).forEach(([key, value]) => {
        if (key.endsWith('_upload')) {
            const file = value?.[0]?.originFileObj;
            if (file) {
                payload.append(key.replace('_upload', '_file'), file);
            }

            return;
        }

        if (typeof value === 'boolean') {
            payload.append(key, value ? '1' : '0');
            return;
        }

        if (value !== undefined && value !== null) {
            payload.append(key, value);
        }
    });

    payload.append('_method', 'PUT');

    return payload;
}

function fieldErrors(error) {
    return Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages }));
}

export function SettingsBrandingPanel({ branding, brandingLoading, reloadBranding, reloadAuth }) {
    const { notification } = App.useApp();
    const [brandingForm] = Form.useForm();

    useEffect(() => {
        if (branding) {
            brandingForm.setFieldsValue({
                show_breadcrumbs: true,
                ...branding,
            });
        }
    }, [branding, brandingForm]);

    async function saveBranding(values) {
        try {
            await http.post(endpoints.branding, brandingPayload(values));
            notification.success({ message: 'Application branding saved' });
            reloadBranding?.();
            reloadAuth?.();
        } catch (error) {
            brandingForm.setFields(fieldErrors(error));
            notification.error({ message: 'Branding save failed', description: error?.response?.data?.message || error.message });
        }
    }

    return (
        <Card title="Application Identity" loading={brandingLoading} className="settings-inner-card">
            <Form form={brandingForm} layout="vertical" onFinish={saveBranding}>
                <div className="form-grid">
                    <Form.Item name="app_name" label="Pharmacy Name" rules={[{ required: true }]}>
                        <Input size="large" />
                    </Form.Item>
                    <Form.Item name="country_code" label="Country" rules={[{ required: true }]}>
                        <Select
                            size="large"
                            showSearch
                            optionFilterProp="label"
                            options={countryOptions}
                            onChange={(code) => {
                                const country = countries.find((item) => item.code === code);
                                if (!country) return;
                                brandingForm.setFieldValue('currency_symbol', country.symbol);
                                brandingForm.setFieldValue('calendar_type', code === 'NP' ? 'bs' : 'ad');
                            }}
                        />
                    </Form.Item>
                    <Form.Item name="currency_symbol" label="Currency Symbol" rules={[{ required: true }]}>
                        <Input size="large" placeholder="Rs." />
                    </Form.Item>
                    <Form.Item name="calendar_type" label="Preferred Calendar System" rules={[{ required: true }]}>
                        <Segmented
                            block
                            size="large"
                            options={[
                                { label: 'Gregorian', value: 'ad' },
                                { label: 'Nepali', value: 'bs' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item name="show_breadcrumbs" label="Show Breadcrumbs" valuePropName="checked">
                        <Switch checkedChildren="Show" unCheckedChildren="Hide" />
                    </Form.Item>
                </div>
                <div className="branding-upload-grid">
                    {[
                        ['logo_upload', 'Main Logo', branding?.logo_url, 'Best for light headers'],
                        ['sidebar_logo_upload', 'Sidebar Logo', branding?.sidebar_logo_url, 'Compact sidebar icon'],
                        ['app_icon_upload', 'App Icon', branding?.app_icon_url, 'Desktop and mobile icon'],
                        ['favicon_upload', 'Favicon', branding?.favicon_url, 'Browser tab icon'],
                    ].map(([name, label, url, hint]) => (
                        <BrandAssetUploadField
                            key={name}
                            form={brandingForm}
                            name={name}
                            label={label}
                            url={url}
                            hint={hint}
                            accept={name === 'favicon_upload' ? '.ico,image/*' : 'image/*'}
                        />
                    ))}
                </div>
                <Space wrap>
                    <Button type="primary" size="large" htmlType="submit">Apply Branding</Button>
                    <Button size="large" onClick={() => reloadBranding?.()}>Discard Changes</Button>
                </Space>
            </Form>
        </Card>
    );
}
