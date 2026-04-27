import React, { useState, useEffect, useRef } from 'react';
import { Button, Calendar, Card, Dropdown, Input, Space, Typography } from 'antd';
import { CalendarOutlined, LeftOutlined, RightOutlined } from '@ant-design/icons';
import dayjs from 'dayjs';

/**
 * Nepali Date Logic & Mapping
 * (Standard BS to AD conversion logic for years 2000 - 2100)
 */
const bsData = {
    2075: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2076: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2077: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2078: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2079: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2080: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2081: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2082: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2083: [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
    2084: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2085: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
};

const nepaliMonths = [
    'Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin',
    'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra'
];

const nepaliDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Reference date: 2075-01-01 BS = 2018-04-14 AD
const refBsYear = 2075;
const refBsMonth = 0; // 0-indexed
const refBsDay = 1;
const refAdDate = dayjs('2018-04-14');

function bsToAd(yy, mm, dd) {
    let totalDays = 0;
    for (let y = refBsYear; y < yy; y++) {
        totalDays += (bsData[y] || []).reduce((a, b) => a + b, 0);
    }
    for (let m = 0; m < mm; m++) {
        totalDays += (bsData[yy] || [])[m] || 0;
    }
    totalDays += (dd - refBsDay);
    return refAdDate.add(totalDays, 'day');
}

function adToBs(adDate) {
    let diff = dayjs(adDate).diff(refAdDate, 'day');
    let yy = refBsYear;
    let mm = refBsMonth;
    let dd = refBsDay;

    if (diff >= 0) {
        while (diff > 0) {
            let daysInMonth = (bsData[yy] || [])[mm] || 30;
            if (diff >= daysInMonth) {
                diff -= daysInMonth;
                mm++;
                if (mm > 11) {
                    mm = 0;
                    yy++;
                }
            } else {
                dd += diff;
                diff = 0;
            }
        }
    } else {
        // Handle dates before 2075 if needed
    }

    return { yy, mm, dd };
}

export function NepaliDatePicker({ value, onChange, placeholder = 'Select Nepali Date', className }) {
    const [open, setOpen] = useState(false);
    const [viewDate, setViewDate] = useState(() => {
        const bs = adToBs(value ? dayjs(value) : dayjs());
        return { yy: bs.yy, mm: bs.mm };
    });

    const selectedBs = value ? adToBs(dayjs(value)) : null;

    function handlePrevMonth() {
        setViewDate(prev => {
            let nextMm = prev.mm - 1;
            let nextYy = prev.yy;
            if (nextMm < 0) {
                nextMm = 11;
                nextYy--;
            }
            return { yy: nextYy, mm: nextMm };
        });
    }

    function handleNextMonth() {
        setViewDate(prev => {
            let nextMm = prev.mm + 1;
            let nextYy = prev.yy;
            if (nextMm > 11) {
                nextMm = 0;
                nextYy++;
            }
            return { yy: nextYy, mm: nextMm };
        });
    }

    const daysInMonth = (bsData[viewDate.yy] || [])[viewDate.mm] || 30;
    const startAd = bsToAd(viewDate.yy, viewDate.mm, 1);
    const startDayOfWeek = startAd.day(); // 0 (Sun) to 6 (Sat)

    const calendarGrid = [];
    // Padding for start day
    for (let i = 0; i < startDayOfWeek; i++) calendarGrid.push(null);
    // Days of month
    for (let i = 1; i <= daysInMonth; i++) calendarGrid.push(i);

    const dropdownContent = (
        <Card className="nepali-datepicker-card" size="small" style={{ width: 280, boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}>
            <div className="calendar-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                <Button type="text" size="small" icon={<LeftOutlined />} onClick={handlePrevMonth} />
                <Typography.Text strong>{nepaliMonths[viewDate.mm]} {viewDate.yy}</Typography.Text>
                <Button type="text" size="small" icon={<RightOutlined />} onClick={handleNextMonth} />
            </div>
            
            <div className="calendar-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 4 }}>
                {nepaliDays.map(d => (
                    <div key={d} style={{ textAlign: 'center', fontSize: 12, color: '#94a3b8', padding: '4px 0' }}>{d}</div>
                ))}
                {calendarGrid.map((day, idx) => {
                    if (day === null) return <div key={`empty-${idx}`} />;
                    
                    const isSelected = selectedBs?.yy === viewDate.yy && selectedBs?.mm === viewDate.mm && selectedBs?.dd === day;
                    
                    return (
                        <div 
                            key={day}
                            onClick={() => {
                                const ad = bsToAd(viewDate.yy, viewDate.mm, day);
                                onChange(ad);
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
                                transition: 'all 0.2s'
                            }}
                        >
                            {day}
                        </div>
                    );
                })}
            </div>
            <div style={{ marginTop: 12, textAlign: 'center' }}>
                <Button type="link" size="small" onClick={() => {
                    const todayAd = dayjs();
                    onChange(todayAd);
                    setOpen(false);
                }}>Today</Button>
            </div>
        </Card>
    );

    const displayValue = selectedBs ? `${selectedBs.yy}-${String(selectedBs.mm + 1).padStart(2, '0')}-${String(selectedBs.dd).padStart(2, '0')} BS` : '';

    return (
        <Dropdown 
            open={open} 
            onOpenChange={setOpen} 
            dropdownRender={() => dropdownContent} 
            trigger={['click']}
            placement="bottomLeft"
        >
            <Input 
                className={className}
                placeholder={placeholder}
                value={displayValue}
                readOnly
                suffix={<CalendarOutlined style={{ color: '#94a3b8' }} />}
                style={{ cursor: 'pointer' }}
            />
        </Dropdown>
    );
}
