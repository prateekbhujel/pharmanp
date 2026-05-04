import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Select, Spin } from 'antd';
import { EnvironmentOutlined } from '@ant-design/icons';

const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
const DEBOUNCE_MS = 400;
const MIN_QUERY_LENGTH = 3;

/**
 * Location search component powered by OpenStreetMap Nominatim.
 * Replaces manual lat/lng capture with an autocomplete search.
 *
 * Props:
 *  - value: current location name string
 *  - onChange: (locationName, { lat, lon }) => void
 *  - placeholder: placeholder text
 *  - countrycodes: comma-separated ISO country codes to restrict results (e.g. 'np')
 */
export function LocationSearch({ value, onChange, placeholder, countrycodes = 'np', ...rest }) {
    const [options, setOptions] = useState([]);
    const [searching, setSearching] = useState(false);
    const timerRef = useRef(null);

    useEffect(() => {
        return () => clearTimeout(timerRef.current);
    }, []);

    const search = useCallback((query) => {
        clearTimeout(timerRef.current);

        if (!query || query.length < MIN_QUERY_LENGTH) {
            setOptions([]);
            return;
        }

        timerRef.current = setTimeout(async () => {
            setSearching(true);

            try {
                const params = new URLSearchParams({
                    q: query,
                    format: 'json',
                    addressdetails: '1',
                    limit: '8',
                    ...(countrycodes ? { countrycodes } : {}),
                });

                const response = await fetch(`${NOMINATIM_URL}?${params}`, {
                    headers: { 'Accept-Language': 'en' },
                });

                if (!response.ok) {
                    setOptions([]);
                    return;
                }

                const results = await response.json();

                setOptions(
                    results.map((item) => ({
                        value: item.display_name,
                        label: (
                            <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
                                <EnvironmentOutlined style={{ color: '#10b981', marginTop: 3, flexShrink: 0 }} />
                                <div style={{ lineHeight: 1.3 }}>
                                    <div style={{ fontWeight: 500 }}>
                                        {item.address?.road || item.address?.city || item.address?.town || item.name || item.display_name?.split(',')[0]}
                                    </div>
                                    <div style={{ fontSize: 11, color: '#888', whiteSpace: 'normal' }}>
                                        {item.display_name}
                                    </div>
                                </div>
                            </div>
                        ),
                        lat: item.lat,
                        lon: item.lon,
                    })),
                );
            } catch {
                setOptions([]);
            } finally {
                setSearching(false);
            }
        }, DEBOUNCE_MS);
    }, [countrycodes]);

    function handleSelect(selectedValue, option) {
        if (onChange) {
            onChange(selectedValue, { lat: option.lat, lon: option.lon });
        }
    }

    function handleChange(nextValue) {
        if (onChange) {
            onChange(nextValue, null);
        }
    }

    return (
        <Select
            showSearch
            value={value || undefined}
            placeholder={placeholder || 'Search location (city, area, street)'}
            filterOption={false}
            onSearch={search}
            onChange={handleChange}
            onSelect={handleSelect}
            options={options}
            notFoundContent={searching ? <Spin size="small" /> : null}
            allowClear
            {...rest}
        />
    );
}
