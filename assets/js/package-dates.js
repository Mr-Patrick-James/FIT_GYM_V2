'use strict';
// Shared calendar-day rules matching api/package-dates.php. No browser-local date shifts.
(function (root) {
    function durationDays(duration) {
        if (typeof duration !== 'string') return null;
        const value = duration.trim().toLowerCase();
        const units = { day: 1, week: 7, month: 30, year: 365 };
        const numeric = value.match(/^([1-9]\d*)\s*(day|week|month|year)s?(?:\s*\([^()]*\))?$/);
        const alias = value.match(/^(daily|day|weekly|week|monthly|month|yearly|year|annual|annually)(?:\s+(?:pass|membership))?(?:\s*\([^()]*\))?$/);
        let days = null;
        if (numeric) days = Number(numeric[1]) * units[numeric[2]];
        else if (/^[1-9]\d*$/.test(value)) days = Number(value);
        else if (alias) days = { daily: 1, day: 1, weekly: 7, week: 7, monthly: 30, month: 30, yearly: 365, year: 365, annual: 365, annually: 365 }[alias[1]];
        return Number.isSafeInteger(days) && days >= 1 && days <= 36500 ? days : null;
    }

    function calendarDate(value) {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
        const [year, month, day] = value.split('-').map(Number);
        if (year < 1000) return null;
        const date = new Date(Date.UTC(year, month - 1, day));
        return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value ? date : null;
    }

    function range(startDate, duration) {
        const start = calendarDate(startDate);
        const days = durationDays(duration);
        if (!start || days === null) return null;
        const end = new Date(start.getTime() + (days - 1) * 86400000);
        const exclusiveEnd = new Date(start.getTime() + days * 86400000);
        if (exclusiveEnd.getUTCFullYear() > 9999) return null;
        const endDate = end.toISOString().slice(0, 10);
        return { booking_date: startDate, duration_days: days, end_date: endDate,
            expires_at: `${endDate} 23:59:59`, calendar_end: exclusiveEnd.toISOString().slice(0, 10) };
    }

    function today(instant = new Date()) {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'
        }).formatToParts(instant);
        const part = type => parts.find(p => p.type === type).value;
        return `${part('year')}-${part('month')}-${part('day')}`;
    }

    function parseGymTimestamp(value) {
        if (value instanceof Date) return Number.isFinite(value.getTime()) ? new Date(value) : null;
        if (typeof value !== 'string') return null;
        const text = value.trim();
        const local = text.match(/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?$/);
        if (local) {
            if (!calendarDate(local[1]) || Number(local[2] || 0) > 23 || Number(local[3] || 0) > 59 || Number(local[4] || 0) > 59) return null;
            return new Date(`${local[1]}T${local[2] || '00'}:${local[3] || '00'}:${local[4] || '00'}+08:00`);
        }
        const iso = text.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d+)?(?:Z|[+-](\d{2}):(\d{2}))$/);
        if (!iso || !calendarDate(iso[1]) || Number(iso[2]) > 23 || Number(iso[3]) > 59 || Number(iso[4]) > 59
            || Number(iso[5] || 0) > 23 || Number(iso[6] || 0) > 59) return null;
        const date = new Date(text);
        return Number.isFinite(date.getTime()) ? date : null;
    }

    function expiry(booking) {
        if (booking.expires_at) return parseGymTimestamp(booking.expires_at);
        const start = String(booking.booking_date || booking.date || '').slice(0, 10);
        const dates = range(start, booking.duration);
        return dates ? parseGymTimestamp(dates.expires_at) : null;
    }

    function displayDate(value) {
        const date = parseGymTimestamp(value);
        return date ? new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', year: 'numeric' }).format(date) : '—';
    }

    function calendarEnd(booking) {
        const expires = expiry(booking);
        if (!expires) return null;
        const endDay = calendarDate(today(expires));
        endDay.setUTCDate(endDay.getUTCDate() + 1);
        return endDay.getUTCFullYear() <= 9999 ? endDay.toISOString().slice(0, 10) : null;
    }

    function previewText(startDate, duration) {
        const dates = range(startDate, duration);
        if (!dates) return 'Choose a package and valid start date to see its end date.';
        return `${displayDate(dates.booking_date)} – ${displayDate(dates.end_date)} (${dates.duration_days} ${dates.duration_days === 1 ? 'day' : 'days'}). Ends 11:59 PM Philippine time.`;
    }

    root.PackageDates = { durationDays, calendarDate, range, today, parseGymTimestamp, expiry, displayDate, calendarEnd, previewText };
})(globalThis);
