import React from 'react';
import { Button, Empty, Space } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';

export function TransactionLineItems({
    rows,
    columns,
    errors = {},
    addLabel = 'Add Item',
    minRows = 1,
    onAdd,
    onRemove,
    rowKey = (_, index) => index,
    summary = [],
    actions = null,
}) {
    const hasRows = rows.length > 0;

    return (
        <div className="transaction-lines">
            {hasRows ? (
                <div className="transaction-lines-scroll">
                    <table className="transaction-lines-table">
                        <thead>
                            <tr>
                                <th className="line-number-cell">S.No</th>
                                {columns.map((column) => (
                                    <th key={column.key} style={{ width: column.width }}>{column.title}</th>
                                ))}
                                {onRemove && <th className="line-action-cell">Action</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => {
                                const key = rowKey(row, index);
                                const rowErrors = errors[index] || [];

                                return (
                                    <React.Fragment key={key}>
                                        <tr>
                                            <td className="line-number-cell" data-label="S.No">{index + 1}</td>
                                            {columns.map((column) => (
                                                <td key={column.key} data-label={column.title} className={column.className}>
                                                    {column.render(row, index)}
                                                </td>
                                            ))}
                                            {onRemove && (
                                                <td className="line-action-cell" data-label="Action">
                                                    <Button
                                                        aria-label="Remove row"
                                                        danger
                                                        icon={<DeleteOutlined />}
                                                        disabled={rows.length <= minRows}
                                                        onClick={() => onRemove(index)}
                                                    />
                                                </td>
                                            )}
                                        </tr>
                                        {rowErrors.length > 0 && (
                                            <tr className="line-error-row">
                                                <td colSpan={columns.length + (onRemove ? 2 : 1)}>
                                                    {rowErrors.map((message) => <span key={message}>{message}</span>)}
                                                </td>
                                            </tr>
                                        )}
                                    </React.Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            ) : (
                <Empty description="No items added" />
            )}

            <div className="transaction-line-footer">
                <Button icon={<PlusOutlined />} onClick={onAdd}>{addLabel}</Button>
                <Space className="transaction-actions" wrap>{actions}</Space>
            </div>

            {summary.length > 0 && (
                <div className="transaction-summary-panel">
                    {summary.map((item) => (
                        <div key={item.label} className={item.strong ? 'transaction-summary-total' : ''}>
                            <span>{item.label}</span>
                            <strong>{item.value}</strong>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
