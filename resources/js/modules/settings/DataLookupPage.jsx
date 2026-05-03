import React, { useMemo, useState } from 'react';
import { App, AutoComplete, Button, Card, Form, Input, Modal, Select, Space, Switch, Table, Tabs } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined, UndoOutlined } from '@ant-design/icons';
import { StatusToggle } from '../../core/components/StatusToggle';
import { StatusTag } from '../../core/components/StatusTag';
import { ServerTable } from '../../core/components/ServerTable';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { useServerTable } from '../../core/hooks/useServerTable';
import { useBranding } from '../../core/context/BrandingContext';
import { placeOptionsForCountry } from '../../core/utils/placeSuggestions';
import { dropdownAliasOptions, dropdownDataField, fallbackDropdownAliases } from '../../core/utils/dropdownOptions';
import { appUrl } from '../../core/utils/url';
import { PaymentModePanel } from './PaymentModePanel';

const branchTypeOptions = [
    { value: 'hq', label: 'Head Office' },
    { value: 'branch', label: 'Branch' },
];

const tabRoutes = {
    'payment-modes': appUrl('/app/administration/payment-modes'),
    branches: appUrl('/app/administration/branches'),
    dropdowns: appUrl('/app/administration/data-lookup'),
    'party-types': appUrl('/app/administration/party-types'),
    'supplier-types': appUrl('/app/administration/supplier-types'),
};

function tabFromPath() {
    const path = window.location.pathname;
    return Object.entries(tabRoutes).find(([, route]) => route === path)?.[0] || 'payment-modes';
}

export function DataLookupPage() {
    const { data: dropdownResponse, reload: reloadDropdowns } = useApi(endpoints.dropdownOptions);
    const { data: partyTypes, reload: reloadPartyTypes } = useApi(endpoints.partyTypes);
    const { data: supplierTypes, reload: reloadSupplierTypes } = useApi(endpoints.supplierTypes);

    const dropdownOptions = Array.isArray(dropdownResponse) ? dropdownResponse : (dropdownResponse?.data || []);
    const dropdownAliases = dropdownResponse?.aliases || fallbackDropdownAliases;
    const aliasOptions = useMemo(() => dropdownAliasOptions(dropdownAliases), [dropdownAliases]);
    const [dropdownAlias, setDropdownAlias] = useState(aliasOptions[0]?.value || 'product_status');
    const [dropdownModalOpen, setDropdownModalOpen] = useState(false);
    const [editingOption, setEditingOption] = useState(null);
    const [dropdownForm] = Form.useForm();

    const [partyTypeModalOpen, setPartyTypeModalOpen] = useState(false);
    const [editingPartyType, setEditingPartyType] = useState(null);
    const [partyTypeForm] = Form.useForm();

    const [supplierTypeModalOpen, setSupplierTypeModalOpen] = useState(false);
    const [editingSupplierType, setEditingSupplierType] = useState(null);
    const [supplierTypeForm] = Form.useForm();
    const [activeTab, setActiveTab] = useState(tabFromPath);

    const filteredOptions = useMemo(() =>
        (dropdownOptions || []).filter(o => o.alias === dropdownAlias),
        [dropdownOptions, dropdownAlias]);

    const selectedAliasMeta = dropdownAliases[dropdownAlias] || {};
    const dataField = dropdownDataField(dropdownAlias);

    // --- Handlers ---
    const openDropdownOption = (record = null) => {
        setEditingOption(record);
        dropdownForm.resetFields();
        if (record) {
            dropdownForm.setFieldsValue({
                ...record,
                status: Boolean(record.status),
            });
        } else {
            dropdownForm.setFieldsValue({ alias: dropdownAlias, status: true });
        }
        setDropdownModalOpen(true);
    };

    const saveDropdownOption = async (values) => {
        if (editingOption) await http.put(endpoints.dropdownOptions + '/' + editingOption.id, values);
        else await http.post(endpoints.dropdownOptions, values);
        setDropdownModalOpen(false);
        reloadDropdowns();
    };

    const deleteDropdownOption = async (record) => {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'Options already used in transactions are protected by the backend.',
            onOk: async () => {
                await http.delete(endpoints.dropdownOptions + '/' + record.id);
                reloadDropdowns();
            },
        });
    };

    const openPartyType = (record = null) => {
        setEditingPartyType(record);
        partyTypeForm.resetFields();
        if (record) partyTypeForm.setFieldsValue(record);
        setPartyTypeModalOpen(true);
    };

    const savePartyType = async (values) => {
        if (editingPartyType) await http.put(endpoints.partyTypes + '/' + editingPartyType.id, values);
        else await http.post(endpoints.partyTypes, values);
        setPartyTypeModalOpen(false);
        reloadPartyTypes();
    };

    const deletePartyType = async (record) => {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'This removes the party type from future selection lists.',
            onOk: async () => {
                await http.delete(endpoints.partyTypes + '/' + record.id);
                reloadPartyTypes();
            },
        });
    };

    const openSupplierType = (record = null) => {
        setEditingSupplierType(record);
        supplierTypeForm.resetFields();
        if (record) supplierTypeForm.setFieldsValue(record);
        setSupplierTypeModalOpen(true);
    };

    const saveSupplierType = async (values) => {
        if (editingSupplierType) await http.put(endpoints.supplierTypes + '/' + editingSupplierType.id, values);
        else await http.post(endpoints.supplierTypes, values);
        setSupplierTypeModalOpen(false);
        reloadSupplierTypes();
    };

    const deleteSupplierType = async (record) => {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: 'This removes the supplier type from future selection lists.',
            onOk: async () => {
                await http.delete(endpoints.supplierTypes + '/' + record.id);
                reloadSupplierTypes();
            },
        });
    };

    return (
        <div className="page-stack">
            <Tabs
                activeKey={activeTab}
                onChange={(key) => {
                    setActiveTab(key);
                    window.history.pushState({}, '', tabRoutes[key] || tabRoutes.dropdowns);
                    window.dispatchEvent(new PopStateEvent('popstate'));
                }}
                items={[
                {
                    key: 'payment-modes',
                    label: 'Payment Modes',
                    children: <PaymentModePanel />,
                },
                {
                    key: 'branches',
                    label: 'Branches',
                    children: <BranchMasterPanel />,
                },
                {
                    key: 'dropdowns',
                    label: 'General Options',
                    children: (
                        <Card
                            title="Shared Dropdown Options"
                            extra={
                                <Space>
                                    <Select
                                        value={dropdownAlias}
                                        onChange={setDropdownAlias}
                                        style={{ width: 200 }}
                                        options={aliasOptions}
                                    />
                                    <Button type="primary" icon={<PlusOutlined />} onClick={() => openDropdownOption()}>Add Option</Button>
                                </Space>
                            }
                        >
                            <Table
                                rowKey="id"
                                dataSource={filteredOptions}
                                pagination={false}
                                columns={[
                                    { title: 'Name', dataIndex: 'name' },
                                    {
                                        title: selectedAliasMeta.supports_data ? (dataField?.label || 'Data') : 'Data',
                                        dataIndex: 'data',
                                        render: (v) => v || '-',
                                    },
                                    { title: 'Status', dataIndex: 'is_active', width: 150, render: (v, record) => <StatusToggle value={v} id={record.id} endpoint={endpoints.dropdownOptions} /> },
                                    {
                                        title: 'Action', width: 112, render: (_, record) => (
                                            <Space>
                                                <Button icon={<EditOutlined />} onClick={() => openDropdownOption(record)} />
                                                <Button danger icon={<DeleteOutlined />} onClick={() => deleteDropdownOption(record)} />
                                            </Space>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    )
                },
                {
                    key: 'party-types',
                    label: 'Party Types',
                    children: (
                        <Card title="Party Types" extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openPartyType()}>New Party Type</Button>}>
                            <Table
                                rowKey="id"
                                dataSource={partyTypes}
                                pagination={false}
                                columns={[
                                    { title: 'Name', dataIndex: 'name' },
                                    { title: 'Code', dataIndex: 'code', render: (v) => v || '-' },
                                    {
                                        title: 'Action', width: 112, render: (_, record) => (
                                            <Space>
                                                <Button icon={<EditOutlined />} onClick={() => openPartyType(record)} />
                                                <Button danger icon={<DeleteOutlined />} onClick={() => deletePartyType(record)} />
                                            </Space>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    )
                },
                {
                    key: 'supplier-types',
                    label: 'Supplier Types',
                    children: (
                        <Card title="Supplier Types" extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openSupplierType()}>New Supplier Type</Button>}>
                            <Table
                                rowKey="id"
                                dataSource={supplierTypes}
                                pagination={false}
                                columns={[
                                    { title: 'Name', dataIndex: 'name' },
                                    { title: 'Code', dataIndex: 'code', render: (v) => v || '-' },
                                    {
                                        title: 'Action', width: 112, render: (_, record) => (
                                            <Space>
                                                <Button icon={<EditOutlined />} onClick={() => openSupplierType(record)} />
                                                <Button danger icon={<DeleteOutlined />} onClick={() => deleteSupplierType(record)} />
                                            </Space>
                                        ),
                                    },
                                ]}
                            />
                        </Card>
                    )
                }
            ]} />

            {/* Modals */}
            <Modal centered className="intent-modal" title={editingOption ? 'Edit Option' : 'New Option'} open={dropdownModalOpen} onCancel={() => setDropdownModalOpen(false)} onOk={() => dropdownForm.submit()} destroyOnHidden>
                <Form form={dropdownForm} layout="vertical" onFinish={saveDropdownOption}>
                    <Form.Item name="alias" label="Alias" rules={[{ required: true }]}><Select options={aliasOptions} /></Form.Item>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    {selectedAliasMeta.supports_data && dataField?.options && (
                        <Form.Item name="data" label={dataField.label}>
                            <Select allowClear options={dataField.options} placeholder={dataField.placeholder} />
                        </Form.Item>
                    )}
                    {selectedAliasMeta.supports_data && !dataField?.options && (
                        <Form.Item name="data" label={dataField?.label || 'Data (optional)'}><Input placeholder={dataField?.placeholder || 'Optional'} /></Form.Item>
                    )}
                    <Form.Item name="status" valuePropName="checked" label="Active"><Switch /></Form.Item>
                </Form>
            </Modal>

            <Modal centered className="intent-modal" title={editingPartyType ? 'Edit Party Type' : 'New Party Type'} open={partyTypeModalOpen} onCancel={() => setPartyTypeModalOpen(false)} onOk={() => partyTypeForm.submit()} destroyOnHidden>
                <Form form={partyTypeForm} layout="vertical" onFinish={savePartyType}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="code" label="Code"><Input /></Form.Item>
                </Form>
            </Modal>

            <Modal centered className="intent-modal" title={editingSupplierType ? 'Edit Supplier Type' : 'New Supplier Type'} open={supplierTypeModalOpen} onCancel={() => setSupplierTypeModalOpen(false)} onOk={() => supplierTypeForm.submit()} destroyOnHidden>
                <Form form={supplierTypeForm} layout="vertical" onFinish={saveSupplierType}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}><Input /></Form.Item>
                    <Form.Item name="code" label="Code"><Input /></Form.Item>
                </Form>
            </Modal>
        </div>
    );
}

function BranchMasterPanel() {
    const { notification } = App.useApp();
    const { branding } = useBranding();
    const branchTable = useServerTable({ endpoint: endpoints.mrBranches, defaultSort: { field: 'name', order: 'asc' } });
    const [branchModalOpen, setBranchModalOpen] = useState(false);
    const [editingBranch, setEditingBranch] = useState(null);
    const [saving, setSaving] = useState(false);
    const [branchForm] = Form.useForm();
    const deletedMode = Boolean(branchTable.filters.deleted);
    const placeOptions = useMemo(() => placeOptionsForCountry(branding?.country_code || 'NP'), [branding?.country_code]);
    const parentOptions = branchTable.extra?.lookups?.parents || [];

    const columns = [
        { title: 'Branch Name', dataIndex: 'name', sorter: true, field: 'name', width: 240 },
        { title: 'Code', dataIndex: 'code', sorter: true, field: 'code', width: 110, render: (value) => value || '-' },
        {
            title: 'Type',
            dataIndex: 'type',
            sorter: true,
            field: 'type',
            width: 140,
            render: (value) => branchTypeOptions.find((item) => item.value === value)?.label || value || '-',
        },
        { title: 'Parent', dataIndex: ['parent', 'name'], width: 180, render: (value) => value || '-' },
        { title: 'Location', dataIndex: 'address', width: 260, render: (value) => value || '-' },
        { title: 'Phone', dataIndex: 'phone', width: 140, render: (value) => value || '-' },
        { title: 'MRs', dataIndex: 'medical_representatives_count', width: 90, align: 'right', render: (value) => Number(value || 0) },
        {
            title: 'Status',
            dataIndex: 'is_active',
            width: 150,
            render: (value, record) => record.deleted_at
                ? <StatusTag active={false} falseText="Deleted" />
                : <StatusToggle value={value} id={record.id} endpoint={endpoints.mrBranches} />,
        },
        {
            title: 'Action',
            key: 'actions',
            fixed: 'right',
            width: deletedMode ? 110 : 120,
            render: (_, record) => record.deleted_at ? (
                <Button icon={<UndoOutlined />} onClick={() => restoreBranch(record)}>Restore</Button>
            ) : (
                <Space>
                    <Button aria-label="Edit branch" icon={<EditOutlined />} onClick={() => openBranch(record)} />
                    <Button aria-label="Delete branch" danger icon={<DeleteOutlined />} onClick={() => deleteBranch(record)} />
                </Space>
            ),
        },
    ];

    function openBranch(record = null) {
        setEditingBranch(record);
        branchForm.resetFields();
        branchForm.setFieldsValue(record ? {
            ...record,
            is_active: Boolean(record.is_active),
        } : {
            type: 'branch',
            is_active: true,
        });
        setBranchModalOpen(true);
    }

    async function saveBranch(values) {
        setSaving(true);
        try {
            const payload = {
                ...values,
                code: values.code ? String(values.code).toUpperCase() : null,
                parent_id: values.type === 'hq' ? null : values.parent_id,
            };

            if (editingBranch) {
                await http.put(`${endpoints.mrBranches}/${editingBranch.id}`, payload);
                notification.success({ message: 'Branch updated' });
            } else {
                await http.post(endpoints.mrBranches, payload);
                notification.success({ message: 'Branch created' });
            }

            setBranchModalOpen(false);
            branchTable.reload();
        } catch (error) {
            branchForm.setFields(Object.entries(validationErrors(error)).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Branch save failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    function deleteBranch(record) {
        confirmDelete({
            title: `Delete ${record.name}?`,
            content: Number(record.medical_representatives_count || 0) > 0
                ? 'This branch has assigned MRs. Reassign them before deleting.'
                : 'This will soft delete the branch. Existing transaction and user history remains intact.',
            onOk: async () => {
                await http.delete(`${endpoints.mrBranches}/${record.id}`);
                notification.success({ message: 'Branch deleted' });
                branchTable.reload();
            },
        });
    }

    function restoreBranch(record) {
        confirmDelete({
            title: `Restore ${record.name}?`,
            content: 'This branch will return to active branch selection lists.',
            okText: 'Restore',
            danger: false,
            onOk: async () => {
                await http.post(endpoints.mrBranchRestore(record.id));
                notification.success({ message: 'Branch restored' });
                branchTable.reload();
            },
        });
    }

    return (
        <Card
            title="Branches"
            extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => openBranch()}>New Branch</Button>}
        >
            <div className="table-toolbar table-toolbar-wide">
                <Input.Search
                    value={branchTable.search}
                    onChange={(event) => branchTable.setSearch(event.target.value)}
                    placeholder="Search branch, code, location or phone"
                    allowClear
                />
                <Select
                    allowClear
                    placeholder="Type"
                    style={{ width: 170 }}
                    value={branchTable.filters.type}
                    onChange={(value) => branchTable.setFilters((current) => ({ ...current, type: value }))}
                    options={branchTypeOptions}
                />
                <Select
                    allowClear
                    placeholder="Status"
                    style={{ width: 130 }}
                    value={branchTable.filters.is_active}
                    onChange={(value) => branchTable.setFilters((current) => ({ ...current, is_active: value }))}
                    options={[
                        { value: true, label: 'Active' },
                        { value: false, label: 'Inactive' },
                    ]}
                />
                <div className="table-switch">
                    <Switch
                        checked={deletedMode}
                        onChange={(deleted) => branchTable.setFilters((filters) => ({ ...filters, deleted: deleted ? 1 : undefined }))}
                    />
                    <span>View Deleted</span>
                </div>
                <Button icon={<ReloadOutlined />} onClick={branchTable.reload}>Refresh</Button>
            </div>

            <ServerTable table={branchTable} columns={columns} />

            <Modal
                centered
                className="intent-modal"
                title={editingBranch ? `Edit Branch: ${editingBranch.name}` : 'New Branch'}
                open={branchModalOpen}
                onCancel={() => setBranchModalOpen(false)}
                onOk={() => branchForm.submit()}
                confirmLoading={saving}
                destroyOnHidden
                width={820}
            >
                <Form form={branchForm} layout="vertical" onFinish={saveBranch}>
                    <div className="form-grid">
                        <Form.Item name="name" label="Branch Name" rules={[{ required: true }]}>
                            <Input autoFocus />
                        </Form.Item>
                        <Form.Item name="code" label="Branch Code">
                            <Input placeholder="KTM-HQ" />
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="type" label="Branch Type" rules={[{ required: true }]}>
                            <Select options={branchTypeOptions} />
                        </Form.Item>
                        <Form.Item shouldUpdate={(previous, current) => previous.type !== current.type} noStyle>
                            {({ getFieldValue }) => (
                                <Form.Item name="parent_id" label="Parent HQ">
                                    <Select
                                        allowClear
                                        disabled={getFieldValue('type') === 'hq'}
                                        showSearch
                                        optionFilterProp="label"
                                        placeholder={getFieldValue('type') === 'hq' ? 'HQ does not need a parent' : 'Select HQ'}
                                        options={parentOptions
                                            .filter((item) => item.id !== editingBranch?.id)
                                            .map((item) => ({
                                                value: item.id,
                                                label: `${item.name}${item.code ? ` (${item.code})` : ''}`,
                                            }))}
                                    />
                                </Form.Item>
                            )}
                        </Form.Item>
                    </div>
                    <div className="form-grid">
                        <Form.Item name="address" label="Location / City">
                            <AutoComplete
                                options={placeOptions}
                                filterOption={(inputValue, option) =>
                                    option?.value?.toLowerCase().includes(inputValue.toLowerCase())
                                }
                                placeholder="Type a city or choose a suggestion"
                            />
                        </Form.Item>
                        <Form.Item name="phone" label="Phone">
                            <Input />
                        </Form.Item>
                    </div>
                    <Form.Item name="is_active" label="Active" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
