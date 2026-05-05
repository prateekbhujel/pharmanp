import React, { useState } from 'react';
import { App, Button, Card, Drawer, Form, Input, InputNumber, Select, Space, Tooltip } from 'antd';
import { DeleteOutlined, EditOutlined, EnvironmentOutlined, PlusOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import { DateText } from '../../core/components/DateText';
import { PharmaBadge } from '../../core/components/PharmaBadge';
import { Money } from '../../core/components/Money';
import { SmartDatePicker } from '../../core/components/SmartDatePicker';
import { LocationSearch } from '../../core/components/LocationSearch';
import { ServerTable } from '../../core/components/ServerTable';
import { confirmDelete } from '../../core/components/ConfirmDelete';
import { endpoints } from '../../core/api/endpoints';
import { http, validationErrors } from '../../core/api/http';
import { useServerTable } from '../../core/hooks/useServerTable';
import { mrVisitStatusOptions } from '../../core/utils/accountCatalog';
import { applyDateRangeFilter } from '../../core/utils/dateFilters';

export function MrVisitsPanel({ mrOptions, customers, canVisits }) {
    const { notification } = App.useApp();
    const [view, setView] = useState('list');
    const [visitRange, setVisitRange] = useState([]);
    const [editingVisit, setEditingVisit] = useState(null);
    const [mapVisit, setMapVisit] = useState(null);
    const [visitForm] = Form.useForm();

    const visitTable = useServerTable({
        endpoint: endpoints.mrVisits,
        defaultSort: { field: 'visit_date', order: 'desc' },
        enabled: canVisits,
    });

    function openVisit(record = null) {
        setEditingVisit(record);
        visitForm.resetFields();

        visitForm.setFieldsValue(
            record
                ? {
                    ...record,
                    medical_representative_id:
                        record.medical_representative_id ?? record.medical_representative?.id,
                    customer_id: record.customer_id ?? record.customer?.id,
                    visit_date: record.visit_date ? dayjs(record.visit_date) : dayjs(),
                }
                : {
                    visit_date: dayjs(),
                    status: 'planned',
                    order_value: 0,
                },
        );

        setView('visit');
    }

    async function saveVisit(values) {
        try {
            const payload = {
                ...values,
                visit_date: values.visit_date.format('YYYY-MM-DD'),
            };

            if (editingVisit) {
                await http.put(`${endpoints.mrVisits}/${editingVisit.id}`, payload);
                notification.success({ message: 'Visit updated' });
            } else {
                await http.post(endpoints.mrVisits, payload);
                notification.success({ message: 'Visit created' });
            }

            setView('list');
            visitTable.reload();
        } catch (e) {
            visitForm.setFields(
                Object.entries(validationErrors(e)).map(([name, errors]) => ({ name, errors })),
            );

            notification.error({ message: 'Save failed' });
        }
    }

    function deleteVisit(record) {
        confirmDelete({
            title: 'Delete this visit?',
            onOk: async () => {
                await http.delete(`${endpoints.mrVisits}/${record.id}`);
                notification.success({ message: 'Visit deleted' });
                visitTable.reload();
            },
        });
    }

    function updateVisitRange(range) {
        setVisitRange(range || []);
        visitTable.setFilters((current) => ({
            ...applyDateRangeFilter(current, range),
        }));
    }

    function captureLocation() {
        if (!navigator.geolocation) {
            notification.warning({ message: 'Geolocation is not supported by this browser' });
            return;
        }

        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                const lat = pos.coords.latitude.toFixed(7);
                const lon = pos.coords.longitude.toFixed(7);
                visitForm.setFieldsValue({ latitude: lat, longitude: lon });

                try {
                    const response = await fetch(
                        `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json&addressdetails=1`,
                        { headers: { 'Accept-Language': 'en' } },
                    );
                    const data = await response.json();
                    if (data.display_name) {
                        visitForm.setFieldValue('location_name', data.display_name);
                    }
                } catch {
                    visitForm.setFieldValue('location_name', visitForm.getFieldValue('location_name') || `${lat}, ${lon}`);
                }

                notification.success({ message: 'Location captured and resolved' });
            },
            () => notification.warning({ message: 'Location access denied' }),
        );
    }

    function handleLocationSelect(locationName, coords) {
        visitForm.setFieldValue('location_name', locationName);
        if (coords) {
            visitForm.setFieldsValue({ latitude: coords.lat, longitude: coords.lon });
        }
    }

    const visitColumns = [
        {
            title: 'Date',
            dataIndex: 'visit_date',
            width: 110,
            sorter: true,
            field: 'visit_date',
            render: (value) => <DateText value={value} style="compact" />,
        },
        {
            title: 'MR',
            dataIndex: ['medical_representative', 'name'],
            render: (value) => value || '—',
        },
        {
            title: 'Customer',
            dataIndex: ['customer', 'name'],
            render: (value) => value || '—',
        },
        {
            title: 'Status',
            dataIndex: 'status',
            width: 120,
            render: (value) => (
                <PharmaBadge tone={value} dot>
                    {value}
                </PharmaBadge>
            ),
        },
        {
            title: 'Order Value',
            dataIndex: 'order_value',
            align: 'right',
            width: 130,
            render: (value) => <Money value={value} />,
        },
        {
            title: 'Location',
            width: 180,
            render: (_, record) => (
                record.location_name || record.has_coordinates ? (
                    <Tooltip title={record.location_name || 'Captured location'}>
                        <Button
                            type="link"
                            icon={<EnvironmentOutlined style={{ color: '#52c41a' }} />}
                            onClick={() => setMapVisit(record)}
                        >
                            {record.location_name || 'View location'}
                        </Button>
                    </Tooltip>
                ) : (
                    <span style={{ color: '#ccc' }}>—</span>
                )
            ),
        },
        canVisits
            ? {
                title: 'Action',
                width: 96,
                render: (_, record) => (
                    <Space>
                        <Button
                            size="small"
                            icon={<EditOutlined />}
                            onClick={() => openVisit(record)}
                        />
                        <Button
                            size="small"
                            danger
                            icon={<DeleteOutlined />}
                            onClick={() => deleteVisit(record)}
                        />
                    </Space>
                ),
            }
            : null,
    ].filter(Boolean);

    if (view === 'visit') {
        return (
            <Card
                title={editingVisit ? 'Edit Visit' : 'New Visit'}
                extra={<Button onClick={() => setView('list')}>Cancel</Button>}
            >
                <Form form={visitForm} layout="vertical" onFinish={saveVisit}>
                    <Form.Item
                        name="medical_representative_id"
                        label="MR"
                        rules={[{ required: true }]}
                    >
                        <Select
                            options={mrOptions.map((mr) => ({
                                value: mr.id,
                                label: mr.name,
                            }))}
                        />
                    </Form.Item>

                    <Form.Item name="customer_id" label="Customer">
                        <Select
                            allowClear
                            options={customers.map((customer) => ({
                                value: customer.id,
                                label: customer.name,
                            }))}
                        />
                    </Form.Item>

                    <div className="form-grid">
                        <Form.Item
                            name="visit_date"
                            label="Visit Date"
                            rules={[{ required: true }]}
                        >
                            <SmartDatePicker className="full-width" />
                        </Form.Item>

                        <Form.Item
                            name="status"
                            label="Status"
                            rules={[{ required: true }]}
                        >
                            <Select options={mrVisitStatusOptions} />
                        </Form.Item>
                    </div>

                    <div className="form-grid">
                        <Form.Item name="visit_time" label="Visit Time">
                            <Input type="time" />
                        </Form.Item>

                        <Form.Item name="order_value" label="Order Value">
                            <InputNumber min={0} className="full-width" />
                        </Form.Item>
                    </div>

                    <Form.Item name="location_name" label="Location">
                        <LocationSearch
                            countrycodes="np"
                            placeholder="Search location (city, ward, area, street)"
                            onChange={handleLocationSelect}
                        />
                    </Form.Item>

                    <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
                        <Button
                            icon={<EnvironmentOutlined />}
                            onClick={captureLocation}
                        >
                            Use My Current Location
                        </Button>
                    </div>

                    <Form.Item name="latitude" hidden><Input /></Form.Item>
                    <Form.Item name="longitude" hidden><Input /></Form.Item>

                    <Form.Item name="purpose" label="Purpose">
                        <Input />
                    </Form.Item>

                    <Form.Item name="notes" label="Notes">
                        <Input.TextArea rows={2} />
                    </Form.Item>

                    <Button type="primary" htmlType="submit">
                        Save Visit
                    </Button>
                </Form>
            </Card>
        );
    }

    return (
        <>
            <Card title="Visit Log">
                <div className="table-toolbar table-toolbar-wide">
                    <Input.Search
                        value={visitTable.search}
                        onChange={(event) => visitTable.setSearch(event.target.value)}
                        placeholder="Search MR or customer"
                        allowClear
                    />

                    <Select
                        allowClear
                        placeholder="MR"
                        value={visitTable.filters.medical_representative_id}
                        onChange={(value) => (
                            visitTable.setFilters((current) => ({
                                ...current,
                                medical_representative_id: value,
                            }))
                        )}
                        options={mrOptions.map((mr) => ({
                            value: mr.id,
                            label: mr.name,
                        }))}
                        style={{ minWidth: 160 }}
                    />

                    <Select
                        allowClear
                        placeholder="Status"
                        value={visitTable.filters.status}
                        onChange={(value) => (
                            visitTable.setFilters((current) => ({
                                ...current,
                                status: value,
                            }))
                        )}
                        options={mrVisitStatusOptions}
                    />

                    <SmartDatePicker.RangePicker
                        value={visitRange}
                        onChange={updateVisitRange}
                        placeholder={['Visit from', 'Visit to']}
                    />

                    <Button type="primary" icon={<PlusOutlined />} onClick={() => openVisit(null)}>
                        Add Visit
                    </Button>
                </div>

                <ServerTable table={visitTable} columns={visitColumns} />
            </Card>

            <Drawer
                title={mapVisit ? `Visit Location — ${mapVisit.medical_representative?.name ?? ''}` : ''}
                open={!!mapVisit}
                onClose={() => setMapVisit(null)}
                size="large"
            >
                {mapVisit && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                        {mapVisit.location_name && (
                            <p style={{ margin: 0 }}>
                                <strong>Location:</strong> {mapVisit.location_name}
                            </p>
                        )}

                        {mapVisit.map_embed_url ? (
                            <>
                                <iframe
                                    title="Visit Map"
                                    width="100%"
                                    height="380"
                                    style={{ border: 0, borderRadius: 8 }}
                                    loading="lazy"
                                    src={mapVisit.map_embed_url}
                                />

                                <a
                                    href={mapVisit.map_view_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    style={{ fontSize: 12 }}
                                >
                                    Open in OpenStreetMap
                                </a>
                            </>
                        ) : mapVisit.location_name ? (
                            <a
                                href={`https://www.openstreetmap.org/search?query=${encodeURIComponent(mapVisit.location_name)}`}
                                target="_blank"
                                rel="noreferrer"
                                style={{ fontSize: 12 }}
                            >
                                Search this location in OpenStreetMap
                            </a>
                        ) : null}
                    </div>
                )}
            </Drawer>
        </>
    );
}
