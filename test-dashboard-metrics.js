'use strict';

// Run with: node test-dashboard-metrics.js
// Isolated browser/API fixtures: this test never writes to the application database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const today = new Date();
const dateKey = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const day = dateKey(today);
const oldYear = `${today.getFullYear() - 1}-${day.slice(5)}`;
const future = `${today.getFullYear() + 1}-12-31 23:59:59`;
const bookings = [
    { id: 1, user_id: '1', status: 'verified', amount: '80.50', package_name: 'Discounted', created_at: `${day} 01:00:00`, verified_at: `${day} 02:00:00`, booking_date: '2030-01-01', expires_at: future, trainer_id: 1 },
    { id: 2, user_id: 1, status: 'verified', amount: '20.00', package_name: 'Discounted', created_at: `${day} 01:00:00`, expires_at: future },
    { id: 3, user_id: 2, status: 'expired', amount: '50.00', package_name: 'Discounted', created_at: `${day} 01:00:00` },
    { id: 4, user_id: 2, status: 'pending', amount: '70.00', package_name: 'Discounted', created_at: `${day} 01:00:00` },
    { id: 5, user_id: 2, status: 'rejected', amount: '90.00', package_name: 'Discounted', created_at: `${day} 01:00:00` },
    { id: 6, user_id: null, status: 'verified', amount: '10.00', package_name: 'Walk-in', created_at: `${day} 01:00:00`, expires_at: future },
    { id: 7, user_id: 2, status: 'expired', amount: '1000.00', package_name: 'Discounted', created_at: `${oldYear} 01:00:00` },
    { id: 8, user_id: 3, status: 'verified', amount: '5.00', package_name: 'Walk-in', created_at: `${day} 01:00:00`, expires_at: '2000-01-01 00:00:00' }
];
const fixture = {
    users: [{ id: 1, created_at: `${day} 01:00:00` }, { id: 2, created_at: '2000-01-01' }, { id: 3, created_at: '2000-01-01' }],
    bookings,
    trainers: [{ id: 1, name: 'Active Coach', is_active: true, active_client_count: 2 }, { id: 2, name: 'Inactive Coach', is_active: '0', active_client_count: 10 }],
    equipment: [{ id: 1, status: 'maintenance' }, { id: 2, status: 'out_of_order' }],
    exercises: [{ id: 1 }]
};
let failedApi = null;
const requestedApis = [];
const elements = new Map();
function element(id) {
    if (!elements.has(id)) elements.set(id, {
        value: '', textContent: '', innerHTML: '', style: {},
        classList: { add() {}, remove() {} }, appendChild() {},
        getContext() { return { createLinearGradient() { return { addColorStop() {} }; }, clearRect() {}, fillText() {} }; }
    });
    return elements.get(id);
}
function Chart(ctx, config) { this.data = config.data; this.destroy = () => {}; }
const context = vm.createContext({
    Date, Set, Map, String, Number, Math, JSON, Array, Object, parseFloat, parseInt, isNaN,
    console: { log() {}, error() {} }, Chart,
    window: { location: { origin: 'http://localhost', pathname: '/FIT_GYM_V2/views/admin/dashboard.php' } },
    document: { body: { dataset: { canManageTrainers: 'true' } }, readyState: 'loading', addEventListener() {}, getElementById: element, querySelectorAll: () => [], querySelector: () => null, createElement: () => element('card') },
    setInterval() {}, setTimeout() {},
    fetch: async url => {
        requestedApis.push(url);
        const key = Object.keys(fixture).find(name => url.includes(`/api/${name}/`));
        if (key === failedApi) throw new Error('Simulated network failure');
        return { ok: true, json: async () => ({ success: true, data: fixture[key] || [] }) };
    }
});
vm.runInContext(fs.readFileSync('assets/js/package-dates.js', 'utf8'), context);
vm.runInContext(fs.readFileSync('assets/js/dashboard.js', 'utf8'), context);
const run = code => vm.runInContext(code, context);
const stats = (category, id) => run(`dashboardStats.${category}.find(s => s.id === '${id}').value`);

(async () => {
    await context.updateStats();
    assert.equal(stats('overview', 'totalRevenueStat'), '₱1,165.5', 'Paid historical amounts, not pending/rejected, must count');
    assert.equal(stats('payments', 'monthlyRevenueStat'), '₱165.5');
    assert.equal(stats('payments', 'todayRevenueStat'), '₱165.5', 'Missing verification timestamps must use creation, not scheduled date');
    assert.equal(stats('payments', 'outstandingStat'), '₱70');
    assert.equal(stats('bookings', 'totalBookingsStat'), 8);
    assert.equal(stats('bookings', 'verifiedTodayStat'), 5);
    assert.equal(stats('overview', 'activeMembersStat'), 1, 'Deduplicate ids and exclude walk-ins/expired entitlements');
    assert.equal(stats('members', 'inactiveMembersStat'), 2);
    assert.equal(stats('members', 'newMembersStat'), 1);
    assert.equal(stats('overview', 'totalTrainersStat'), 1, 'String 0 is not an active trainer');
    assert.equal(stats('trainers', 'avgLoadStat'), '2.0');
    assert.equal(stats('inventory', 'maintenanceStat'), 1);
    assert.equal(stats('inventory', 'inventoryValueStat'), 'Needs Attention');

    run(`allBookings = ${JSON.stringify(bookings)}; packagesData = [{name:'Discounted'}, {name:'Walk-in'}]`);
    element('statsStartDate').value = day;
    element('statsEndDate').value = day;
    element('packageFilter').value = 'all';
    await context.initializePackageStatsChart();
    assert.equal(element('selectedPkgBookings').textContent, 7, 'Package booking counts include every request within the period');
    assert.equal(element('selectedPkgRevenue').textContent, '₱165.50');
    element('packageFilter').value = 'Discounted';
    await context.initializePackageStatsChart();
    assert.equal(element('selectedPkgBookings').textContent, 5);
    assert.equal(element('selectedPkgRevenue').textContent, '₱150.50');
    assert.equal(element('topPackageName').textContent, 'Discounted');
    await context.initializeChart();
    assert.equal(context.window.revenueChart.data.datasets[0].data.reduce((a, b) => a + b, 0), 165.5, 'Same month last year must not leak into the current chart');
    run('allBookings = []');
    element('packageFilter').value = 'all';
    await context.initializePackageStatsChart();
    assert.equal(element('topPackageName').textContent, 'No data', 'Do not invent a top package with zero sales');

    bookings.push({ id: 9, user_id: 1, status: 'upgraded', amount: '30.25', created_at: `${day} 01:00:00` });
    await context.updateStats();
    assert.equal(stats('overview', 'totalRevenueStat'), '₱1,195.75', 'An upgrade must not erase an already collected payment');
    assert.equal(stats('payments', 'monthlyRevenueStat'), '₱195.75');
    bookings.pop();

    failedApi = 'trainers';
    await context.updateStats();
    assert.equal(stats('overview', 'totalRevenueStat'), '₱1,165.5');
    assert.equal(stats('overview', 'totalTrainersStat'), 'Unavailable');
    failedApi = 'bookings';
    await context.updateStats();
    assert.equal(stats('overview', 'totalRevenueStat'), 'Unavailable');
    assert.equal(stats('members', 'registeredMembersStat'), 3);
    assert.equal(element('bookingsBadge').textContent, '?');

    failedApi = null;
    await context.updateStats();
    const adminStats = JSON.parse(run('JSON.stringify(dashboardStats)'));
    context.document.body.dataset.canManageTrainers = 'false';
    requestedApis.length = 0;
    await context.updateStats();
    assert.ok(!requestedApis.some(url => url.includes('/api/trainers/')), 'Manager must not request trainer management data');
    const managerStats = JSON.parse(run('JSON.stringify(dashboardStats)'));
    for (const category of ['bookings', 'payments', 'members', 'inventory']) {
        assert.deepEqual(managerStats[category], adminStats[category], `Manager ${category} must match admin calculations`);
    }
    assert.deepEqual(managerStats.overview.filter(s => s.id !== 'registeredMembersStat'), adminStats.overview.filter(s => s.id !== 'totalTrainersStat'));
    assert.equal(stats('overview', 'registeredMembersStat'), 3);
    assert.deepEqual(managerStats.trainers, []);
    context.switchControlTab('trainers');
    assert.equal(run('currentControlTab'), 'overview', 'Manager cannot switch to the restricted trainer tab');
    context.switchControlTab('payments');
    assert.equal(run('currentControlTab'), 'payments');
    failedApi = 'users';
    await context.updateStats();
    assert.equal(stats('overview', 'registeredMembersStat'), 'Unavailable');
    assert.equal(stats('overview', 'totalRevenueStat'), '₱1,165.5');
    failedApi = null;

    const bookingCallbacks = [];
    const walkinElements = new Map();
    const walkinRequests = [];
    const walkinElement = id => {
        if (!walkinElements.has(id)) {
            const classes = new Set();
            walkinElements.set(id, {
                value: '', textContent: '', innerHTML: '', dataset: {}, style: {},
                classList: { add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c) },
                addEventListener() {}, appendChild() {}, reset() { walkinElement('bookingDate').value = ''; }
            });
        }
        return walkinElements.get(id);
    };
    const bc = vm.createContext({
        console, Date, URLSearchParams, setInterval() {}, setTimeout: fn => fn(),
        window: { location: { search: '?action=walkin', pathname: '/FIT_GYM_V2/views/admin/bookings.php' }, history: { replaceState() {} } },
        document: { title: 'Bookings fixture', addEventListener: (type, fn) => { if (type === 'DOMContentLoaded') bookingCallbacks.push(fn); }, getElementById: walkinElement, querySelector: () => null, querySelectorAll: () => [], createElement: () => walkinElement('option') },
        fetch: async url => { walkinRequests.push(url); return { ok: true, json: async () => ({ success: true, data: [{ id: 1, name: 'Fixture Package', price: 100 }] }) }; }
    });
    vm.runInContext(fs.readFileSync('assets/js/package-dates.js', 'utf8'), bc);
    const gymDay = vm.runInContext('PackageDates.today()', bc);
    vm.runInContext(fs.readFileSync('assets/js/bookings.js', 'utf8'), bc);
    bc.initPage = () => {}; // Isolate the real action=walkin callback from unrelated calendar setup.
    bookingCallbacks.forEach(fn => fn());
    await Promise.resolve();
    assert.ok(walkinElement('walkinModal').classList.contains('active'), 'Dashboard Walk-in URL must open the integrated modal');
    assert.equal(walkinElement('bookingDate').value, gymDay, 'Form reset must not clear the default Philippine Walk-in date');
    assert.ok(walkinRequests.includes('../../api/packages/get-all.php'));
    bc.closeWalkinModal();
    assert.equal(walkinElement('walkinModal').classList.contains('active'), false);
    bc.openWalkinModal();
    assert.ok(walkinElement('walkinModal').classList.contains('active'), 'Bookings New Walk-in button handler must reopen the modal');
    assert.equal(walkinElement('bookingDate').value, gymDay);

    vm.runInContext(fs.readFileSync('assets/js/package-dates.js', 'utf8'), context);
    vm.runInContext(fs.readFileSync('assets/js/members.js', 'utf8'), context);
    assert.equal(context.isMemberActive({ verifiedBookings: [{ status: 'expired', expires_at: future }] }), false);
    assert.equal(context.isMemberActive({ verifiedBookings: [{ status: 'verified', expires_at: future }] }), true);
    assert.equal(context.isMemberActive({ verifiedBookings: [{ status: 'verified', expires_at: null }] }), true);
    const rc = vm.createContext({
        console, Date, Chart, setTimeout() {}, setInterval() {},
        document: { addEventListener() {}, getElementById: element, querySelectorAll: () => [], querySelector: () => null, createElement: () => element('toast') }
    });
    vm.runInContext(fs.readFileSync('assets/js/reports.js', 'utf8'), rc);
    const report = {
        summary: { total_bookings: 5, total_revenue: 130.75, pending_revenue: 70, paid_bookings: 3, verified_bookings: 1, expired_bookings: 1, upgraded_bookings: 1, pending_bookings: 1, rejected_bookings: 1, unique_clients: 3, walkin_bookings: 1 },
        sales_by_date: [{ sale_date: day, total_sales: 5, paid_count: 3, verified_count: 1, expired_count: 1, upgraded_count: 1, pending_count: 1, rejected_count: 1, total_revenue: 130.75, pending_revenue: 70, member_count: 4, walkin_count: 1 }],
        package_sales: [{ package_name: 'Discounted', total_availed: 5, unique_users: 3, paid_count: 3, verified_count: 1, expired_count: 1, upgraded_count: 1, pending_count: 1, rejected_count: 1, total_revenue: 130.75, pending_revenue: 70 }],
        filters: { start_date: day, end_date: day }
    };
    vm.runInContext(`salesData = ${JSON.stringify(report)}`, rc);
    assert.equal(rc.peso(130.75), '₱130.75', 'Reports must not round away centavos');
    rc.renderSummary();
    assert.equal(element('s-total-rev').textContent, '₱130.75');
    assert.equal(element('s-paid').textContent, '3');
    rc.renderStatusChart();
    assert.equal(vm.runInContext('statusChart.data.datasets[0].data.reduce((a,b)=>a+b,0)', rc), 5);
    const html = rc.buildReportHTML({}, 'Fixture Report', rc.getPeriodLabel(), '', true, true, true, true, true);
    assert.ok(html.includes('₱130.75') && html.includes('₱70.00') && html.includes('Upgraded'));
    let csv = '';
    rc.downloadBlob = content => { csv = content; };
    rc.exportSalesCsv();
    assert.ok(csv.includes('Verified,Expired,Upgraded,Pending,Rejected'));
    assert.equal(csv.trim().split('\n').at(-1).split(',').length, 12);
    rc.exportPackageCsv();
    assert.equal(csv.trim().split('\n').at(-1).split(',').length, 12);
    const sheets = {};
    rc.getGymSettings = async () => ({});
    rc.XLSX = { utils: { book_new: () => ({}), aoa_to_sheet: rows => ({ rows }), book_append_sheet: (book, sheet, name) => { sheets[name] = sheet.rows; } }, writeFile() {} };
    await rc.exportExcel('Fixture Report', day, true, true, true, true, true);
    assert.equal(sheets['Sales by Date'].at(-1)[8], 130.75);
    assert.equal(sheets['Sales by Date'].at(-1)[9], 70);
    assert.equal(sheets['Package Sales'].at(-1)[11], 130.75);
    assert.equal(sheets['Package Sales'].at(-1)[12], 70);
    assert.equal(sheets['Booking Status'].slice(5).reduce((sum, row) => sum + row[1], 0), 5);
    element('periodPreset').value = '1';
    const oneDay = rc.getDateRange();
    assert.equal(oneDay.start, oneDay.end);
    element('periodPreset').value = 'custom';
    element('startDate').value = '2030-01-01';
    element('endDate').value = '2030-02-01';
    assert.equal(rc.getPeriodLabel(), `${day} to ${day}`, 'Unapplied controls must not relabel exports');

    console.log('PASS: admin/manager metric parity, trainer restrictions, Walk-in modal/local date, dashboard API failures, report centavos/statuses, CSV/Excel/PDF data, and period regression checks');
})().catch(error => { console.error(error); process.exitCode = 1; });
