import React, { useMemo, useState } from 'react';
import { Button, Card, Dropdown, Input } from 'antd';
import { CalendarOutlined, CloseCircleOutlined, LeftOutlined, RightOutlined, SwapRightOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';
import {
    adToBs,
    bsData,
    bsToAd,
    formatBsDate,
    isNepaliHolidayDate,
    nepaliDaysShort,
    nepaliMonthsFull,
} from '../utils/calendar';

function normalizeDate(value) {
    const date = dayjs(value);

    return date.isValid() ? date.startOf('day') : null;
}

function sameDay(first, second) {
    return Boolean(first && second && first.isSame(second, 'day'));
}

function isBetweenDay(date, start, end) {
    return Boolean(start && end && date.isAfter(start, 'day') && date.isBefore(end, 'day'));
}

function addBsMonths(viewDate, offset) {
    let year = viewDate.year;
    let month = viewDate.month + offset;

    while (month < 0) {
        month += 12;
        year -= 1;
    }

    while (month > 11) {
        month -= 12;
        year += 1;
    }

    if (!bsData[year]) {
        return viewDate;
    }

    return { year, month };
}

function monthGrid(year, month) {
    const daysInMonth = (bsData[year] || [])[month] || 30;
    const startAd = bsToAd(year, month, 1);
    const grid = [];

    for (let index = 0; index < startAd.day(); index += 1) {
        grid.push(null);
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        grid.push(day);
    }

    return grid;
}

function MonthPanel({
    viewDate,
    start,
    end,
    onSelect,
    showPrev,
    showNext,
    onPrev,
    onNext,
}) {
    const grid = useMemo(() => monthGrid(viewDate.year, viewDate.month), [viewDate]);

    return (
        <div className="nepali-range-month">
            <div className="nepali-range-month-header">
                {showPrev ? <Button type="text" size="small" icon={<LeftOutlined />} onClick={onPrev} /> : <span />}
                <div className="nepali-range-month-title">
                    {nepaliMonthsFull[viewDate.month]} {viewDate.year}
                </div>
                {showNext ? <Button type="text" size="small" icon={<RightOutlined />} onClick={onNext} /> : <span />}
            </div>

            <div className="nepali-range-grid">
                {nepaliDaysShort.map((dayLabel, index) => (
                    <div
                        key={dayLabel}
                        className={`nepali-range-weekday ${index === 6 ? 'nepali-range-weekday-holiday' : ''}`}
                    >
                        {dayLabel}
                    </div>
                ))}

                {grid.map((day, index) => {
                    if (day === null) {
                        return <div key={`empty-${index}`} className="nepali-range-empty-day" />;
                    }

                    const adDate = bsToAd(viewDate.year, viewDate.month, day).startOf('day');
                    const isStart = sameDay(adDate, start);
                    const isEnd = sameDay(adDate, end);
                    const isInRange = isBetweenDay(adDate, start, end);
                    const isToday = adDate.isSame(dayjs(), 'day');
                    const isHoliday = isNepaliHolidayDate(viewDate.year, viewDate.month, day);
                    const isWeekStart = adDate.day() === 0;
                    const isWeekEnd = adDate.day() === 6;
                    const className = [
                        'nepali-range-day',
                        isStart ? 'nepali-range-day-start' : '',
                        isEnd ? 'nepali-range-day-end' : '',
                        isInRange ? 'nepali-range-day-in-range' : '',
                        isWeekStart ? 'nepali-range-day-week-start' : '',
                        isWeekEnd ? 'nepali-range-day-week-end' : '',
                        isToday ? 'nepali-range-day-today' : '',
                        isHoliday ? 'nepali-range-day-holiday' : '',
                    ].filter(Boolean).join(' ');

                    return (
                        <button
                            key={day}
                            type="button"
                            className={className}
                            onClick={() => onSelect(adDate)}
                            title={isHoliday ? 'Weekly public holiday' : undefined}
                        >
                            <span className="nepali-range-bs-day">{day}</span>
                            <span className="nepali-range-ad-day">{adDate.format('MMM D')}</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

export function NepaliDateRangePicker({
    value,
    onChange,
    placeholder = ['Start date', 'End date'],
    className,
    allowClear = true,
    disabled = false,
}) {
    const [open, setOpen] = useState(false);
    const [selecting, setSelecting] = useState('start');
    const [startValue, endValue] = Array.isArray(value) ? value : [];
    const start = normalizeDate(startValue);
    const end = normalizeDate(endValue);
    const initialBs = adToBs(start || dayjs()) || adToBs(dayjs());
    const [viewDate, setViewDate] = useState({
        year: initialBs?.year || 2083,
        month: initialBs?.month || 0,
    });
    const [draftRange, setDraftRange] = useState([start, end]);
    const [draftStart, draftEnd] = draftRange;
    const activeStart = open ? draftStart : start;
    const activeEnd = open ? draftEnd : end;
    const nextViewDate = addBsMonths(viewDate, 1);
    const hasValue = Boolean(start || end);
    const displayStart = start ? formatBsDate(start, { style: 'compact', includeEra: false }) : '';
    const displayEnd = end ? formatBsDate(end, { style: 'compact', includeEra: false }) : '';
    const panelClassName = [
        'nepali-range-picker',
        className || '',
        disabled ? 'nepali-range-picker-disabled' : '',
        open ? 'nepali-range-picker-open' : '',
    ].filter(Boolean).join(' ');

    function emit(nextStart, nextEnd) {
        if (!nextStart && !nextEnd) {
            onChange?.([]);
            return;
        }

        onChange?.([nextStart, nextEnd]);
    }

    function handleSelect(adDate) {
        if (!draftStart || draftEnd || selecting === 'start') {
            setDraftRange([adDate, null]);
            setSelecting('end');
            return;
        }

        const nextStart = adDate.isBefore(draftStart, 'day') ? adDate : draftStart;
        const nextEnd = adDate.isBefore(draftStart, 'day') ? draftStart : adDate;

        setDraftRange([nextStart, nextEnd]);
        emit(nextStart, nextEnd);

        setSelecting('start');
        setOpen(false);
    }

    function clearRange(event) {
        event.stopPropagation();
        setDraftRange([null, null]);
        emit(null, null);
        setSelecting('start');
    }

    const dropdownContent = (
        <Card className="nepali-range-card" size="small">
            <div className="nepali-range-card-body">
                <MonthPanel
                    viewDate={viewDate}
                    start={activeStart}
                    end={activeEnd}
                    onSelect={handleSelect}
                    showPrev
                    onPrev={() => setViewDate((current) => addBsMonths(current, -1))}
                />
                <MonthPanel
                    viewDate={nextViewDate}
                    start={activeStart}
                    end={activeEnd}
                    onSelect={handleSelect}
                    showNext
                    onNext={() => setViewDate((current) => addBsMonths(current, 1))}
                />
            </div>
            <div className="nepali-range-footer">
                <span>{selecting === 'end' && draftStart ? 'Select end date' : 'Select start date'}</span>
                <Button
                    type="link"
                    size="small"
                    onClick={() => {
                        const today = dayjs().startOf('day');
                        setDraftRange([today, today]);
                        emit(today, today);
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
            onOpenChange={(nextOpen) => {
                if (disabled) {
                    return;
                }

                if (nextOpen) {
                    setDraftRange([start, end]);
                    setSelecting('start');
                }

                setOpen(nextOpen);
            }}
            dropdownRender={() => dropdownContent}
            trigger={['click']}
            placement="bottomLeft"
        >
            <div className={panelClassName} role="button" tabIndex={disabled ? -1 : 0}>
                <CalendarOutlined className="nepali-range-icon" />
                <Input
                    value={displayStart}
                    placeholder={placeholder?.[0] || 'Start date'}
                    readOnly
                    disabled={disabled}
                    variant="borderless"
                    className="nepali-range-input"
                />
                <SwapRightOutlined className="nepali-range-separator" />
                <Input
                    value={displayEnd}
                    placeholder={placeholder?.[1] || 'End date'}
                    readOnly
                    disabled={disabled}
                    variant="borderless"
                    className="nepali-range-input"
                />
                {allowClear && hasValue && !disabled ? (
                    <Button
                        type="text"
                        size="small"
                        icon={<CloseCircleOutlined />}
                        onClick={clearRange}
                        aria-label="Clear date range"
                    />
                ) : null}
            </div>
        </Dropdown>
    );
}
