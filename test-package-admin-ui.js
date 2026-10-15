'use strict';
// Offline actual-source UI fixtures. No application bootstrap, HTTP or database writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = __dirname;
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
let now = '2026-10-16T15:59:59Z';
class Clock extends Date {
    constructor(...args) { super(...(args.length ? args : [now])); }
    static now() { return new Date(now).getTime(); }
}
function fixture(source, apiFixtures = null) {
    const elements = new Map();
    const ready = [];
    const get = id => {
        if (!elements.has(id)) {
            const listeners = {};
            const classes = new Set();
            const el = { value: '', textContent: '', style: {}, dataset: {}, children: [],
                classList: { add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x) },
                addEventListener(type, callback) { (listeners[type] ||= []).push(callback); },
                dispatch(type) { for (const callback of listeners[type] || []) callback({ target: el }); },
                appendChild(child) { this.children.push(child); },
                querySelector: () => null,
                querySelectorAll: () => []
            };
            Object.defineProperty(el, 'innerHTML', {
                get() { return this.html || ''; },
                set(value) { this.html = value; this.children = []; }
            });
            Object.defineProperty(el, 'options', { get() { return this.children; } });
            Object.defineProperty(el, 'selectedIndex', { get() { return this.children.findIndex(c => c.value === this.value); } });
            elements.set(id, el);
        }
        return elements.get(id);
    };
    get('walkinForm').reset = () => {
        get('packageSelect').value = '';
        get('bookingDate').value = '';
    };
    const context = vm.createContext({
        Date: Clock, Intl, URLSearchParams, console: { log() {}, warn() {}, error() {} },
        document: { getElementById: get, createElement: () => get(`new-${elements.size}`),
            addEventListener(type, callback) { if (type === 'DOMContentLoaded') ready.push(callback); },
            querySelectorAll: () => [], querySelector: () => null, body: { appendChild() {} } },
        window: { location: { search: '', pathname: '/fixture' } }, setTimeout() {}, setInterval() {},
        fetch: async url => {
            if (apiFixtures) {
                const key = Object.keys(apiFixtures).find(key => url.includes(`/api/${key}/`));
                assert.ok(key, `Unexpected fixture API: ${url}`);
                return { ok: true, json: async () => ({ success: true, data: apiFixtures[key] }) };
            }
            if (/walkin\/get-all\.php|settings\/get\.php/.test(url)) {
                return { json: async () => ({ success: true, data: [] }) };
            }
            assert.match(url, /packages\/get-all\.php$/, 'Only read-only fixture requests allowed');
            return { json: async () => ({ success: true, data: [
                { name: 'Daily Pass', duration: 'Daily', price: 100 },
                { name: 'Weekly Pass', duration: 'Weekly', price: 500 },
                { name: 'Invalid Pass', duration: 'invalid', price: 50 }
            ] }) };
        }
    });
    vm.runInContext(read('assets/js/package-dates.js'), context);
    vm.runInContext(read(source), context);
    return { context, get, ready };
}

(async () => {
    for (const view of ['views/admin/bookings.php', 'views/admin/walkin-bookings.php', 'views/admin/members.php']) {
        const html = read(view);
        assert.match(html, /requireManagerAccess\(\);/);
        assert.ok(html.indexOf('assets/js/package-dates.js') < html.indexOf(view.includes('walkin-') ? 'assets/js/walkin-bookings.js' : view.includes('members') ? 'assets/js/members.js' : 'assets/js/bookings.js'));
        if (!view.includes('members')) assert.match(html, /id="packageDatePreview"/);
    }
    const b = fixture('assets/js/bookings.js');
    const walkinReady = b.ready.find(callback => callback.toString().includes('walkinForm'));
    assert.ok(walkinReady, 'Actual Walk-in DOM-ready handler');
    walkinReady();
    await b.context.loadPackages();
    b.context.setDefaultDate();
    assert.equal(b.get('bookingDate').value, '2026-10-16');
    for (const [name, end, days] of [['Daily Pass', 'Oct 16, 2026', 1], ['Weekly Pass', 'Oct 22, 2026', 7]]) {
        b.get('packageSelect').value = name;
        b.get('packageSelect').dispatch('change');
        assert.match(b.get('packageDatePreview').textContent, new RegExp(end));
        assert.ok(b.get('packageDatePreview').textContent.includes(`(${days} ${days === 1 ? 'day' : 'days'})`));
        assert.equal(b.get('bookingDate').value, '2026-10-16', 'Selecting package never replaces chosen start');
    }
    b.get('bookingDate').value = '2026-12-31';
    b.get('bookingDate').dispatch('change');
    assert.match(b.get('packageDatePreview').textContent, /Jan 6, 2027/);
    b.get('packageSelect').value = 'Invalid Pass';
    b.get('packageSelect').dispatch('change');
    assert.match(b.get('packageDatePreview').textContent, /Choose a package/);
    b.context.closeWalkinModal();
    now = '2026-10-16T16:00:00Z';
    b.context.openWalkinModal();
    assert.equal(b.get('bookingDate').value, '2026-10-17');
    assert.match(b.get('packageDatePreview').textContent, /Choose a package/);
    assert.equal(b.context.formatDateForDisplay('2026-10-16'), 'Oct 16, 2026');
    assert.equal(b.context.formatDateForDisplay('2026-10-16 23:59:59'), 'Oct 16, 2026');
    assert.equal(b.context.formatDateForDisplay('2026-10-16T16:00:00Z'), 'Oct 17, 2026');

    const w = fixture('assets/js/walkin-bookings.js');
    const manager = vm.runInContext('Object.create(WalkinBookingsManager.prototype)', w.context);
    manager.bindEvents();
    await manager.loadPackages();
    manager.setDefaultDate();
    w.get('bookingDate').value = '2024-02-28';
    w.get('packageSelect').value = 'Weekly Pass';
    w.get('packageSelect').dispatch('change');
    assert.match(w.get('packageDatePreview').textContent, /Mar 5, 2024.*7 days/);
    w.get('packageSelect').value = 'Daily Pass';
    w.get('packageSelect').dispatch('change');
    assert.match(w.get('packageDatePreview').textContent, /Feb 28, 2024.*1 day/);
    w.get('bookingDate').value = '2026-12-31';
    w.get('bookingDate').dispatch('change');
    assert.match(w.get('packageDatePreview').textContent, /Dec 31, 2026.*1 day/);
    manager.openWalkinModal();
    assert.equal(w.get('bookingDate').value, '2026-10-17');
    assert.match(w.get('packageDatePreview').textContent, /Choose a package/);
    const daily = { status: 'verified', booking_date: '2026-10-16', duration: 'Daily' };
    now = '2026-10-16T15:59:59Z';
    assert.equal(manager.isBookingActive(daily), true);
    assert.equal(b.context.isBookingActive(daily), true);
    now = '2026-10-16T16:00:00Z';
    assert.equal(manager.isBookingActive(daily), false);
    assert.equal(b.context.isBookingActive(daily), false);

    const m = fixture('assets/js/members.js');
    const stored = { status: 'verified', booking_date: '2026-10-16', duration: '2 Years',
        expires_at: '2026-10-19 23:59:59', package_name: 'Stored Plan', amount: 500 };
    const member = { id: 42, name: 'Fixture Member', joinedDate: '2026-10-01 12:00:00',
        bookings: [stored], verifiedBookings: [stored], totalSpent: 500 };
    vm.runInContext(`allMembers = [${JSON.stringify(member)}];`, m.context);
    m.context.switchTab = () => {};
    m.context.viewMember(42);
    assert.equal(m.get('modalPackageExpiry').textContent, 'Oct 19, 2026', 'Banner preserves purchased range');
    assert.equal(m.context.isMemberActive({ verifiedBookings: [{ status: 'verified', expires_at: null }] }), true, 'Legacy no-expiry counts unchanged');
    assert.equal(m.context.formatDateForDisplay('2026-10-16'), 'Oct 16, 2026');
    m.context.renderCalendar();
    assert.match(m.get('memberCalendar').innerHTML, /class="cal-cell has-booking verified[^\"]*">\s*<span>16<\/span>/);
    assert.doesNotMatch(m.get('memberCalendar').innerHTML, /class="cal-cell has-booking[^\"]*">\s*<span>15<\/span>/);
    assert.equal(m.get('calendarMonthLabel').textContent, 'October 2026');

    const metricFixture = fixture('assets/js/dashboard.js', {
        users: [{id:42,created_at:'2026-10-01 10:00:00'}],
        bookings: [{user_id:42,status:'verified',amount:100,created_at:'2026-10-16 10:00:00',
            booking_date:'2026-10-16',expires_at:'2026-10-16 23:59:59'}],
        trainers: [], equipment: [], exercises: []
    });
    for (const [instant, activeCount] of [['2026-10-16T15:59:59Z', 1], ['2026-10-16T16:00:00Z', 0]]) {
        now = instant;
        await metricFixture.context.updateStats();
        assert.equal(vm.runInContext("dashboardStats.overview.find(s => s.id === 'activeMembersStat').value", metricFixture.context), activeCount,
            'Dashboard uses the same Manila expiry boundary as Bookings and Members');
    }
    const dashboardView = read('views/admin/dashboard.php');
    assert.ok(dashboardView.indexOf('assets/js/package-dates.js') < dashboardView.indexOf('assets/js/dashboard.js'));
    console.log(`PASS: admin/manager and Walk-in previews, changes/resets, Manila midnight metric parity, stored member expiry and recorded-date calendar (TZ=${process.env.TZ || 'system'}). Offline only.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
