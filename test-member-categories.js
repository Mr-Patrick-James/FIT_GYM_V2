'use strict';

// Run with: node test-member-categories.js
// Actual browser source, isolated API/DOM fixtures. No app bootstrap or database writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/members.js', 'utf8');
const helpers = fs.readFileSync('assets/js/package-dates.js', 'utf8');
const view = fs.readFileSync('views/admin/members.php', 'utf8');
const css = fs.readFileSync('assets/css/dashboard.css', 'utf8');
assert.ok(view.includes('requireManagerAccess();'), 'Admin and manager share the member page');
assert.ok(view.includes('id="memberTypeFilter"') && view.includes('value="registered">Online'));
assert.ok(view.includes('id="membersLoadError" role="alert" hidden'));
assert.match(css, /\.walkin-badge\s*\{[^}]*color:\s*#3b82f6;/, 'Walk-in uses blue');
assert.match(css, /\.regular-badge\s*\{[^}]*color:\s*#22c55e;/, 'Online uses green');
assert.ok(view.indexOf('package-dates.js') < view.indexOf('members.js'));

function fixture(role) {
    const elements = new Map();
    const callbacks = [];
    const requests = [];
    const timers = [];
    let exportedCsv = '';
    let failure = null;
    function domElement() {
        let html = '';
        const classes = new Set();
        return {
            value: '', textContent: '', className: '', style: {}, hidden: true, children: [], listeners: {},
            get innerHTML() { return html; },
            set innerHTML(value) { html = value; this.children = []; },
            classList: { add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c) },
            appendChild(child) { this.children.push(child); }, removeChild(child) { this.children = this.children.filter(c => c !== child); },
            addEventListener(type, callback) { this.listeners[type] = callback; }, click() {}, remove() {}
        };
    }
    function element(id) {
        if (!elements.has(id)) elements.set(id, domElement());
        return elements.get(id);
    }
    element('statusFilter').value = 'all';
    element('memberTypeFilter').value = 'all';
    element('sortBy').value = 'name-asc';
    const users = [
        { id: 1, name: 'Online Shared', email: 'shared@example.test', contact: '0999', role: 'user', created_at: '2024-01-01 00:00:00' },
        { id: 2, name: 'Online No Booking', email: 'no-booking@example.test', role: 'user', created_at: '2024-01-02 00:00:00' },
        { id: 3, name: 'Online Pending', email: 'pending@example.test', role: 'user', created_at: '2024-01-03 00:00:00' }
    ];
    const base = { package_name: 'Daily Fixture', booking_date: '2026-10-16', created_at: '2026-10-16 10:00:00', expires_at: '2099-10-16 23:59:59' };
    const bookings = [
        { ...base, id: 10, user_id: null, name: 'Walk-in Shared', email: ' SHARED@example.test ', contact: '0910', status: 'verified', amount: '40.25', is_walkin: false, booking_type: 'online' },
        { ...base, id: 11, user_id: null, name: 'Walk-in Shared', email: 'shared@EXAMPLE.test', contact: '', status: 'expired', amount: '50.10' },
        { ...base, id: 12, user_id: null, name: 'Walk-in Latest', email: 'shared@example.test', contact: '0912', status: 'upgraded', amount: '30.50' },
        { ...base, id: 13, user_id: null, name: 'Legacy Missing Email', email: null, status: 'expired', amount: '12.10' },
        { ...base, id: 14, user_id: null, name: 'Legacy Empty Email', email: '', status: 'verified', expires_at: null, amount: '15.25' },
        { ...base, id: 15, user_id: null, name: 'Pending Walk-in', email: 'other@example.test', status: 'pending', amount: '999' },
        { ...base, id: 16, user_id: null, name: 'Rejected Walk-in', email: 'other@example.test', status: 'rejected', amount: '999' },
        { ...base, id: 20, user_id: '1', name: 'Online Shared', email: 'shared@example.test', package_name: 'Walk-in Pass', status: 'verified', amount: '100.75', is_walkin: true, booking_type: 'walkin' },
        { ...base, id: 21, user_id: 1, name: 'Online Shared', email: 'shared@example.test', status: 'expired', amount: '20.25' },
        { ...base, id: 22, user_id: 3, name: 'Online Pending', email: 'pending@example.test', status: 'pending', amount: '100' },
        { ...base, id: 23, name: 'Unknown ownership', email: 'unknown@example.test', status: 'verified', amount: '100', is_walkin: true }
    ];
    const context = vm.createContext({
        console: { log() {}, error() {} }, Date,
        setTimeout() {}, setInterval(fn) { timers.push(fn); },
        Blob: class { constructor(parts) { exportedCsv = parts.join(''); } },
        window: { URL: { createObjectURL: () => 'blob:fixture', revokeObjectURL() {} } },
        document: {
            readyState: 'loading', body: { className: 'role-' + role, appendChild() {}, removeChild() {} },
            addEventListener(type, fn) { if (type === 'DOMContentLoaded') callbacks.push(fn); },
            getElementById: element, createElement: domElement,
            querySelector: () => domElement(), querySelectorAll: () => []
        },
        fetch: async url => {
            requests.push(url);
            const key = url.includes('/api/users/') ? 'users' : url.includes('/api/bookings/') ? 'bookings' : null;
            assert.ok(key, 'Members uses only users and unified bookings APIs: ' + url);
            if (failure === key + ':network') throw new Error('Network fixture');
            if (failure === key + ':http') return { ok: false, json: async () => ({ success: true, data: [] }) };
            const data = url.includes('status=pending') ? bookings.filter(b => b.status === 'pending') : key === 'users' ? users : bookings;
            return { ok: true, json: async () => failure === key + ':application' ? { success: false, data: [] }
                : failure === key + ':shape' ? { success: true, data: {} } : { success: true, data } };
        }
    });
    vm.runInContext(helpers, context);
    vm.runInContext(source, context);
    const run = code => vm.runInContext(code, context);
    const snapshot = () => JSON.parse(run('JSON.stringify(allMembers)'));
    return { context, run, snapshot, element, requests, callbacks, timers, bookings,
        fail(value) { failure = value; }, csv: () => exportedCsv };
}

(async () => {
    for (const role of ['manager', 'admin']) {
        const f = fixture(role);
        assert.equal((await f.context.loadAllMembers()).length, 6);
        assert.equal(f.requests.length, 2, 'No redundant Walk-in API call');
        assert.ok(f.requests.includes('../../api/bookings/get-all.php?status=all'));
        let members = f.snapshot();
        assert.deepEqual(members.map(m => m.id), ['user_1', 'user_2', 'user_3', 'walkin_10', 'walkin_13', 'walkin_14']);
        assert.equal(members.find(m => m.id === 'walkin_10').totalSpent, 120.85);
        assert.equal(members.find(m => m.id === 'walkin_10').bookings.length, 3);
        assert.equal(members.find(m => m.id === 'walkin_10').contact, '0912');
        assert.equal(members.find(m => m.id === 'walkin_10').name, 'Walk-in Latest');
        assert.equal(members.find(m => m.id === 'user_1').totalSpent, 121);
        assert.equal(members.find(m => m.id === 'user_3').totalSpent, 0);
        assert.equal(members.find(m => m.id === 'user_1').type, 'registered', 'Saved owner beats package name and misleading type flag');
        assert.equal(members.find(m => m.id === 'walkin_10').type, 'walkin', 'NULL owner beats misleading type flag');
        assert.equal(members.find(m => m.id === 'user_2').bookings.length, 0, 'Unbooked online account remains visible');
        f.context.applyFilters();
        assert.equal(f.element('membersGrid').children.length, 6);
        if (role === 'manager' && process.env.MEMBER_CATEGORY_PREVIEW) {
            // Optional static browser preview: actual rendered rows/CSS, no scripts or API calls.
            const legend = view.match(/<div class="member-category-legend"[\s\S]*?<\/div>/)[0];
            const header = view.match(/<div class="members-list-header">[\s\S]*?\n            <\/div>/)[0];
            fs.writeFileSync(process.env.MEMBER_CATEGORY_PREVIEW, `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline member category fixture</title><style>${css}</style></head><body class="role-manager"><main class="main-content" style="margin-left:0;width:100%;padding:16px"><div class="content-card"><div class="card-header"><h3>All Members — offline fixture</h3></div>${legend}${header}<div id="membersGrid">${f.element('membersGrid').children.map(row => `<div class="members-list-row">${row.innerHTML}</div>`).join('')}</div></div></main></body></html>`);
        }
        assert.equal(f.element('activeMembers').textContent, 3);
        assert.equal(f.element('inactiveMembers').textContent, 3);
        assert.ok(f.element('membersGrid').children.some(row => row.innerHTML.includes('walkin-badge') && row.innerHTML.includes('Inactive')));
        assert.ok(f.element('membersGrid').children.some(row => row.innerHTML.includes('regular-badge') && row.innerHTML.includes('Inactive')));
        for (const [type, status, count] of [['walkin', 'all', 3], ['registered', 'all', 3], ['walkin', 'inactive', 1], ['walkin', 'active', 2], ['registered', 'inactive', 2]]) {
            f.element('memberTypeFilter').value = type;
            f.element('statusFilter').value = status;
            f.context.applyFilters();
            assert.equal(f.element('membersGrid').children.length, count, type + '/' + status);
        }
        f.element('memberTypeFilter').value = 'walkin';
        f.element('statusFilter').value = 'all';
        f.element('searchInput').value = '0912';
        f.context.applyFilters();
        assert.equal(f.run('filteredMembers.length'), 1, 'Contact search composes with category');
        f.element('searchInput').value = '';
        f.element('memberTypeFilter').value = 'all';
        f.context.applyFilters();
        f.context.viewMember('walkin_10');
        assert.equal(f.element('modalMemberType').textContent, 'Walk-in');
        assert.equal(f.element('modalMemberType').className, 'walkin-badge');
        assert.equal(f.element('modalStatus').textContent, 'Active');
        assert.equal(f.element('memberBookingsList').children.length, 3);
        assert.ok(f.element('memberBookingsList').children.every(row => row.innerHTML.includes('walkin-badge')));
        f.context.viewMember('user_1');
        assert.equal(f.element('modalMemberType').textContent, 'Online');
        assert.equal(f.element('modalMemberType').className, 'regular-badge');
        assert.ok(f.element('memberBookingsList').children.every(row => row.innerHTML.includes('regular-badge')));
        f.context.exportMembers();
        assert.ok(f.csv().split('\n')[0].endsWith('Status,Booking Category'));
        assert.ok(f.csv().includes('Inactive,Walk-in') && f.csv().includes('Active,Online'));

        f.bookings.reverse();
        await f.context.loadAllMembers();
        const canonical = list => list.map(member => ({ ...member,
            bookings: [...member.bookings].sort((a, b) => a.id - b.id),
            verifiedBookings: [...member.verifiedBookings].sort((a, b) => a.id - b.id)
        }));
        assert.deepEqual(canonical(f.snapshot()), canonical(members), 'Reload/reordered API data restores same IDs, category and history');
        f.bookings.push({ ...f.bookings.find(b => b.id === 10), id: 17, amount: '95.15', name: 'Renewed Walk-in', contact: '0917' });
        await f.context.loadAllMembers();
        members = f.snapshot();
        assert.equal(members.length, 6, 'Staff save automatically appears without a separate account/member insert');
        assert.equal(members.find(m => m.id === 'walkin_10').bookings.length, 4, 'Renewal reuses customer entry');
        assert.equal(members.find(m => m.id === 'walkin_10').totalSpent, 216);
        assert.equal(members.find(m => m.id === 'walkin_10').contact, '0917');
        f.context.applyFilters();
        const preserved = JSON.stringify(members);
        for (const failure of ['users:network', 'bookings:network', 'users:http', 'bookings:http', 'users:application', 'bookings:application', 'users:shape', 'bookings:shape']) {
            f.fail(failure);
            assert.equal(await f.context.loadAllMembers(), null, failure);
            assert.equal(JSON.stringify(f.snapshot()), preserved, 'Failed API must not erase category/history');
            assert.equal(f.element('membersLoadError').hidden, false);
        }
        const messages = [];
        f.context.showNotification = (message, type) => messages.push({ message, type });
        await f.context.refreshMembers();
        assert.equal(messages.at(-1).type, 'warning', 'Do not claim a failed refresh succeeded');
        f.fail(null);
        await f.context.refreshMembers();
        assert.equal(f.element('membersLoadError').hidden, true);
        assert.equal(messages.at(-1).type, 'success');
        f.context.initializePage();
        assert.equal(f.element('memberTypeFilter').listeners.change, f.context.applyFilters);
        assert.equal(f.timers.length, 1);
        await f.timers[0]();
        assert.equal(f.run('filteredMembers.length'), 6, 'Automatic refresh retains saved categories');
    }
    console.log('PASS: admin/manager saved-source categories, blue/green labels, automatic Walk-in entries, renewal/history, combined filters, modal/CSV, legacy emails and API-failure preservation');
})().catch(error => { console.error(error); process.exitCode = 1; });
