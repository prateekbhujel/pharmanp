import { useCallback, useEffect, useMemo, useState } from 'react';
import { App } from 'antd';
import { apiData, apiExtra, apiMeta, http } from '../api/http';
import { useDebounce } from './useDebounce';

export function useServerTable({ endpoint, defaultSort = { field: 'updated_at', order: 'desc' }, defaultFilters = {}, enabled = true }) {
    const { notification } = App.useApp();
    const [rows, setRows] = useState([]);
    const [extra, setExtra] = useState({});
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const debouncedSearch = useDebounce(search);
    const [pagination, setPagination] = useState({ current: 1, pageSize: 15, total: 0 });
    const [sort, setSort] = useState(defaultSort);
    const [filters, setFilters] = useState(defaultFilters);

    const params = useMemo(() => ({
        page: pagination.current,
        per_page: pagination.pageSize,
        search: debouncedSearch || undefined,
        sort_field: sort.field,
        sort_order: sort.order,
        ...filters,
    }), [pagination.current, pagination.pageSize, debouncedSearch, sort, filters]);

    const load = useCallback(() => {
        if (!enabled || !endpoint) {
            setLoading(false);
            return Promise.resolve();
        }

        setLoading(true);
        return http.get(endpoint, { params })
            .then(({ data }) => {
                const meta = apiMeta(data);
                setRows(apiData(data, []));
                setExtra(apiExtra(data));
                setPagination((current) => ({
                    ...current,
                    total: meta.total || 0,
                    current: meta.current_page || current.current,
                    pageSize: meta.per_page || current.pageSize,
                }));
            })
            .catch((error) => {
                notification.error({
                    message: 'Table request failed',
                    description: error?.response?.data?.message || error.message,
                });
            })
            .finally(() => setLoading(false));
    }, [enabled, endpoint, params, notification]);

    useEffect(() => {
        if (enabled) {
            load();
        }
    }, [enabled, load]);

    function handleTableChange(nextPagination, _filters, sorter) {
        setPagination({
            current: nextPagination.current,
            pageSize: nextPagination.pageSize,
            total: nextPagination.total,
        });

        if (sorter?.field) {
            setSort({
                field: sorter.field,
                order: sorter.order === 'ascend' ? 'asc' : 'desc',
            });
        }
    }

    return {
        rows,
        loading,
        search,
        setSearch,
        pagination,
        extra,
        filters,
        setFilters,
        reload: load,
        handleTableChange,
    };
}
