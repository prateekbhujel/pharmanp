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
                { key: 'users', label: 'Users', type: 'Admin', description: 'Staff accounts and access control', route: '/app/settings' },
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
                    prefix={loading ? <Spin size="small" style={{ marginRight: 12 }} /> : <SearchOutlined style={{ color: '#94a3b8', fontSize: '22px' }} />}
                    placeholder="Search anything (products, customers, suppliers)..."
                    variant="borderless"
                    size="large"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    autoFocus
                    suffix={
                        <Space size={6}>
                            <Typography.Text keyboard>{isMac ? '⌘' : 'Ctrl'}</Typography.Text>
                            <Typography.Text keyboard>K</Typography.Text>
                        </Space>
                    }
                />
            </div>
            <div className="search-results" style={{ maxHeight: '480px', overflowY: 'auto', padding: '12px 0' }}>
                <List
                    dataSource={results}
                    renderItem={(item) => (
                        <List.Item 
                            className="search-result-item" 
                            onClick={() => handleSelect(item)}
                        >
                            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' }}>
                                <Space size={16}>
                                    <div className="search-icon-box">{ICON_MAP[item.type] || <FileTextOutlined />}</div>
                                    <div>
                                        <Space size={8}>
                                            <Typography.Text strong style={{ fontSize: '15px', color: '#1e293b' }}>{item.label}</Typography.Text>
                                            <Typography.Text type="secondary" style={{ fontSize: '10px', background: '#f1f5f9', padding: '1px 6px', borderRadius: '4px', textTransform: 'uppercase', fontWeight: 700 }}>{item.type}</Typography.Text>
                                        </Space>
                                        <br />
                                        <Typography.Text type="secondary" style={{ fontSize: '12px' }}>{item.description}</Typography.Text>
                                    </div>
                                </Space>
                                <ArrowRightOutlined className="result-arrow" style={{ opacity: 0.3 }} />
                            </div>
                        </List.Item>
                    )}
                />
                {!loading && results.length === 0 && search && (
                    <div style={{ padding: '60px 20px', textAlign: 'center' }}>
                        <SearchOutlined style={{ fontSize: '48px', color: '#e2e8f0', marginBottom: '16px' }} />
                        <Typography.Title level={5} style={{ color: '#94a3b8', margin: 0 }}>No results found</Typography.Title>
                        <Typography.Text type="secondary">Try searching for something else</Typography.Text>
                    </div>
                )}
            </div>
            <div className="search-footer">
                <Space size={24}>
                    <Typography.Text type="secondary" style={{ fontSize: '12px' }}>
                        <Typography.Text keyboard>Enter</Typography.Text> Select
                    </Typography.Text>
                    <Typography.Text type="secondary" style={{ fontSize: '12px' }}>
                        <Typography.Text keyboard>↑↓</Typography.Text> Navigate
                    </Typography.Text>
                    <Typography.Text type="secondary" style={{ fontSize: '12px' }}>
                        <Typography.Text keyboard>Esc</Typography.Text> Close
                    </Typography.Text>
                </Space>
            </div>
        </Modal>
    );
}
