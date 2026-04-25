import React from 'react';
import { Table } from 'antd';

export function ServerTable({ table, columns, rowKey = 'id' }) {
    return (
        <Table
            rowKey={rowKey}
            columns={columns}
            dataSource={table.rows}
            loading={table.loading}
            pagination={{
                current: table.pagination.current,
                pageSize: table.pagination.pageSize,
                total: table.pagination.total,
                showSizeChanger: true,
                pageSizeOptions: [15, 25, 50, 100],
            }}
            onChange={table.handleTableChange}
            scroll={{ x: 1100 }}
        />
    );
}
