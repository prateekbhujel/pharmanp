import React, { useEffect, useState } from 'react';
import { App, Button, Form, Input, Modal, Space } from 'antd';
import { PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { appUrl } from '../../core/utils/url';
import { openAuthenticatedDocument } from '../../core/utils/documents';
import { PurchaseReturnsPanel } from './PurchaseReturnsPanel';
import { PurchaseOrdersPanel } from './PurchaseOrdersPanel';
import { PurchaseEntryPanel } from './PurchaseEntryPanel';
import { PurchaseBillsPanel } from './PurchaseBillsPanel';

const OCR_DRAFT_STORAGE_KEY = 'pharmanp-purchase-ocr-draft';
const fallbackPaymentTypes = [
    { value: 'cash', label: 'Cash' },
    { value: 'credit', label: 'Credit' },
    { value: 'partial', label: 'Partial' },
];

function purchaseSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    if (['entry', 'orders', 'returns', 'expiry-returns'].includes(section)) {
        return section;
    }

    return 'bills';
}

function goToApp(path) {
    window.history.pushState({}, '', appUrl(path));
    window.dispatchEvent(new PopStateEvent('popstate'));
}

export function PurchasesPage() {
    const { notification } = App.useApp();
    const section = purchaseSection();
    const [suppliers, setSuppliers] = useState([]);
    const [products, setProducts] = useState([]);
    const [paymentModes, setPaymentModes] = useState([]);
    const [paymentTypes, setPaymentTypes] = useState(fallbackPaymentTypes);
    const [quickSupplierOpen, setQuickSupplierOpen] = useState(false);
    const [lastPurchasePrintUrl, setLastPurchasePrintUrl] = useState(null);
    const [ocrDraft, setOcrDraft] = useState(null);
    const [supplierForm] = Form.useForm();

    useEffect(() => {
        loadSuppliers();
        loadPaymentLookups();
        searchProducts('');
        loadOcrDraft();
    }, []);

    async function loadSuppliers() {
        const { data } = await http.get(endpoints.supplierOptions);
        setSuppliers(data.data);
    }

    async function loadPaymentLookups() {
        try {
            const { data } = await http.get(endpoints.dropdownOptions);
            const rows = Array.isArray(data.data) ? data.data : [];
            setPaymentModes(rows.filter((item) => item.alias === 'payment_mode' && item.is_active !== false));
            const types = rows
                .filter((item) => item.alias === 'payment_type' && item.is_active !== false)
                .map((item) => ({ value: item.data || item.name?.toLowerCase(), label: item.name }));
            setPaymentTypes(types.length ? types : fallbackPaymentTypes);
        } catch {
            setPaymentModes([]);
            setPaymentTypes(fallbackPaymentTypes);
        }
    }

    function loadOcrDraft() {
        try {
            const stored = window.sessionStorage.getItem(OCR_DRAFT_STORAGE_KEY);
            if (!stored) return;

            const draft = JSON.parse(stored);
            setOcrDraft(draft);
        } catch {
            window.sessionStorage.removeItem(OCR_DRAFT_STORAGE_KEY);
        }
    }

    function clearOcrDraft() {
        window.sessionStorage.removeItem(OCR_DRAFT_STORAGE_KEY);
        setOcrDraft(null);
    }

    async function searchProducts(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        setProducts(data.data || []);
    }

    async function submitSupplier(values) {
        try {
            await http.post(endpoints.suppliers, values);
            await loadSuppliers();
            supplierForm.resetFields();
            setQuickSupplierOpen(false);
            notification.success({ message: 'Supplier added' });
        } catch (error) {
            supplierForm.setFields(Object.entries(validationErrors(error)).map(([name, errors]) => ({ name, errors })));
            notification.error({ message: 'Supplier save failed', description: error?.response?.data?.message || error.message });
        }
    }

    return (
        <div className="page-stack">
            <PageHeader
                actions={(
                    <Space>
                        {section !== 'entry' && <Button type="primary" icon={<PlusOutlined />} onClick={() => goToApp('/app/purchases/entry')}>New Purchase</Button>}
                        {section !== 'bills' && <Button onClick={() => goToApp('/app/purchases/bills')}>Purchase Bills</Button>}
                        <Button disabled={!lastPurchasePrintUrl} icon={<PrinterOutlined />} onClick={() => openAuthenticatedDocument(lastPurchasePrintUrl)}>Print Last Purchase</Button>
                    </Space>
                )}
            />

            {section === 'entry' && (
                <PurchaseEntryPanel
                    suppliers={suppliers}
                    paymentModes={paymentModes}
                    paymentTypes={paymentTypes}
                    ocrDraft={ocrDraft}
                    clearOcrDraft={clearOcrDraft}
                    onSuccess={(url) => setLastPurchasePrintUrl(url)}
                    searchProducts={searchProducts}
                    products={products}
                    setProducts={setProducts}
                    setQuickSupplierOpen={setQuickSupplierOpen}
                />
            )}

            {section === 'bills' && (
                <PurchaseBillsPanel suppliers={suppliers} />
            )}

            {section === 'orders' && (
                <PurchaseOrdersPanel />
            )}

            {['returns', 'expiry-returns'].includes(section) && (
                <PurchaseReturnsPanel />
            )}

            <Modal
                title="Quick Add Supplier"
                open={quickSupplierOpen}
                onCancel={() => setQuickSupplierOpen(false)}
                onOk={() => supplierForm.submit()}
                destroyOnHidden
            >
                <Form form={supplierForm} layout="vertical" onFinish={submitSupplier}>
                    <Form.Item name="name" label="Supplier Name" rules={[{ required: true }]}><Input autoFocus /></Form.Item>
                    <div className="form-grid">
                        <Form.Item name="phone" label="Phone"><Input /></Form.Item>
                        <Form.Item name="email" label="Email"><Input /></Form.Item>
                    </div>
                    <Form.Item name="address" label="Address"><Input /></Form.Item>
                </Form>
            </Modal>
        </div>
    );
}
