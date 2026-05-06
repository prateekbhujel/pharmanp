import React, { useMemo } from 'react';
import { Table } from 'antd';

const pageSizeOptions = ['10', '15', '20', '25', '50', '100'];

export function ServerTable({ table, columns, rowKey = 'id', serial = true, scroll = { x: 'max-content' }, size = 'middle', sticky = true, ...tableProps }) {
    const resolvedColumns = useMemo(() => {
        const serialColumn = {
            title: 'SN',
            key: '__serial',
            width: 68,
            align: 'center',
            fixed: 'left',
            className: 'table-serial-cell',
            render: (_, __, index) => ((table.pagination.current - 1) * table.pagination.pageSize) + index + 1,
        };

        return serial ? [serialColumn, ...columns] : columns;
    }, [columns, serial, table.pagination.current, table.pagination.pageSize]);

    return (
        <Table
            className="server-table"
            rowKey={rowKey}
            columns={resolvedColumns}
            dataSource={table.rows}
            loading={table.loading}
            size={size}
            tableLayout="auto"
            pagination={{
                current: table.pagination.current,
                pageSize: table.pagination.pageSize,
                total: table.pagination.total,
                showSizeChanger: true,
                pageSizeOptions,
            }}
            onChange={table.handleTableChange}
            scroll={scroll}
            sticky={sticky}
            {...tableProps}
        />
    );
}
