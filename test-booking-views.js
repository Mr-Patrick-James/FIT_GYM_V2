'use strict';

// Run with: node test-booking-views.js
// Actual template/JavaScript, isolated DOM, read-only API fixtures; no database access.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const template = fs.readFileSync(path.join(__dirname, 'views/admin/bookings.php'), 'utf8');
const script = fs.readFileSync(path.join(__dirname, 'assets/js/bookings.js'), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));

function attributes(text) {
    return Object.fromEntries(Array.from(text.matchAll(/([\w-]+)="([^"]*)"/g), m => [m[1], m[2]]));
}

const toggleMarkup = template.match(/<div class="view-toggle">([\s\S]*?)<\/div>/)[1];
assert.ok(!toggleMarkup.includes('<?php'), 'Both roles must receive the same controls');
assert.match(template, /requireManagerAccess\(\);/);
assert.match(template, /<body class="role-<\?php echo \$user\['role'\]; \?>">/);
assert.match(template, /#calendar-view\s*\{\s*display: none;/);
for (const mode of ['calendar', 'table']) {
    const button = attributes(toggleMarkup.match(new RegExp(`<button([^>]*data-view="${mode}"[^>]*)>`))[1]);
    assert.equal(button.id, `${mode}ViewBtn`);
    assert.equal(button.type, 'button');
    assert.equal(button['aria-label'], `${mode[0].toUpperCase()}${mode.slice(1)} view`);
    assert.equal(button['aria-controls'], mode === 'calendar' ? 'calendar-view' : 'bookings-table-container');
    assert.equal(button['aria-pressed'], String(mode === 'table'));
    assert.equal(button.class.split(' ').includes('active'), mode === 'table');
    assert.equal(template.match(new RegExp(`id="${mode}ViewBtn"`, 'g')).length, 1);
}
for (const status of ['pending', 'verified', 'rejected', 'expired', 'upgraded']) {
    assert.ok(template.includes(`.event-status-${status}`), `${status} events need a status color`);
}

class Element {
    constructor(tag = 'div', attrs = {}) {
        this.tagName = tag.toUpperCase();
        this.attributes = { ...attrs };
        this.classes = new Set((attrs.class || '').split(' ').filter(Boolean));
        this.classList = {
            add: name => this.classes.add(name),
            remove: name => this.classes.delete(name),
            contains: name => this.classes.has(name),
            toggle: name => this.classes.has(name) ? this.classes.delete(name) : this.classes.add(name)
        };
        this.style = {};
        for (const declaration of (attrs.style || '').split(';')) {
            const [key, value] = declaration.split(':');
            if (key && value) this.style[key.trim()] = value.trim();
        }
        this.dataset = {};
        for (const [key, value] of Object.entries(attrs)) {
            if (key.startsWith('data-')) this.dataset[key.slice(5)] = value;
        }
        this.value = attrs.value || '';
        this.textContent = '';
        this.children = [];
        this.listeners = new Map();
        this.innerHTML = '';
    }
    get className() { return [...this.classes].join(' '); }
    set className(value) { this.classes = new Set(value.split(' ').filter(Boolean)); }
    get innerHTML() { return this.html; }
    set innerHTML(value) { this.html = value; this.children = []; }
    getAttribute(name) { return this.attributes[name] ?? null; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    addEventListener(type, handler) {
        if (!this.listeners.has(type)) this.listeners.set(type, []);
        this.listeners.get(type).push(handler);
    }
    async dispatch(type) {
        const event = { target: this, currentTarget: this, preventDefault() {}, stopPropagation() {} };
        await Promise.all((this.listeners.get(type) || []).map(handler => handler.call(this, event)));
        await flush();
    }
    appendChild(child) { child.parentElement = this; this.children.push(child); }
    remove() {
        if (this.parentElement) {
            this.parentElement.children = this.parentElement.children.filter(child => child !== this);
            this.parentElement = null;
        }
    }
}

function booking(id, status, overrides = {}) {
    return {
        id, status, name: `Client ${id}`, email: `client${id}@example.test`,
        package_name: 'Fixture Package', amount_formatted: '₱100.00', duration: '30 Days',
        created_at: `2030-01-${String(id).padStart(2, '0')} 12:00:00`,
        is_walkin: false, ...overrides
    };
}

function fixture(role, failure = null) {
    const elements = new Map();
    for (const match of template.matchAll(/<([a-z][\w-]*)\b([^<>]*\bid="([^"]+)"[^<>]*)>/gi)) {
        elements.set(match[3], new Element(match[1], attributes(match[2])));
    }
    for (const match of template.matchAll(/<select\b[^>]*id="([^"]+)"[^>]*>([\s\S]*?)<\/select>/g)) {
        const firstOption = match[2].match(/<option[^>]*value="([^"]*)"/);
        if (firstOption) elements.get(match[1]).value = firstOption[1];
    }
    const el = id => {
        assert.ok(elements.has(id), `Missing real template ID: ${id}`);
        return elements.get(id);
    };
    el('calendar-view').style.display = 'none'; // Actual template stylesheet default.
    const body = new Element('body', { class: `role-${role}` });
    const modalPrimary = new Element('button');
    const modalSecondary = new Element('button');
    const ready = [];
    const intervals = [];
    const errors = [];
    const requests = [];
    const instances = [];
    let constructors = 0;
    const state = {
        failure,
        bookings: [
            booking(1, 'verified', { booking_date: '2030-01-02' }),
            booking(2, 'pending', { booking_date: '2030-01-03 23:59:59', is_walkin: true }),
            booking(3, 'expired', { created_at: '2030-01-04 00:00:00' }),
            booking(4, 'upgraded', { booking_date: '2030-01-05T00:30:00+14:00', is_walkin: true }),
            booking(5, 'rejected', { booking_date: '2028-02-29' }),
            booking(6, 'expired', { booking_date: '2030-02-30' }),
            booking(7, 'pending', { booking_date: 'not-a-date' }),
            booking(8, 'upgraded', { booking_date: '0000-00-00 00:00:00' })
        ]
    };
    class Calendar {
        constructor(node, options) {
            constructors++;
            if (state.failure === 'constructor') throw new Error('Fixture constructor failed');
            assert.equal(node, el('calendar'));
            assert.equal(el('calendar-view').style.display, 'block', 'Construct after revealing container');
            this.options = options;
            this.sources = [];
            this.renderCount = 0;
            this.sizeCount = 0;
            this.addEventSource(options.events);
            instances.push(this);
        }
        getEventSources() { return this.sources.slice(); }
        addEventSource(events) {
            const source = { events: Array.from(events), remove: () => { this.sources = this.sources.filter(s => s !== source); } };
            this.sources.push(source);
        }
        get events() { return this.sources.flatMap(source => source.events); }
        render() {
            assert.equal(el('calendar-view').style.display, 'block', 'Render only when visible');
            this.renderCount++;
        }
        updateSize() {
            assert.equal(el('calendar-view').style.display, 'block', 'Resize only when visible');
            assert.ok(this.renderCount > this.sizeCount, 'Render before sizing');
            this.sizeCount++;
        }
        click(id) { this.options.eventClick({ event: { id: String(id) } }); }
    }
    const context = vm.createContext({
        Date, URLSearchParams,
        console: { log() {}, warn() {}, error: (...args) => errors.push(args) },
        document: {
            body, title: 'Bookings fixture',
            getElementById: id => elements.get(id) || null,
            createElement: tag => new Element(tag),
            querySelectorAll: () => [],
            querySelector: selector => ({
                '.modal-actions .btn-primary': modalPrimary,
                '.modal-actions .btn-secondary': modalSecondary
            })[selector] || null,
            addEventListener: (type, handler) => { if (type === 'DOMContentLoaded') ready.push(handler); }
        },
        window: { location: { search: '', pathname: '/FIT_GYM_V2/views/admin/bookings.php' } },
        setInterval: (callback, delay) => { intervals.push({ callback, delay }); },
        setTimeout() {},
        fetch: async (url, options) => {
            assert.ok(!options || !options.method || options.method === 'GET', 'No database writes allowed');
            assert.ok(url.startsWith('../../api/bookings/get-all.php?'), `Unexpected API: ${url}`);
            requests.push(url);
            const params = new URL(url, 'https://fixture.test/').searchParams;
            let rows = state.bookings.filter(b => params.get('status') === 'all' || b.status === params.get('status'));
            const search = (params.get('search') || '').toLowerCase();
            rows = rows.filter(b => `${b.name} ${b.email} ${b.package_name}`.toLowerCase().includes(search));
            rows = rows.slice().sort((a, b) => params.get('sort') === 'name-asc'
                ? a.name.localeCompare(b.name)
                : params.get('sort') === 'date-asc'
                    ? a.created_at.localeCompare(b.created_at) : b.created_at.localeCompare(a.created_at));
            return { ok: true, text: async () => JSON.stringify({ success: true, data: rows }) };
        }
    });
    if (failure !== 'cdn') context.FullCalendar = { Calendar };
    vm.runInContext(fs.readFileSync(path.join(__dirname, 'assets/js/package-dates.js'), 'utf8'), context);
    vm.runInContext(script, context, { filename: path.join(__dirname, 'assets/js/bookings.js') });
    const run = code => vm.runInContext(code, context);
    const rows = () => el('bookingsTable').children.map(row => Number(row.innerHTML.match(/viewBooking\('([^']+)'\)/)[1]));
    const notifications = () => body.children.filter(child => child.classList.contains('notification'));
    const assertView = mode => {
        assert.equal(run('viewMode'), mode);
        assert.equal(el('calendar-view').style.display, mode === 'calendar' ? 'block' : 'none');
        assert.equal(el('bookings-table-container').style.display, mode === 'table' ? 'block' : 'none');
        assert.notEqual(el('table-filters').style.display, 'none', 'One shared filter panel stays usable');
        for (const candidate of ['table', 'calendar']) {
            assert.equal(el(`${candidate}ViewBtn`).classList.contains('active'), candidate === mode);
            assert.equal(el(`${candidate}ViewBtn`).getAttribute('aria-pressed'), String(candidate === mode));
        }
        assert.equal(body.className, `role-${role}`);
    };
    const assertShared = () => {
        assert.deepEqual(rows(), Array.from(run('filteredBookings.map(b => b.id)')));
        assert.equal(Number(el('showingCount').textContent), rows().length);
        if (instances.length) {
            assert.equal(instances[0].sources.length, 1, 'Refresh must not accumulate event sources');
            assert.deepEqual(instances[0].events.map(e => e.id), Array.from(context.getCalendarEvents(), e => e.id));
        }
    };
    return {
        el, context, state, requests, errors, instances, intervals, rows, notifications, run, assertView, assertShared,
        get constructors() { return constructors; },
        async mount() {
            ready.forEach(handler => handler());
            await flush();
            assert.equal(requests.length, 1, 'Initialization loads once');
            assert.equal(intervals.length, 1);
            assert.equal(intervals[0].delay, 3000);
            for (const mode of ['table', 'calendar']) assert.equal(el(`${mode}ViewBtn`).listeners.get('click').length, 1);
            for (const id of ['bookingTypeFilter', 'statusFilter', 'sortBy', 'dateRange', 'customStartDate', 'customEndDate']) {
                assert.equal(el(id).listeners.get('change').length, 1, `One filter listener for ${id}`);
            }
            assert.equal(el('searchInput').listeners.get('input').length, 1);
            assertView('table');
            assertShared();
        },
        async change(id, value, event = 'change') {
            const before = requests.length;
            el(id).value = value;
            await el(id).dispatch(event);
            assert.equal(requests.length, before + 1, 'Each filter action makes exactly one request');
            assertShared();
        },
        async refresh(automatic = false) {
            const before = requests.length;
            await (automatic ? intervals[0].callback() : context.refreshBookings());
            assert.equal(requests.length, before + 1, 'Refresh makes exactly one shared request');
            assertShared();
        }
    };
}

async function testRole(role) {
    const f = fixture(role);
    // Initialization also repairs stale/restored button state, not just the markup default.
    f.el('calendarViewBtn').classList.add('active');
    f.el('calendarViewBtn').setAttribute('aria-pressed', 'true');
    f.el('tableViewBtn').classList.remove('active');
    f.el('tableViewBtn').setAttribute('aria-pressed', 'false');
    await f.mount();
    assert.equal(f.constructors, 0, 'Default table must not initialize FullCalendar');
    assert.equal(f.rows().length, 8, 'Invalid calendar dates remain manageable in the table');
    const initialRequests = f.requests.length;
    for (let n = 0; n < 5; n++) {
        await f.el('calendarViewBtn').dispatch('click');
        f.assertView('calendar');
        f.assertShared();
        await f.el('tableViewBtn').dispatch('click');
        f.assertView('table');
        f.assertShared();
    }
    assert.equal(f.requests.length, initialRequests, 'Switching views must not refetch');
    assert.equal(f.constructors, 1, 'Repeated switches reuse one FullCalendar instance');
    const calendar = f.instances[0];
    assert.equal(calendar.renderCount, 5);
    assert.equal(calendar.sizeCount, 5);
    assert.equal(calendar.events.length, 5, 'Omit invalid dates');
    for (const [id, start] of [[1, '2030-01-02'], [2, '2030-01-03'], [3, '2030-01-04'], [4, '2030-01-05'], [5, '2028-02-29']]) {
        const event = calendar.events.find(e => e.id === id);
        assert.equal(event.start, start, 'Keep the written day without a UTC shift');
        assert.equal(event.allDay, true);
        assert.ok(calendar.options.eventClassNames({ event }).includes(`event-status-${event.extendedProps.status}`));
    }
    for (const invalid of [null, undefined, 123, '', 'bad', '0000-01-01', '2030-00-01', '2030-13-01', '2030-01-00', '2030-02-29', '2100-02-29', '2030-04-31', '2030-01-02junk', '2030-01-02 24:00:00', '2030-01-02 12:60:00', '2030-01-02 12:00:60']) {
        assert.equal(f.context.normalizeCalendarDate(invalid), null, `Reject invalid date ${invalid}`);
    }
    assert.equal(f.context.normalizeCalendarDate(' 2030-01-02 00:00:00 '), '2030-01-02');
    assert.equal(f.context.normalizeCalendarDate('2000-02-29'), '2000-02-29');
    for (const mode of ['table', 'calendar']) {
        f.context.toggleView(mode);
        const renders = calendar.renderCount;
        const requests = f.requests.length;
        for (const invalid of ['list', '', null, undefined, {}, 'Calendar']) f.context.toggleView(invalid);
        f.assertView(mode);
        assert.equal(calendar.renderCount, renders);
        assert.equal(f.requests.length, requests);
    }
    calendar.click(1);
    assert.equal(f.el('modalBookingId').textContent, '#1', 'Event ID reaches the real viewBooking');
    assert.ok(f.el('bookingModal').classList.contains('active'));
    assert.equal(f.run('currentViewingBooking.id'), 1);
    f.context.closeModal();

    await f.change('bookingTypeFilter', 'walkin');
    assert.deepEqual(f.rows(), [4, 2]);
    f.assertView('calendar');
    await f.change('statusFilter', 'pending');
    assert.deepEqual(f.rows(), [2]);
    await f.change('statusFilter', 'all');
    await f.change('searchInput', 'Client 4', 'input');
    assert.deepEqual(f.rows(), [4]);
    await f.change('searchInput', '', 'input');
    await f.change('sortBy', 'name-asc');
    assert.deepEqual(f.rows(), [2, 4]);
    await f.change('customStartDate', '2030-01-04');
    await f.change('customEndDate', '2030-01-04');
    await f.change('dateRange', 'custom');
    assert.deepEqual(f.rows(), [4]);
    await f.change('dateRange', 'all');
    f.state.bookings.push(booking(9, 'pending', { booking_date: '2030-01-10', is_walkin: true }));
    await f.refresh();
    assert.deepEqual(f.rows(), [2, 4, 9]);
    f.assertView('calendar');
    await f.el('tableViewBtn').dispatch('click');
    f.state.bookings = f.state.bookings.filter(b => b.id !== 2);
    await f.refresh(true);
    f.assertView('table');
    assert.deepEqual(f.rows(), [4, 9]);
    await f.el('calendarViewBtn').dispatch('click');
    f.assertShared();
    assert.equal(f.constructors, 1);
    assert.equal(f.errors.length, 0);
}

async function testFallback(role, failure) {
    const f = fixture(role, failure);
    await f.mount();
    for (let n = 0; n < 2; n++) {
        await f.el('calendarViewBtn').dispatch('click');
        f.assertView('table');
        f.assertShared();
        assert.equal(f.instances.length, 0);
        const notification = f.notifications().at(-1);
        assert.ok(notification, 'Failure must show a visible notification');
        assert.ok(notification.classList.contains('warning'));
        assert.ok(notification.innerHTML.includes('Calendar unavailable. Please use Table view'));
        assert.ok(notification.style.cssText.includes('display: flex'));
        assert.equal(notification.parentElement, f.context.document.body);
        await f.el('tableViewBtn').dispatch('click');
        f.assertView('table');
    }
    assert.equal(f.requests.length, 1, 'Failure does not trigger extra filter requests');
    await f.change('bookingTypeFilter', 'walkin');
    assert.deepEqual(f.rows(), [4, 2]);
    await f.refresh();
    await f.refresh(true);
    f.assertView('table');
    assert.equal(f.errors.length, 2, 'Failures are caught, not unhandled');
    if (failure === 'constructor') {
        assert.equal(f.constructors, 2);
        f.state.failure = null;
        await f.el('calendarViewBtn').dispatch('click');
        f.assertView('calendar');
        f.assertShared();
        assert.equal(f.instances.length, 1, 'A failed constructor must not leave a broken instance');
    } else {
        assert.equal(f.constructors, 0);
    }
}

(async () => {
    for (const role of ['admin', 'manager']) {
        await testRole(role);
        await testFallback(role, 'cdn');
        await testFallback(role, 'constructor');
    }
    console.log('PASS: admin/manager booking view controls, repeated toggles, active/ARIA states, one calendar/source, local all-day dates, event details, shared filters/refresh, invalid modes/dates, and CDN/constructor fallback (no database writes)');
})().catch(error => { console.error(error); process.exitCode = 1; });
