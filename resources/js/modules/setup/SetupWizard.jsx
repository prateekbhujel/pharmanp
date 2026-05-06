import React, { useEffect, useMemo, useState } from 'react';
import { Alert, App, Button, Card, Checkbox, Empty, Form, Input, Segmented, Select, Space, Steps } from 'antd';
import { MinusCircleOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { BrandAssetUploadField } from '../../core/components/BrandAssetUploadField';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { applyRequestFormErrors, showRequestError, showRequestSuccess } from '../../core/api/feedback';
import { countries, countryOptions } from '../../core/utils/countries';
import { appUrl } from '../../core/utils/url';

function appendFormValue(formData, key, value) {
    if (value === undefined || value === null) {
        return;
    }

    if (Array.isArray(value) && value[0]?.originFileObj) {
        const file = value[0]?.originFileObj;

        if (file) {
            const uploadKey = key.endsWith('_upload')
                ? key.replace(/_upload$/, '_file')
                : key.replace(/_upload\]$/, '_file]');

            formData.append(uploadKey, file);
        }

        return;
    }

    if (Array.isArray(value)) {
        value.forEach((entry, index) => appendFormValue(formData, `${key}[${index}]`, entry));

        return;
    }

    if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0');
        return;
    }

    if (typeof value === 'object' && !(value instanceof Blob)) {
        Object.entries(value).forEach(([childKey, childValue]) => {
            appendFormValue(formData, key ? `${key}[${childKey}]` : childKey, childValue);
        });

        return;
    }

    formData.append(key, value);
}

function buildSetupPayload(values) {
    const formData = new FormData();

    appendFormValue(formData, '', values);

    return formData;
}

const setupSteps = [
    {
        key: 'company',
        title: 'Company',
        description: 'Tenant, company, store and HQ branch basics.',
        fields: [
            ['company', 'name'],
            ['company', 'pan_number'],
            ['company', 'address'],
            ['store', 'name'],
            ['store', 'phone'],
            ['branch', 'name'],
            ['branch', 'code'],
        ],
    },
    {
        key: 'areas',
        title: 'Areas',
        description: 'Initial branch coverage areas.',
        optional: true,
        fields: [['areas']],
    },
    {
        key: 'fiscal',
        title: 'Fiscal Year',
        description: 'Opening accounting period.',
        fields: [
            ['fiscal_year', 'name'],
            ['fiscal_year', 'starts_on'],
            ['fiscal_year', 'ends_on'],
        ],
    },
    {
        key: 'divisions',
        title: 'Divisions',
        description: 'Initial product or field-force divisions.',
        optional: true,
        fields: [['divisions']],
    },
    {
        key: 'payment',
        title: 'Payments',
        description: 'Starting payment modes.',
        optional: true,
        fields: [['payment_modes']],
    },
    {
        key: 'employees',
        title: 'Employees',
        description: 'Initial staff or MR records.',
        optional: true,
        fields: [['employees']],
    },
    {
        key: 'branding',
        title: 'Branding',
        description: 'App name, country, calendar and assets.',
        fields: [
            ['branding', 'app_name'],
            ['branding', 'country_code'],
            ['branding', 'currency_symbol'],
            ['branding', 'calendar_type'],
        ],
    },
    {
        key: 'admin',
        title: 'Admin',
        description: 'Owner login account and final review.',
        fields: [
            ['admin', 'name'],
            ['admin', 'email'],
            ['admin', 'password'],
            ['admin', 'password_confirmation'],
        ],
    },
];

export function SetupWizard() {
    const { notification } = App.useApp();
    const [status, setStatus] = useState(null);
    const [saving, setSaving] = useState(false);
    const [stepSaving, setStepSaving] = useState(false);
    const [activeStep, setActiveStep] = useState(0);
    const [completedSteps, setCompletedSteps] = useState([]);
    const [form] = Form.useForm();

    useEffect(() => {
        http.get(endpoints.setupStatus)
            .then(({ data }) => setStatus(data.data))
            .catch((error) => showRequestError(notification, error, 'Setup status failed'));
    }, []);

    const databaseReady = Boolean(status?.database?.ok);
    const environmentReady = Boolean(status?.environment?.app_key && status?.environment?.storage_writable && status?.environment?.cache_writable);
    const setupReady = databaseReady && environmentReady;

    const currentStep = setupSteps[activeStep];
    const completionSet = useMemo(() => new Set(completedSteps), [completedSteps]);

    function markCompleted(index) {
        setCompletedSteps((current) => [...new Set([...current, setupSteps[index].key])]);
    }

    async function saveCurrentStep() {
        setStepSaving(true);
        try {
            await form.validateFields(currentStep.fields);
            markCompleted(activeStep);
            notification.success({ message: `${currentStep.title} ready` });
            setActiveStep((step) => Math.min(step + 1, setupSteps.length - 1));
        } catch (error) {
            if (error?.errorFields) {
                notification.error({
                    message: `${currentStep.title} needs attention`,
                    description: error.errorFields[0]?.errors?.[0] || 'Check the highlighted fields before continuing.',
                });
            } else {
                showRequestError(notification, error, `${currentStep.title} failed`);
            }
        } finally {
            setStepSaving(false);
        }
    }

    function skipCurrentStep() {
        markCompleted(activeStep);
        setActiveStep((step) => Math.min(step + 1, setupSteps.length - 1));
    }

    async function submit(values) {
        setSaving(true);
        try {
            const response = await http.post(endpoints.setupComplete, buildSetupPayload({
                ...values,
                fiscal_year: {
                    ...values.fiscal_year,
                    starts_on: values.fiscal_year.starts_on.format('YYYY-MM-DD'),
                    ends_on: values.fiscal_year.ends_on.format('YYYY-MM-DD'),
                },
            }));
            showRequestSuccess(notification, response, 'Setup completed');
            window.location.href = appUrl('/app');
        } catch (error) {
            applyRequestFormErrors(form, error);
            showRequestError(notification, error, 'Setup failed');
        } finally {
            setSaving(false);
        }
    }

    function renderCompanyStep() {
        return (
            <>
                <div className="form-grid">
                    <Form.Item name={['company', 'name']} label="Company Name" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name={['company', 'pan_number']} label="PAN">
                        <Input />
                    </Form.Item>
                </div>
                <Form.Item name={['company', 'address']} label="Company Address">
                    <Input />
                </Form.Item>
                <div className="form-grid">
                    <Form.Item name={['store', 'name']} label="Store Name">
                        <Input />
                    </Form.Item>
                    <Form.Item name={['store', 'phone']} label="Store Phone">
                        <Input />
                    </Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name={['branch', 'name']} label="HQ Branch Name">
                        <Input />
                    </Form.Item>
                    <Form.Item name={['branch', 'code']} label="HQ Branch Code">
                        <Input />
                    </Form.Item>
                </div>
            </>
        );
    }

    function renderAreasStep() {
        return (
            <Form.List name="areas">
                {(fields, { add, remove }) => (
                    <div className="page-stack">
                        {fields.length === 0 && <Empty description="No opening areas added. You can skip this and add areas later." />}
                        {fields.map((field) => (
                            <Card key={field.key} size="small" className="settings-inner-card">
                                <div className="form-grid form-grid-4">
                                    <Form.Item {...field} name={[field.name, 'name']} label="Area Name" rules={[{ required: true }]}>
                                        <Input />
                                    </Form.Item>
                                    <Form.Item {...field} name={[field.name, 'code']} label="Code">
                                        <Input />
                                    </Form.Item>
                                    <Form.Item {...field} name={[field.name, 'district']} label="District">
                                        <Input />
                                    </Form.Item>
                                    <Form.Item {...field} name={[field.name, 'province']} label="Province">
                                        <Input />
                                    </Form.Item>
                                </div>
                                <Button danger type="link" icon={<MinusCircleOutlined />} onClick={() => remove(field.name)}>Remove Area</Button>
                            </Card>
                        ))}
                        <Button icon={<PlusOutlined />} onClick={() => add({ name: '', code: '' })}>Add Area</Button>
                    </div>
                )}
            </Form.List>
        );
    }

    function renderFiscalStep() {
        return (
            <>
                <div className="form-grid">
                    <Form.Item name={['fiscal_year', 'name']} label="Fiscal Year" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name={['fiscal_year', 'starts_on']} label="Starts On" rules={[{ required: true }]}>
                        <SmartDatePicker className="full-width" />
                    </Form.Item>
                </div>
                <Form.Item name={['fiscal_year', 'ends_on']} label="Ends On" rules={[{ required: true }]}>
                    <SmartDatePicker className="full-width" />
                </Form.Item>
            </>
        );
    }

    function renderDivisionsStep() {
        return (
            <Form.List name="divisions">
                {(fields, { add, remove }) => (
                    <div className="page-stack">
                        {fields.length === 0 && <Empty description="No opening divisions added. You can skip this and add divisions later." />}
                        {fields.map((field) => (
                            <Card key={field.key} size="small" className="settings-inner-card">
                                <div className="form-grid">
                                    <Form.Item {...field} name={[field.name, 'name']} label="Division Name" rules={[{ required: true }]}>
                                        <Input />
                                    </Form.Item>
                                    <Form.Item {...field} name={[field.name, 'code']} label="Code">
                                        <Input />
                                    </Form.Item>
                                </div>
                                <Button danger type="link" icon={<MinusCircleOutlined />} onClick={() => remove(field.name)}>Remove Division</Button>
                            </Card>
                        ))}
                        <Button icon={<PlusOutlined />} onClick={() => add({ name: '', code: '' })}>Add Division</Button>
                    </div>
                )}
            </Form.List>
        );
    }

    function renderPaymentStep() {
        return (
            <Form.List name="payment_modes">
                {(fields, { add, remove }) => (
                    <div className="page-stack">
                        {fields.map((field) => (
                            <Card key={field.key} size="small" className="settings-inner-card">
                                <div className="form-grid">
                                    <Form.Item {...field} name={[field.name, 'name']} label="Payment Mode" rules={[{ required: true }]}>
                                        <Input />
                                    </Form.Item>
                                    <Form.Item {...field} name={[field.name, 'data']} label="Code / QR Reference">
                                        <Input />
                                    </Form.Item>
                                </div>
                                <Button danger type="link" icon={<MinusCircleOutlined />} onClick={() => remove(field.name)}>Remove Mode</Button>
                            </Card>
                        ))}
                        <Button icon={<PlusOutlined />} onClick={() => add({ name: '', data: '' })}>Add Payment Mode</Button>
                    </div>
                )}
            </Form.List>
        );
    }

    function renderEmployeesStep() {
        return (
            <Form.List name="employees">
                {(fields, { add, remove }) => (
                    <div className="page-stack">
                        {fields.length === 0 && <Empty description="No opening employees added. You can skip this and add staff later." />}
                        {fields.map((field) => (
                            <Card key={field.key} size="small" className="settings-inner-card">
                                <div className="form-grid form-grid-4">
                                    <Form.Item {...field} name={[field.name, 'name']} label="Name" rules={[{ required: true }]}>
                                        <Input />
                                    </Form.Item>
                                    <Form.Item {...field} name={[field.name, 'designation']} label="Designation">
                                        <Input />
                                    </Form.Item>
                                    <Form.Item {...field} name={[field.name, 'phone']} label="Phone">
                                        <Input />
                                    </Form.Item>
                                    <Form.Item {...field} name={[field.name, 'email']} label="Email">
                                        <Input />
                                    </Form.Item>
                                </div>
                                <Button danger type="link" icon={<MinusCircleOutlined />} onClick={() => remove(field.name)}>Remove Employee</Button>
                            </Card>
                        ))}
                        <Button icon={<PlusOutlined />} onClick={() => add({ name: '', designation: 'MR' })}>Add Employee</Button>
                    </div>
                )}
            </Form.List>
        );
    }

    function renderBrandingStep() {
        return (
            <>
                <div className="form-grid">
                    <Form.Item name={['branding', 'app_name']} label="App Name" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name={['branding', 'country_code']} label="Country" rules={[{ required: true }]}>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            options={countryOptions}
                            onChange={(code) => {
                                const country = countries.find((item) => item.code === code);

                                if (!country) {
                                    return;
                                }

                                form.setFieldValue(['branding', 'currency_symbol'], country.symbol);
                                form.setFieldValue(['branding', 'calendar_type'], code === 'NP' ? 'bs' : 'ad');
                            }}
                        />
                    </Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name={['branding', 'currency_symbol']} label="Currency Symbol" rules={[{ required: true }]}>
                        <Input placeholder="Rs." />
                    </Form.Item>
                    <Form.Item name={['branding', 'calendar_type']} label="Calendar System" rules={[{ required: true }]}>
                        <Segmented
                            block
                            options={[
                                { label: 'Gregorian', value: 'ad' },
                                { label: 'Nepali', value: 'bs' },
                            ]}
                        />
                    </Form.Item>
                </div>
                <div className="branding-upload-grid">
                    {[
                        ['logo_upload', 'Main Logo', 'Best for light headers'],
                        ['sidebar_logo_upload', 'Sidebar Logo', 'Compact sidebar icon'],
                        ['app_icon_upload', 'App Icon', 'Desktop and mobile icon'],
                        ['favicon_upload', 'Favicon', 'Browser tab icon'],
                    ].map(([fieldKey, label, hint]) => (
                        <BrandAssetUploadField
                            key={fieldKey}
                            form={form}
                            name={['branding', fieldKey]}
                            label={label}
                            hint={hint}
                            accept={fieldKey === 'favicon_upload' ? '.ico,image/*' : 'image/*'}
                        />
                    ))}
                </div>
                <div className="form-grid">
                    <Form.Item name={['branding', 'sidebar_default_collapsed']} valuePropName="checked">
                        <Checkbox>Start sidebar minimized</Checkbox>
                    </Form.Item>
                    <Form.Item name={['branding', 'show_breadcrumbs']} valuePropName="checked">
                        <Checkbox>Show page breadcrumbs</Checkbox>
                    </Form.Item>
                </div>
            </>
        );
    }

    function renderAdminStep() {
        return (
            <>
                <div className="form-grid">
                    <Form.Item name={['admin', 'name']} label="Admin Name" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name={['admin', 'email']} label="Admin Email" rules={[{ required: true, type: 'email' }]}>
                        <Input />
                    </Form.Item>
                </div>
                <div className="form-grid">
                    <Form.Item name={['admin', 'password']} label="Password" rules={[{ required: true }, { min: 8 }]}>
                        <Input.Password />
                    </Form.Item>
                    <Form.Item
                        name={['admin', 'password_confirmation']}
                        label="Confirm Password"
                        dependencies={[['admin', 'password']]}
                        rules={[
                            { required: true },
                            ({ getFieldValue }) => ({
                                validator(_, value) {
                                    if (value === getFieldValue(['admin', 'password'])) {
                                        return Promise.resolve();
                                    }

                                    return Promise.reject(new Error('Password confirmation must match.'));
                                },
                            }),
                        ]}
                    >
                        <Input.Password />
                    </Form.Item>
                </div>
                <Form.Item name="seed_demo" valuePropName="checked">
                    <Checkbox disabled={status?.environment?.app_env === 'production'}>Seed local demo data</Checkbox>
                </Form.Item>
                <Alert
                    type="info"
                    showIcon
                    message="Review before completion"
                    description="Setup will create the tenant, company, store, HQ branch, current fiscal year, owner account and any optional opening structure you entered."
                />
            </>
        );
    }

    function renderActiveStep() {
        if (currentStep.key === 'company') return renderCompanyStep();
        if (currentStep.key === 'areas') return renderAreasStep();
        if (currentStep.key === 'fiscal') return renderFiscalStep();
        if (currentStep.key === 'divisions') return renderDivisionsStep();
        if (currentStep.key === 'payment') return renderPaymentStep();
        if (currentStep.key === 'employees') return renderEmployeesStep();
        if (currentStep.key === 'branding') return renderBrandingStep();

        return renderAdminStep();
    }

    return (
        <main className="setup-page">
            <section className="setup-hero">
                <strong>PharmaNP</strong>
                <span>First install wizard for pharmacy and distributor operations.</span>
                <Steps
                    current={activeStep}
                    items={setupSteps.map((step) => ({
                        title: step.title,
                        description: completionSet.has(step.key) ? 'Ready' : step.description,
                    }))}
                />
            </section>

            <Card className="setup-card glass-card" title={currentStep.title}>
                {!databaseReady && (
                    <Alert
                        className="mb-16"
                        type="warning"
                        showIcon
                        message="Database is not ready"
                        description={status?.database?.message || 'Create the database, update .env, then run php artisan migrate before completing setup.'}
                    />
                )}
                {!environmentReady && (
                    <Alert
                        className="mb-16"
                        type="info"
                        showIcon
                        message="Environment checks"
                        description="APP_KEY, storage and cache permissions must be ready before production use."
                    />
                )}
                <Alert className="mb-16" type="info" showIcon message={currentStep.description} />

                <Form
                    form={form}
                    layout="vertical"
                    onFinish={submit}
                    initialValues={{
                        company: { name: 'PharmaNP Pharmacy' },
                        store: { name: 'Main Store' },
                        branch: { name: 'Head Office', code: 'HQ' },
                        areas: [{ name: 'Kathmandu', code: 'KTM', district: 'Kathmandu', province: 'Bagmati' }],
                        divisions: [{ name: 'General', code: 'GEN' }],
                        payment_modes: [
                            { name: 'Cash', data: 'cash' },
                            { name: 'Bank', data: 'bank' },
                            { name: 'QR', data: 'qr' },
                        ],
                        employees: [],
                        branding: {
                            app_name: 'PharmaNP',
                            accent_color: '#0f766e',
                            sidebar_default_collapsed: true,
                            show_breadcrumbs: true,
                            country_code: 'NP',
                            currency_symbol: 'Rs.',
                            calendar_type: 'bs',
                        },
                        fiscal_year: {
                            name: `${dayjs().year()}/${String(dayjs().add(1, 'year').year()).slice(-2)}`,
                            starts_on: dayjs().startOf('year'),
                            ends_on: dayjs().endOf('year'),
                        },
                        admin: {
                            name: 'Pratik Admin',
                            email: 'pratik@admin.com',
                            password: '',
                            password_confirmation: '',
                        },
                    }}
                >
                    {renderActiveStep()}
                    <Space className="mt-16">
                        <Button disabled={activeStep === 0 || saving || stepSaving} onClick={() => setActiveStep((step) => Math.max(step - 1, 0))}>Back</Button>
                        {currentStep.optional && activeStep < setupSteps.length - 1 && (
                            <Button disabled={saving || stepSaving} onClick={skipCurrentStep}>Skip</Button>
                        )}
                        {activeStep < setupSteps.length - 1 ? (
                            <Button type="primary" loading={stepSaving} disabled={saving || stepSaving} onClick={saveCurrentStep}>Save & Next</Button>
                        ) : (
                            <Button type="primary" htmlType="submit" loading={saving} disabled={!setupReady || saving || stepSaving}>Complete Setup</Button>
                        )}
                    </Space>
                </Form>
            </Card>
        </main>
    );
}
