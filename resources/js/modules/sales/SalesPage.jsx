import React, { useEffect, useRef, useState } from 'react';
import { Button, Space } from 'antd';
import { PlusOutlined, PrinterOutlined } from '@ant-design/icons';
import { PageHeader } from '../../core/components/PageHeader';
import { endpoints } from '../../core/api/endpoints';
import { http } from '../../core/api/http';
import { appUrl } from '../../core/utils/url';
import { openAuthenticatedDocument } from '../../core/utils/documents';
import { SalesReturnsPanel } from './SalesReturnsPanel';
import { SalesPosPanel } from './SalesPosPanel';
import { SalesInvoicesPanel } from './SalesInvoicesPanel';

const fallbackPaymentTypes = [
    { value: 'cash', label: 'Cash' },
    { value: 'credit', label: 'Credit' },
    { value: 'partial', label: 'Partial' },
    { value: 'qr', label: 'QR / Digital Wallet' },
];

function salesSection() {
    const section = window.location.pathname.split('/').filter(Boolean).pop();

    if (['invoices', 'returns', 'expiry-returns', 'pos'].includes(section)) {
        return section;
    }

    return 'invoices';
}

function goToApp(path) {
    window.history.pushState({}, '', appUrl(path));
    window.dispatchEvent(new PopStateEvent('popstate'));
}

export function SalesPage() {
    const section = salesSection();
    const [customers, setCustomers] = useState([]);
    const [medicalRepresentatives, setMedicalRepresentatives] = useState([]);
    const [paymentModes, setPaymentModes] = useState([]);
    const [paymentTypes, setPaymentTypes] = useState(fallbackPaymentTypes);
    const [lastPrintUrl, setLastPrintUrl] = useState(null);

    const posPanelRef = useRef(null); // Reference to trigger POS actions from hotkeys

    useEffect(() => {
        loadCustomers();
        loadMedicalRepresentatives();
        loadPaymentLookups();

        function handleKeyDown(event) {
            if (event.altKey && (event.key.toLowerCase() === 's' || event.code === 'KeyS')) {
                event.preventDefault();
                if (section === 'pos') posPanelRef.current?.submitInvoice();
            }
            if (event.altKey && (event.key.toLowerCase() === 'n' || event.code === 'KeyN')) {
                event.preventDefault();
                goToApp('/app/sales/pos');
            }
            if (event.altKey && (event.key.toLowerCase() === 'a' || event.code === 'KeyA')) {
                event.preventDefault();
                if (section === 'pos') {
                    posPanelRef.current?.focusProductSearch();
                }
            }
            if (event.altKey && (event.key.toLowerCase() === 'b' || event.code === 'KeyB')) {
                event.preventDefault();
                if (section === 'pos') posPanelRef.current?.focusBarcode();
            }
            if (event.altKey && (event.key.toLowerCase() === 'p' || event.code === 'KeyP')) {
                event.preventDefault();
                if (section === 'pos') posPanelRef.current?.focusPaidAmount();
            }
            if (event.altKey && (event.key.toLowerCase() === 'q' || event.code === 'KeyQ')) {
                event.preventDefault();
                if (section === 'pos') posPanelRef.current?.openQuickProduct();
            }
        }

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [section]);

    async function loadCustomers() {
        const { data } = await http.get(endpoints.customerOptions);
        setCustomers(data.data);
    }

    async function loadMedicalRepresentatives() {
        try {
            const { data } = await http.get(endpoints.mrOptions);
            setMedicalRepresentatives(data.data || []);
        } catch {
            setMedicalRepresentatives([]);
        }
    }

    async function loadPaymentLookups() {
        try {
            const { data } = await http.get(endpoints.dropdownOptions);
            const options = data.data || [];
            const modes = options.filter((item) => item.alias === 'payment_mode' && item.is_active !== false);
            const types = options
                .filter((item) => item.alias === 'payment_type' && item.is_active !== false)
                .map((item) => ({ value: item.code || item.name?.toLowerCase(), label: item.name }))
                .filter((item) => item.value && item.label);

            setPaymentModes(modes);
            setPaymentTypes(types.length ? types : fallbackPaymentTypes);
        } catch {
            setPaymentModes([]);
            setPaymentTypes(fallbackPaymentTypes);
        }
    }

    async function searchProduct(q) {
        const { data } = await http.get(endpoints.salesProductLookup, { params: { q } });
        return (data.data || []).flatMap((product) => (product.batches || []).map((batch) => ({
            value: `${product.id}:${batch.id}`,
            label: `${product.name} | ${batch.batch_no} | stock ${batch.quantity_available}`,
            product,
            batch,
        })));
    }

    return (
        <div className="page-stack">
            <PageHeader
                actions={(
                    <Space>
                        {section !== 'pos' && <Button type="primary" onClick={() => goToApp('/app/sales/pos')}>New Sales</Button>}
                        {section !== 'invoices' && <Button onClick={() => goToApp('/app/sales')}>Sales</Button>}
                        <Button disabled={!lastPrintUrl} icon={<PrinterOutlined />} onClick={() => openAuthenticatedDocument(lastPrintUrl)}>Print Last Invoice</Button>
                    </Space>
                )}
            />

            {section === 'pos' && (
                <SalesPosPanel
                    ref={posPanelRef}
                    customers={customers}
                    medicalRepresentatives={medicalRepresentatives}
                    paymentModes={paymentModes}
                    paymentTypes={paymentTypes}
                    onCustomerAdded={loadCustomers}
                    onMrAdded={loadMedicalRepresentatives}
                    onInvoiceSuccess={(url) => setLastPrintUrl(url)}
                    searchProduct={searchProduct}
                />
            )}

            {section === 'invoices' && (
                <SalesInvoicesPanel
                    customers={customers}
                    medicalRepresentatives={medicalRepresentatives}
                    paymentModes={paymentModes}
                />
            )}

            {['returns', 'expiry-returns'].includes(section) && (
                <SalesReturnsPanel />
            )}
        </div>
    );
}
