import React, { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Input, Select, Space, Switch } from 'antd';
import { PlusOutlined, ReloadOutlined } from '@ant-design/icons';
import { Form } from 'antd';
import { ExportButtons, ImportButton } from '../../core/components/ListToolbarActions';
import { PageHeader } from '../../core/components/PageHeader';
import { ServerTable } from '../../core/components/ServerTable';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { printBarcodeLabels } from '../../core/components/BarcodeLabel';
import { makeBarcodeCandidate } from '../../core/utils/code128';
import { InventoryMasterTable } from './InventoryMasterTable';
import { StockAdjustmentsPanel } from './StockAdjustmentsPanel';
import { StockMovementsPanel } from './StockMovementsPanel';
import { ProductHistoryDrawer } from './ProductHistoryDrawer';
import { ProductFormDrawer } from './ProductFormDrawer';
import { productColumns, ProductExpandedRow } from './productColumns';
import { productPayload, productFormDefaults, productEditValues } from './productPayload';

const inventorySections = {
    companies: { title: 'Company', master: 'companies' },
    units: { title: 'Unit', master: 'units' },
    'stock-adjustment': { title: 'Stock Adjustment' },
    'stock-ledger': { title: 'Stock Ledger' },
    products: { title: 'Product' },
};

function currentSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    return inventorySections[section] ? section : 'products';
}

export function ProductsPage() {
    const { notification } = App.useApp();
    const section = currentSection();
    const sectionConfig = inventorySections[section];
    const table = useServerTable({ endpoint: endpoints.products });
    const [meta, setMeta] = useState({ companies: [], units: [], divisions: [] });
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [saving, setSaving] = useState(false);
    const [quickMaster, setQuickMaster] = useState(null);
    const [historyProduct, setHistoryProduct] = useState(null);
    const [form] = Form.useForm();
    const [quickForm] = Form.useForm();
    const deletedMode = Boolean(table.filters.deleted);

    useEffect(() => {
        loadMeta();
    }, []);

    async function loadMeta() {
        const { data } = await http.get(endpoints.productMeta);
        setMeta(data.data);
    }

    function openCreate() {
        setEditing(null);
        form.resetFields();
        form.setFieldsValue(productFormDefaults());
        setDrawerOpen(true);
    }

    function openEdit(record) {
        setEditing(record);
        form.setFieldsValue(productEditValues(record));
        setDrawerOpen(true);
    }

    async function submit(values) {
        setSaving(true);
        try {
            if (editing) {
                await http.post(`${endpoints.products}/${editing.id}`, productPayload(values, 'PUT'));
                notification.success({ message: 'Product updated' });
            } else {
                await http.post(endpoints.products, productPayload(values));
                notification.success({ message: 'Product created' });
            }
            setDrawerOpen(false);
            table.reload();
        } catch (error) {
            const errors = validationErrors(error);
            form.setFields(Object.entries(errors).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: 'Product save failed', description: error?.response?.data?.message || error.message });
        } finally {
            setSaving(false);
        }
    }

    async function submitQuickMaster(values) {
        const config = {
            company: { endpoint: endpoints.quickCompany, label: 'Company' },
            unit: { endpoint: endpoints.quickUnit, label: 'Unit' },
        }[quickMaster];

        if (!config) {
            return;
        }

        try {
            const { data } = await http.post(config.endpoint, {
                ...values,
                company_id: form.getFieldValue('company_id'),
            });
            await loadMeta();
            quickForm.resetFields();
            setQuickMaster(null);
            notification.success({ message: `${config.label} added` });

            if (quickMaster === 'company') {
                form.setFieldValue('company_id', data.data.id);
            }
            if (quickMaster === 'unit') {
                form.setFieldValue('unit_id', data.data.id);
            }
        } catch (error) {
            const errors = validationErrors(error);
            quickForm.setFields(Object.entries(errors).map(([name, messages]) => ({ name, errors: messages })));
            notification.error({ message: `${config.label} save failed`, description: error?.response?.data?.message || error.message });
        }
    }

    function remove(record) {
        confirmDelete({
            title: 'Delete product?',
            content: `${record.name} will be soft deleted.`,
            onOk: async () => {
                await http.delete(`${endpoints.products}/${record.id}`);
                notification.success({ message: 'Product deleted' });
                table.reload();
            },
        });
    }

    function restore(record) {
        confirmDelete({
            title: 'Restore product?',
            content: `${record.name} will return to the active product list.`,
            okText: 'Restore',
            danger: false,
            onOk: async () => {
                await http.post(endpoints.productRestore(record.id));
                notification.success({ message: 'Product restored' });
                table.reload();
            },
        });
    }

    function syncPricing(changedValues, values) {
        if (!('mrp' in changedValues) && !('discount_percent' in changedValues)) {
            return;
        }

        const mrp = Number(values.mrp || 0);
        const discount = Number(values.discount_percent || 0);
        form.setFieldValue('selling_price', Number((mrp - (mrp * discount / 100)).toFixed(2)));
    }

    function generateBarcode() {
        const seed = form.getFieldValue('sku')
            || form.getFieldValue('product_code')
            || form.getFieldValue('name')
            || 'PNP';

        form.setFieldValue('barcode', makeBarcodeCandidate(seed));
    }

    function printProductBarcode(record) {
        const printed = printBarcodeLabels([record]);

        if (!printed) {
            notification.warning({ message: 'Add barcode or SKU before printing' });
        }
    }

    const columns = useMemo(() => productColumns({
        onHistory: setHistoryProduct,
        onEdit: openEdit,
        onDelete: remove,
        onRestore: restore,
        onPrintBarcode: printProductBarcode,
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }), []);

    function sectionBody() {
        if (sectionConfig.master) {
            return <InventoryMasterTable master={sectionConfig.master} />;
        }

        if (section === 'stock-adjustment') {
            return <StockAdjustmentsPanel />;
        }

        if (section === 'stock-ledger') {
            return <StockMovementsPanel />;
        }

        return (
            <Card title="Product List">
                <div className="table-toolbar table-toolbar-products">
                    <Input.Search value={table.search} onChange={(event) => table.setSearch(event.target.value)} placeholder="Search product, generic, code or barcode" allowClear />
                    <Select
                        allowClear
                        placeholder="Company"
                        options={meta.companies?.map((item) => ({ value: item.id, label: item.name }))}
                        onChange={(company_id) => table.setFilters((filters) => ({ ...filters, company_id }))}
                    />
                    <Select
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        placeholder="Division"
                        options={meta.divisions?.map((item) => ({ value: item.id, label: item.code ? `${item.name} (${item.code})` : item.name }))}
                        onChange={(division_id) => table.setFilters((filters) => ({ ...filters, division_id }))}
                    />
                    <div className="table-switch">
                        <Switch
                            checked={deletedMode}
                            onChange={(deleted) => table.setFilters((filters) => ({ ...filters, deleted: deleted ? 1 : undefined }))}
                        />
                        <span>View Deleted</span>
                    </div>
                    <Button icon={<ReloadOutlined />} onClick={table.reload}>Refresh</Button>
                </div>
                <ServerTable
                    table={table}
                    columns={columns}
                    expandable={{ expandedRowRender: (record) => <ProductExpandedRow record={record} /> }}
                />
            </Card>
        );
    }

    return (
        <div className="page-stack">
            {section === 'products' ? (
                <PageHeader
                    actions={(
                    <Space wrap>
                        <ExportButtons basePath={endpoints.inventoryProductsExport} params={{ search: table.search, ...table.filters }} />
                        <ImportButton target="products" />
                        <Button type="primary" icon={<PlusOutlined />} onClick={openCreate}>Add Product</Button>
                    </Space>
                    )}
                />
            ) : null}

            {sectionBody()}

            {historyProduct ? (
                <ProductHistoryDrawer
                    key={historyProduct.id}
                    product={historyProduct}
                    onClose={() => setHistoryProduct(null)}
                />
            ) : null}

            <ProductFormDrawer
                open={drawerOpen}
                editing={editing}
                form={form}
                quickForm={quickForm}
                quickMaster={quickMaster}
                onClose={() => setDrawerOpen(false)}
                onSubmit={submit}
                onSubmitQuickMaster={submitQuickMaster}
                onSetQuickMaster={setQuickMaster}
                onGenerateBarcode={generateBarcode}
                onSyncPricing={syncPricing}
                saving={saving}
                meta={meta}
            />
        </div>
    );
}
