import React from 'react';
import { Table } from 'antd';

export function ServerTable({ table, columns, rowKey = 'id' }) {
    return (
        <Table
            className="server-table"
            rowKey={rowKey}
            columns={columns}
            dataSource={table.rows}
            loading={table.loading}
            size="middle"
            tableLayout="auto"
            pagination={{
                current: table.pagination.current,
                pageSize: table.pagination.pageSize,
                total: table.pagination.total,
                showSizeChanger: true,
                pageSizeOptions: [15, 25, 50, 100],
            }}
            onChange={table.handleTableChange}
            scroll={{ x: 'max-content' }}
        />
    );
}
