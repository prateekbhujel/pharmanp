import React, { useMemo, useState } from 'react';
import { Button, Card, Dropdown, Input, Typography } from 'antd';
import { CalendarOutlined, LeftOutlined, RightOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import {
    adToBs,
    bsData,
    bsToAd,
    formatBsDate,
    nepaliDaysShort,
    nepaliMonthsFull,
} from '../utils/calendar';

export function NepaliDatePicker({
    value,
    onChange,
    placeholder = 'Select Nepali Date',
    className,
    disabled = false,
}) {
    const [open, setOpen] = useState(false);
    const [viewDate, setViewDate] = useState(() => {
        const bs = adToBs(value ? dayjs(value) : dayjs()) || adToBs(dayjs());

        return {
            year: bs?.year || 2083,
            month: bs?.month || 0,
        };
    });

    const selectedBs = value ? adToBs(dayjs(value)) : null;

    function handlePrevMonth() {
        setViewDate((prev) => {
            let month = prev.month - 1;
            let year = prev.year;

            if (month < 0) {
                month = 11;
                year -= 1;
            }

            return { year, month };
        });
    }

    function handleNextMonth() {
        setViewDate((prev) => {
            let month = prev.month + 1;
            let year = prev.year;

            if (month > 11) {
                month = 0;
                year += 1;
            }

            return { year, month };
        });
    }

    const calendarGrid = useMemo(() => {
        const daysInMonth = (bsData[viewDate.year] || [])[viewDate.month] || 30;
        const startAd = bsToAd(viewDate.year, viewDate.month, 1);
        const startDayOfWeek = startAd.day();
        const grid = [];

        for (let index = 0; index < startDayOfWeek; index += 1) {
            grid.push(null);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            grid.push(day);
        }

        return grid;
    }, [viewDate]);

    const dropdownContent = (
        <Card className="nepali-datepicker-card" size="small" style={{ width: 280, boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}>
            <div className="calendar-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <Button type="text" size="small" icon={<LeftOutlined />} onClick={handlePrevMonth} />
                <Typography.Text strong>{nepaliMonthsFull[viewDate.month]} {viewDate.year}</Typography.Text>
                <Button type="text" size="small" icon={<RightOutlined />} onClick={handleNextMonth} />
            </div>

            <div className="calendar-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 4 }}>
                {nepaliDaysShort.map((dayLabel) => (
                    <div key={dayLabel} style={{ textAlign: 'center', fontSize: 12, color: '#94a3b8', padding: '4px 0' }}>{dayLabel}</div>
                ))}
                {calendarGrid.map((day, idx) => {
                    if (day === null) {
                        return <div key={`empty-${idx}`} />;
                    }

                    const isSelected = selectedBs?.year === viewDate.year && selectedBs?.month === viewDate.month && selectedBs?.day === day;

                    return (
                        <div
                            key={day}
                            onClick={() => {
                                const ad = bsToAd(viewDate.year, viewDate.month, day);
                                onChange?.(ad);
                                setOpen(false);
                            }}
                            className={`calendar-day ${isSelected ? 'selected' : ''}`}
                            style={{
                                textAlign: 'center',
                                padding: '6px 0',
                                borderRadius: 4,
                                cursor: 'pointer',
                                fontSize: 13,
                                background: isSelected ? '#1e3a8a' : 'transparent',
                                color: isSelected ? '#fff' : '#1e293b',
                                transition: 'all 0.2s',
                            }}
                        >
                            {day}
                        </div>
                    );
                })}
            </div>
            <div style={{ marginTop: 12, textAlign: 'center' }}>
                <Button
                    type="link"
                    size="small"
                    onClick={() => {
                        onChange?.(dayjs());
                        setOpen(false);
                    }}
                >
                    Today
                </Button>
            </div>
        </Card>
    );

    const displayValue = selectedBs ? formatBsDate(value, { style: 'compact' }) : '';

    return (
        <Dropdown
            open={disabled ? false : open}
            onOpenChange={(nextOpen) => !disabled && setOpen(nextOpen)}
            dropdownRender={() => dropdownContent}
            trigger={['click']}
            placement="bottomLeft"
        >
            <Input
                className={`full-width ${className || ''}`}
                placeholder={placeholder}
                value={displayValue}
                readOnly
                disabled={disabled}
                suffix={<CalendarOutlined style={{ color: '#94a3b8' }} />}
                style={{ cursor: disabled ? 'not-allowed' : 'pointer' }}
            />
        </Dropdown>
    );
}
