import React, { useState, useEffect, useMemo } from 'react';
import { Modal, Input, List, Typography, Space, Spin } from 'antd';
import { SearchOutlined, FileTextOutlined, ShoppingCartOutlined, UserOutlined, SettingOutlined, ArrowRightOutlined, TeamOutlined, ShopOutlined } from '@ant-design/icons';
import { http } from '../api/http';
import { endpoints } from '../api/endpoints';
import { isMacPlatform } from '../utils/platform';

const ICON_MAP = {
    'Page': <FileTextOutlined />,
    'Inventory': <ShoppingCartOutlined />,
    'Product': <ShoppingCartOutlined />,
    'Sales': <FileTextOutlined />,
    'Purchase': <ShopOutlined />,
    'Admin': <SettingOutlined />,
    'Customer': <TeamOutlined />,
    'Supplier': <ShopOutlined />,
};

export function GlobalSearch({ visible, onCancel, onNavigate }) {
    const [search, setSearch] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [isMac, setIsMac] = useState(false);

    useEffect(() => {
        setIsMac(isMacPlatform());
    }, []);

    useEffect(() => {
        if (!search) {
            setResults([
                { key: 'dashboard', label: 'Dashboard', type: 'Page', description: 'Overview of your business metrics', route: '/app' },
                { key: 'products', label: 'Products', type: 'Inventory', description: 'Manage medicine inventory and stock', route: '/app/inventory/products' },
                { key: 'sales', label: 'Sales Invoices', type: 'Sales', description: 'View and create customer invoices', route: '/app/sales/invoices' },
                { key: 'purchases', label: 'Purchase Bills', type: 'Purchase', description: 'Supplier purchase bills and receiving', route: '/app/purchases/bills' },
                { key: 'users', label: 'Users', type: 'Admin', description: 'Staff accounts and access control', route: '/app/administration/users' },
                { key: 'roles', label: 'Roles & Permissions', type: 'Admin', description: 'Readable access control matrix', route: '/app/administration/roles' },
                { key: 'dropdowns', label: 'Dropdown Masters', type: 'Admin', description: 'Payment modes, types and reusable dropdowns', route: '/app/administration/data-lookup' },
                { key: 'settings', label: 'Settings', type: 'Admin', description: 'System configuration and branding', route: '/app/settings' },
            ]);
            return;
        }

        const handler = setTimeout(async () => {
            setLoading(true);
            try {
                const { data } = await http.get(endpoints.search, { params: { query: search } });
                setResults(data.data || []);
            } catch (e) {
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => clearTimeout(handler);
    }, [search]);

    const handleSelect = (item) => {
        if (item.route) {
            onNavigate(item.route);
        } else {
            onNavigate(item.key);
        }
        onCancel();
        setSearch('');
    };

    return (
        <Modal
            open={visible}
            onCancel={onCancel}
            footer={null}
            closable={false}
            styles={{ body: { padding: 0 } }}
            className="global-search-modal"
            width={720}
            centered
        >
            <div className="search-input-wrapper">
                <Input
                    prefix={loading ? <Spin size="small" style={{ marginRight: 12 }} /> : <SearchOutlined style={{ color: '#1e293b', fontSize: '20px' }} />}
                    placeholder="Search anything (products, customers, invoices)..."
                    variant="borderless"
                    size="large"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    autoFocus
                    suffix={
                        <div className="search-trigger-kbd">
                            {isMac ? '⌘' : 'CTRL'} K
                        </div>
                    }
                />
            </div>
            <div className="search-results" style={{ maxHeight: '420px', overflowY: 'auto' }}>
                <div style={{ padding: '8px 12px', fontSize: '11px', fontWeight: 700, color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                    {search ? 'Search Results' : 'Recent Actions / Pages'}
                </div>
                <List
                    dataSource={results}
                    renderItem={(item) => (
                        <List.Item 
                            className="search-result-item" 
                            onClick={() => handleSelect(item)}
                        >
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' }}>
                                <Space size={14}>
                                    <div className="search-icon-box">{ICON_MAP[item.type] || <FileTextOutlined />}</div>
                                    <div>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            <span style={{ fontSize: '14px', fontWeight: 600, color: '#0f172a' }}>{item.label}</span>
                                            <span style={{ fontSize: '10px', background: '#f1f5f9', color: '#64748b', padding: '1px 6px', borderRadius: '4px', textTransform: 'uppercase', fontWeight: 700 }}>{item.type}</span>
                                        </div>
                                        <div style={{ fontSize: '12px', color: '#64748b', marginTop: '2px' }}>{item.description}</div>
                                    </div>
                                </Space>
                                <ArrowRightOutlined className="result-arrow" style={{ opacity: 0, fontSize: '14px' }} />
                            </div>
                        </List.Item>
                    )}
                />
                {!loading && results.length === 0 && search && (
                    <div style={{ padding: '48px 20px', textAlign: 'center' }}>
                        <SearchOutlined style={{ fontSize: '32px', color: '#e2e8f0', marginBottom: '12px' }} />
                        <div style={{ fontWeight: 600, color: '#94a3b8' }}>No matches found</div>
                        <div style={{ fontSize: '12px', color: '#cbd5e1' }}>Try different keywords or tags</div>
                    </div>
                )}
            </div>
            <div className="search-footer">
                <Space size={20}>
                    <span style={{ fontSize: '11px', color: '#94a3b8' }}><span className="search-trigger-kbd" style={{ verticalAlign: 'middle', marginRight: 4 }}>↵</span> Select</span>
                    <span style={{ fontSize: '11px', color: '#94a3b8' }}><span className="search-trigger-kbd" style={{ verticalAlign: 'middle', marginRight: 4 }}>↑↓</span> Navigate</span>
                    <span style={{ fontSize: '11px', color: '#94a3b8' }}><span className="search-trigger-kbd" style={{ verticalAlign: 'middle', marginRight: 4 }}>ESC</span> Close</span>
                </Space>
            </div>
        </Modal>
    );
}
