'use strict';
// Offline fixtures: node test-package-user-ui.js (also run with different TZ values).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = __dirname;
const dashboardSource = fs.readFileSync(path.join(root, 'assets/js/user-dashboard.js'), 'utf8');
const viewSource = fs.readFileSync(path.join(root, 'views/user/dashboard.php'), 'utf8');
let now = '2026-10-16T15:59:59Z';
class FixedDate extends Date {
    constructor(...args) { super(...(args.length ? args : [now])); }
    static now() { return new Date(now).getTime(); }
}

function element(id = '') {
    const listeners = {};
    const el = {
        id, value: '', textContent: '', style: {}, dataset: {}, checked: false,
        children: [], classList: { add() {}, remove() {}, contains() { return false; } },
        addEventListener(type, callback) { (listeners[type] ||= []).push(callback); },
        dispatch(type) { for (const callback of listeners[type] || []) callback({ target: el }); },
        appendChild(child) {
            this.children.push(child);
            if (child.selected) this.value = child.value;
        },
        querySelectorAll() { return []; }
    };
    Object.defineProperty(el, 'innerHTML', {
        set(value) { this.html = value; this.children = []; this.value = ''; },
        get() { return this.html || ''; }
    });
    Object.defineProperty(el, 'selectedOptions', {
        get() { return this.children.filter(child => child.value === this.value); }
    });
    return el;
}
const elements = {};
for (const prefix of ['booking', 'editBooking']) {
    for (const suffix of ['Package', 'Date', 'EndDate', 'DurationDays', 'DateNotice', 'Form', 'Modal', 'Contact', 'Notes']) {
        elements[prefix + suffix] = element(prefix + suffix);
    }
    elements[prefix + 'Form'].reset = function() {
        this.dispatch('reset');
        for (const suffix of ['Package', 'Date', 'EndDate', 'DurationDays', 'Contact', 'Notes']) {
            elements[prefix + suffix].value = '';
        }
    };
}
for (const id of ['fileUploadArea', 'bookingDetailsModal', 'isStudent', 'studentSection', 'studentCheckboxRow', 'studentAutoNotice', 'bookingPriceSummary', 'bookingPriceLabel', 'bookingStudentPrice', 'bookingStudentNotice', 'modalCalendar', 'userCoachCalendar']) {
    elements[id] = element(id);
}
const calendarConfigs = [];
const document = {
    getElementById: id => elements[id] || null,
    createElement: () => element(),
    addEventListener() {},
    querySelectorAll: () => [],
    head: { appendChild() {} },
    body: { style: {} }
};
const sandbox = vm.createContext({
    console, document, Date: FixedDate, Intl, queueMicrotask,
    window: { innerWidth: 1200 },
    setTimeout() {}, setInterval() {},
    fetch() { throw new Error('Unexpected request: these fixtures must remain offline'); },
    FullCalendar: { Calendar: class {
        constructor(el, config) { calendarConfigs.push(config); }
        render() {} destroy() {} updateSize() {}
    } }
});
vm.runInContext(fs.readFileSync(path.join(root, 'assets/js/package-dates.js'), 'utf8'), sandbox);
vm.runInContext(dashboardSource, sandbox);
const run = source => vm.runInContext(source, sandbox);
const dates = sandbox.PackageDates;

async function main() {
    for (const [duration, days, end] of [
        ['Daily', 1, '2026-10-16'], ['Weekly', 7, '2026-10-22'],
        ['2 Weeks', 14, '2026-10-29'], ['1 Month', 30, '2026-11-14'],
        ['1 Year', 365, '2027-10-15']
    ]) {
        assert.equal(sandbox.parseDurationToDays(duration), days);
        const booking = { booking_date: '2026-10-16', duration, status: 'verified' };
        assert.equal(dates.today(dates.expiry(booking)), end);
        assert.equal(dates.expiry(booking).toISOString(), `${end}T15:59:59.000Z`);
        const milestones = sandbox.getPackageMilestoneEvents(booking);
        assert.equal(milestones[0].start, end);
        assert.equal(milestones[1].start, '2026-10-16');
        assert.equal(milestones[1].end, dates.range('2026-10-16', duration).calendar_end);
    }
    assert.equal(dates.range('2024-02-28', 'Weekly').end_date, '2024-03-05');
    assert.equal(dates.range('2026-12-31', 'Weekly').end_date, '2027-01-06');
    for (const duration of ['unknown', '2 Fortnights', '0 Days', '', null]) {
        assert.equal(sandbox.parseDurationToDays(duration), null);
        const booking = { booking_date: '2026-10-16', duration, status: 'verified' };
        assert.equal(dates.expiry(booking), null);
        assert.equal(sandbox.isBookingActive(booking), false);
        assert.equal(sandbox.canRenewBooking(booking), false);
        assert.equal(sandbox.getPackageMilestoneEvents(booking).length, 0);
    }
    assert.equal(sandbox.getBookingStartDate({ created_at: '2026-10-01 12:00:00' }), null);
    assert.equal(dates.expiry({ created_at: '2026-10-01 12:00:00', duration: 'Weekly' }), null);
    assert.equal(dates.expiry({ booking_date: '2026-02-30', duration: 'Daily' }), null);
    const daily = { booking_date: '2026-10-16', duration: 'Daily', status: 'verified' };
    assert.equal(sandbox.isBookingActive(daily), true, 'Daily remains active at 23:59:59 Manila');
    now = '2026-10-16T16:00:00Z';
    assert.equal(sandbox.isBookingActive(daily), false, 'Daily expires at the next Manila midnight');
    now = '2026-10-16T15:59:59Z';
    assert.equal(sandbox.isBookingActive({ ...daily, status: 'pending' }), false);
    assert.equal(sandbox.isBookingActive({ ...daily, status: 'expired' }), false);

    const stored = { ...daily, duration: 'invalid', expires_at: '2026-10-19 23:59:59', created_at: '2026-10-01 10:00:00' };
    const original = JSON.stringify(stored);
    assert.equal(dates.expiry(stored).toISOString(), '2026-10-19T15:59:59.000Z');
    assert.equal(dates.displayDate(dates.expiry(stored)), 'Oct 19, 2026');
    assert.equal(dates.calendarEnd(stored), '2026-10-20');
    assert.equal(sandbox.getPackageMilestoneEvents(stored)[0].start, '2026-10-19');
    assert.equal(JSON.stringify(stored), original, 'Display calculations do not mutate bookings');
    assert.equal(dates.expiry({ ...daily, expires_at: 'invalid' }), null, 'Do not replace malformed stored expiry with a guess');
    assert.equal(dates.displayDate(null), '—');
    assert.equal(dates.displayDate('2026-10-16T18:00:00Z'), 'Oct 17, 2026');

    run(`packagesData = [
        { name: 'Daily Pass', duration: 'Daily', rawPrice: 200, price: '₱200' },
        { name: 'Student Weekly', duration: 'Weekly', rawPrice: 500, price: '₱500' },
        { name: 'Invalid Pass', duration: 'invalid', rawPrice: 100, price: '₱100' }
    ]; loadPackagesData = async function() {}; showSection = function() {}; showNotification = function() {};`);
    sandbox.setupEventListeners();
    await sandbox.updateBookingPackageSelect();
    elements.bookingPackage.value = 'Daily Pass';
    elements.bookingDate.value = '2026-10-16';
    elements.bookingPackage.onchange();
    assert.equal(elements.bookingEndDate.value, '2026-10-16');
    assert.equal(elements.bookingDurationDays.value, '1');
    assert.match(elements.bookingDateNotice.textContent, /23:59:59.*Asia\/Manila/);
    assert.equal(elements.bookingPriceLabel.textContent, 'Package price: ₱200');
    elements.bookingPackage.value = 'Student Weekly';
    elements.bookingPackage.onchange();
    assert.equal(elements.bookingEndDate.value, '2026-10-22');
    assert.equal(elements.bookingDurationDays.value, '7');
    assert.equal(elements.isStudent.checked, true);
    assert.equal(elements.studentSection.style.display, 'block');
    assert.equal(elements.bookingPriceLabel.textContent, 'Package price: ₱500');
    assert.equal(elements.bookingStudentPrice.style.display, 'none', 'No student discount introduced');
    elements.bookingDate.value = '2026-12-31';
    elements.bookingDate.dispatch('input');
    assert.equal(elements.bookingEndDate.value, '2027-01-06');
    elements.bookingDate.value = '2024-02-28';
    elements.bookingDate.dispatch('change');
    assert.equal(elements.bookingEndDate.value, '2024-03-05');
    elements.bookingPackage.value = 'Invalid Pass';
    elements.bookingPackage.onchange();
    assert.equal(elements.bookingEndDate.value, '');
    assert.equal(elements.bookingDurationDays.value, '');
    assert.match(elements.bookingDateNotice.textContent, /unavailable/);
    elements.bookingPackage.value = '';
    elements.bookingPackage.onchange();
    assert.equal(elements.bookingEndDate.value, '');
    assert.equal(elements.isStudent.checked, false);

    now = '2026-10-16T18:00:00Z'; // Manila is already Oct 17, unlike UTC/US browsers.
    sandbox.closeBookingModal();
    sandbox.openBookingModal();
    assert.equal(elements.bookingDate.value, '2026-10-17');
    await sandbox.selectPackageForBooking('Student Weekly');
    assert.equal(elements.bookingEndDate.value, '2026-10-23');
    assert.equal(elements.bookingDurationDays.value, '7');
    assert.equal(elements.isStudent.checked, true);
    sandbox.closeBookingModal();
    assert.equal(elements.bookingEndDate.value, '');
    assert.equal(elements.bookingDurationDays.value, '');
    assert.equal(elements.bookingPriceSummary.style.display, 'none');
    elements.bookingEndDate.value = 'stale';
    elements.bookingForm.reset();
    await Promise.resolve();
    assert.equal(elements.bookingEndDate.value, '', 'Native form reset refreshes the preview');

    run(`userBookings = [{ id: 42, status: 'pending', package_name: 'Student Weekly', booking_date: '2026-10-16', created_at: '2026-10-01 12:00:00', duration: 'Weekly' }];`);
    const beforeEdit = run('JSON.stringify(userBookings)');
    sandbox.openEditBookingModal(42);
    assert.equal(elements.editBookingDate.value, '2026-10-16');
    assert.equal(elements.editBookingEndDate.value, '2026-10-22');
    elements.editBookingPackage.value = 'Daily Pass';
    elements.editBookingPackage.dispatch('change');
    assert.equal(elements.editBookingEndDate.value, '2026-10-16');
    assert.equal(elements.editBookingDurationDays.value, '1');
    elements.editBookingDate.value = '2026-10-20';
    elements.editBookingDate.dispatch('change');
    assert.equal(elements.editBookingEndDate.value, '2026-10-20');
    assert.equal(run('JSON.stringify(userBookings)'), beforeEdit);
    elements.editBookingForm.reset();
    await Promise.resolve();
    assert.equal(elements.editBookingEndDate.value, '');

    // No real network requests: session data is supplied by this fixture.
    run(`userBookings = [{ id: 42, status: 'verified', package_name: 'Stored Weekly', booking_date: '2026-10-16', created_at: '2026-10-01 12:00:00', duration: 'Weekly', expires_at: '2026-10-19 23:59:59' }];`);
    sandbox.fetch = async url => {
        assert.match(url, /get-sessions\.php\?booking_id=42$/);
        return { json: async () => [{ id: 9, title: 'Recorded session', start: '2026-10-17', type: 'workout' }] };
    };
    const beforeCalendar = run('JSON.stringify(userBookings)');
    const events = await sandbox.getUserCalendarEvents();
    assert.equal(events.find(event => event.id === 'booking-start-42').start, '2026-10-16');
    assert.equal(events.find(event => event.id === 'booking-expiry-42').start, '2026-10-19');
    assert.equal(events.filter(event => event.id.startsWith('session-')).length, 1);
    for (const init of [sandbox.initModalCalendar, sandbox.initCoachCalendar]) {
        await init(42);
        const config = calendarConfigs[calendarConfigs.length - 1];
        let milestones;
        await config.eventSources[1]({}, result => { milestones = result; }, error => { throw error; });
        assert.equal(milestones[0].start, '2026-10-19');
        assert.equal(milestones[1].start, '2026-10-16');
        assert.equal(milestones[1].end, '2026-10-20');
    }
    assert.equal(run('JSON.stringify(userBookings)'), beforeCalendar);
    const storedRow = sandbox.createBookingRow({ ...stored, id: 43, package_name: 'Stored Plan', amount: 500 }, true);
    assert.match(storedRow.innerHTML, /Oct 19, 2026/);
    assert.match(storedRow.innerHTML, /Oct 16, 2026/);
    const invalidRow = sandbox.createBookingRow({ ...daily, duration: 'invalid', id: 44, package_name: 'Invalid Plan', amount: 100 }, true);
    assert.match(invalidRow.innerHTML, /—/);
    assert.doesNotMatch(invalidRow.innerHTML, /Invalid Date|NaN|days left/);
    run(`userBookings = [{ id: 42, status: 'verified', package_name: 'Weekly', booking_date: '2026-10-16', created_at: '2026-10-01 12:00:00', duration: 'Weekly' }];`);
    const fallbackEvents = await sandbox.getUserCalendarEvents();
    assert.equal(fallbackEvents.find(event => event.id === 'booking-expiry-42').start, '2026-10-22');

    assert.ok(viewSource.indexOf('assets/js/package-dates.js') < viewSource.indexOf('assets/js/user-dashboard.js'));
    for (const prefix of ['booking', 'editBooking']) {
        for (const suffix of ['EndDate', 'DurationDays']) {
            const input = viewSource.match(new RegExp(`<input[^>]*id="${prefix}${suffix}"[^>]*>`))?.[0];
            assert.ok(input && /\breadonly\b/.test(input));
            assert.ok(!/\bname=/.test(input), 'Preview fields must not be submitted');
        }
    }
    console.log(`Package user UI fixtures passed (TZ=${process.env.TZ || 'system'}).`);
}
main().catch(error => { console.error(error); process.exitCode = 1; });
