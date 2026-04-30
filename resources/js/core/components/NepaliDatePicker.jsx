import React, { useEffect, useMemo, useState } from 'react';
import { Button, Card, Dropdown, Input, Select } from 'antd';
import { CalendarOutlined, LeftOutlined, RightOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import {
    adToBs,
    bsData,
    bsToAd,
    formatBsDate,
    isNepaliHolidayDate,
    nepaliDaysShort,
    nepaliMonthsFull,
    parseCalendarInput,
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
    const displayValue = selectedBs ? formatBsDate(value, { style: 'compact' }) : '';
    const [inputValue, setInputValue] = useState(displayValue);
    const supportedYears = useMemo(() => Object.keys(bsData).map(Number), []);
    const monthOptions = useMemo(
        () => nepaliMonthsFull.map((label, index) => ({ value: index, label })),
        [],
    );
    const yearOptions = useMemo(
        () => supportedYears.map((year) => ({ value: year, label: String(year) })),
        [supportedYears],
    );

    useEffect(() => {
        setInputValue(displayValue);
    }, [displayValue]);

    function commitTypedDate() {
        if (!inputValue) {
            onChange?.(null);
            return;
        }

        const parsed = parseCalendarInput(inputValue, 'bs');

        if (!parsed) {
            setInputValue(displayValue);
            return;
        }

        const bs = adToBs(parsed);
        if (bs) {
            setViewDate({ year: bs.year, month: bs.month });
        }

        onChange?.(parsed);
        setOpen(false);
    }

    function handlePrevMonth() {
        setViewDate((prev) => {
            let month = prev.month - 1;
            let year = prev.year;

            if (month < 0) {
                month = 11;
                year -= 1;
            }

            if (!bsData[year]) {
                return prev;
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

            if (!bsData[year]) {
                return prev;
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

    const viewAdRange = useMemo(() => {
        const daysInMonth = (bsData[viewDate.year] || [])[viewDate.month] || 30;
        const startAd = bsToAd(viewDate.year, viewDate.month, 1);
        const endAd = bsToAd(viewDate.year, viewDate.month, daysInMonth);

        return `${startAd.format('MMM D')} - ${endAd.format('MMM D, YYYY')}`;
    }, [viewDate]);

    const dropdownContent = (
        <Card className="nepali-datepicker-card" size="small">
            <div className="nepali-calendar-header">
                <Button type="text" size="small" icon={<LeftOutlined />} onClick={handlePrevMonth} />
                <Select
                    size="small"
                    value={viewDate.month}
                    options={monthOptions}
                    onChange={(month) => setViewDate((current) => ({ ...current, month }))}
                />
                <Select
                    size="small"
                    showSearch
                    optionFilterProp="label"
                    value={viewDate.year}
                    options={yearOptions}
                    onChange={(year) => setViewDate((current) => ({ ...current, year }))}
                />
                <Button type="text" size="small" icon={<RightOutlined />} onClick={handleNextMonth} />
            </div>
            <div className="nepali-calendar-subtitle">{viewAdRange}</div>

            <div className="nepali-calendar-grid">
                {nepaliDaysShort.map((dayLabel, index) => (
                    <div
                        key={dayLabel}
                        className={`nepali-calendar-weekday ${index === 6 ? 'nepali-calendar-weekday-holiday' : ''}`}
                    >
                        {dayLabel}
                    </div>
                ))}
                {calendarGrid.map((day, idx) => {
                    if (day === null) {
                        return <div key={`empty-${idx}`} className="nepali-calendar-empty-day" />;
                    }

                    const isSelected = selectedBs?.year === viewDate.year && selectedBs?.month === viewDate.month && selectedBs?.day === day;
                    const isHoliday = isNepaliHolidayDate(viewDate.year, viewDate.month, day);
                    const adDate = bsToAd(viewDate.year, viewDate.month, day);
                    const isToday = adDate.isSame(dayjs(), 'day');
                    const dayClassName = [
                        'nepali-calendar-day',
                        isSelected ? 'nepali-calendar-day-selected' : '',
                        isHoliday ? 'nepali-calendar-day-holiday' : '',
                        isToday ? 'nepali-calendar-day-today' : '',
                    ].filter(Boolean).join(' ');

                    return (
                        <button
                            type="button"
                            key={day}
                            onClick={() => {
                                onChange?.(adDate);
                                setInputValue(formatBsDate(adDate, { style: 'compact' }));
                                setOpen(false);
                            }}
                            className={dayClassName}
                            title={isHoliday ? 'Weekly public holiday' : undefined}
                        >
                            <span className="nepali-calendar-bs-day">{day}</span>
                            <span className="nepali-calendar-ad-day">{adDate.format('MMM D')}</span>
                        </button>
                    );
                })}
            </div>
            <div className="nepali-calendar-footer">
                <Button
                    type="link"
                    size="small"
                    onClick={() => {
                        onChange?.(dayjs());
                        setInputValue(formatBsDate(dayjs(), { style: 'compact' }));
                        setOpen(false);
                    }}
                >
                    Today
                </Button>
            </div>
        </Card>
    );

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
                value={inputValue}
                onChange={(event) => setInputValue(event.target.value)}
                onBlur={commitTypedDate}
                onPressEnter={commitTypedDate}
                disabled={disabled}
                suffix={<CalendarOutlined style={{ color: '#94a3b8' }} />}
                style={{ cursor: disabled ? 'not-allowed' : 'pointer' }}
            />
        </Dropdown>
    );
}
