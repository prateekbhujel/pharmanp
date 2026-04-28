import React, { useMemo, useState } from 'react';
import { Button, Card, Form, Input, Modal, Select, Space, Switch, Table, Tabs } from 'antd';
import { DeleteOutlined, EditOutlined, PlusOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { StatusToggle } from '../../core/components/StatusToggle';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { useApi } from '../../core/hooks/useApi';
import { dropdownAliasOptions, dropdownDataField, fallbackDropdownAliases } from '../../core/utils/dropdownOptions';
import { PaymentModePanel } from './PaymentModePanel';

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
            <PageHeader
                title="Master Data"
                description="Manage payment modes and reusable lookup values used across the app."
            />

            <Tabs items={[
                {
                    key: 'payment-modes',
                    label: 'Payment Modes',
                    children: <PaymentModePanel />,
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
