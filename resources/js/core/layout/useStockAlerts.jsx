import React, { useEffect, useMemo, useState } from 'react';
import HistoryOutlined from '@ant-design/icons/es/icons/HistoryOutlined';
import WarningOutlined from '@ant-design/icons/es/icons/WarningOutlined';
import { http } from '../api/http';
import { endpoints } from '../api/endpoints';
import { formatCalendarDate } from '../utils/calendar';
import { appUrl } from '../utils/url';

const ALERT_DISMISS_STORAGE_KEY = 'pharmanp-dismissed-alert-signature';

function buildAlertSignature(lowStockRows = [], expiryRows = []) {
    return [
        ...lowStockRows.map((item) => [
            'low',
            item.id,
            item.stock_on_hand,
            item.reorder_level,
        ].join(':')),
        ...expiryRows.map((item) => [
            'expiry',
            item.id,
            item.batch_no || '',
            item.expires_at || '',
            item.quantity_available || '',
        ].join(':')),
    ].sort().join('|');
}

function emptyAlerts(signature = '') {
    return { loading: false, lowStockRows: [], expiryRows: [], count: 0, signature };
}

export function useStockAlerts({ calendarType = 'ad', navigate }) {
    const [alerts, setAlerts] = useState({ loading: true, lowStockRows: [], expiryRows: [], count: 0, signature: '' });

    useEffect(() => {
        let active = true;

        http.get(endpoints.dashboard)
            .then(({ data }) => {
                if (!active) return;

                const payload = data.data || {};
                const stats = payload.stats || {};
                const lowStockRows = payload.low_stock_rows || [];
                const expiryRows = payload.expiry_rows || [];
                const signature = buildAlertSignature(lowStockRows, expiryRows);
                const dismissedSignature = window.localStorage.getItem(ALERT_DISMISS_STORAGE_KEY);
                const dismissed = signature && dismissedSignature === signature;
                const count = dismissed
                    ? 0
                    : Number(stats.low_stock || lowStockRows.length || 0)
                        + Number(stats.expiring_batches || expiryRows.length || 0);

                setAlerts({
                    loading: false,
                    lowStockRows: dismissed ? [] : lowStockRows,
                    expiryRows: dismissed ? [] : expiryRows,
                    count,
                    signature,
                });
            })
            .catch(() => {
                if (active) setAlerts(emptyAlerts());
            });

        return () => { active = false; };
    }, []);

    const notificationItems = useMemo(() => {
        if (!alerts.count) {
            return [{ key: 'empty', disabled: true, label: <div className="notification-empty">No stock alerts right now</div> }];
        }

        const items = [
            {
                key: 'header',
                disabled: true,
                label: (
                    <div className="notification-tray-header">
                        <strong>Notifications</strong>
                        <span>{alerts.count} active</span>
                    </div>
                ),
            },
            { key: 'mark-read', label: <div className="notification-tray-action">Mark all as read</div> },
            { type: 'divider' },
        ];

        if (alerts.lowStockRows.length > 0) {
            items.push({ key: 'low-stock-title', disabled: true, label: <div className="notification-title"><WarningOutlined /> Low stock alert</div> });
            alerts.lowStockRows.slice(0, 5).forEach((item) => {
                items.push({
                    key: `low-stock-${item.id}`,
                    label: (
                        <div className="notification-row">
                            <div className="notification-content">
                                <span className="notification-subject">{item.name}</span>
                                <span className="notification-meta">{item.stock_on_hand} in stock, reorder at {item.reorder_level}</span>
                            </div>
                        </div>
                    ),
                });
            });
        }

        if (alerts.lowStockRows.length > 0 && alerts.expiryRows.length > 0) {
            items.push({ type: 'divider' });
        }

        if (alerts.expiryRows.length > 0) {
            items.push({ key: 'expiry-title', disabled: true, label: <div className="notification-title"><HistoryOutlined /> Expiry watch</div> });
            alerts.expiryRows.slice(0, 5).forEach((item) => {
                items.push({
                    key: `expiry-${item.id}`,
                    label: (
                        <div className="notification-row">
                            <div className="notification-content">
                                <span className="notification-subject">{item.name}</span>
                                <span className="notification-meta">
                                    Batch {item.batch_no || '-'} expires {formatCalendarDate(item.expires_at, calendarType, { style: 'compact' })}
                                </span>
                            </div>
                        </div>
                    ),
                });
            });
        }

        items.push({ type: 'divider' });
        items.push({ key: 'footer', label: <div className="notification-tray-footer">View detailed reports</div> });

        return items;
    }, [alerts, calendarType]);

    function handleNotificationClick({ key }) {
        if (key === 'mark-read') {
            if (alerts.signature) {
                window.localStorage.setItem(ALERT_DISMISS_STORAGE_KEY, alerts.signature);
            }

            setAlerts((current) => emptyAlerts(current.signature));
            return;
        }

        if (key === 'footer') {
            navigate(appUrl('/app/reports/inventory'));
            return;
        }

        if (key.startsWith('low-stock')) navigate(appUrl('/app/reports/low-stock'));
        if (key.startsWith('expiry')) navigate(appUrl('/app/reports/expiry'));
    }

    return { alerts, notificationItems, handleNotificationClick };
}
