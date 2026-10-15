'use strict';
// Offline tests of the actual trainer form handlers; no browser/API/database writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');
const root = __dirname;
const dateSource = fs.readFileSync(path.join(root, 'assets/js/trainer-dates.js'), 'utf8');
const details = fs.readFileSync(path.join(root, 'views/trainer/client-details.php'), 'utf8');
const members = fs.readFileSync(path.join(root, 'views/trainer/members.php'), 'utf8');

// A background shorthand on focus resets the select arrow's repeat/position/size.
for (const selector of ['.modern-input', '.modern-input:focus']) {
    const start = members.indexOf(`${selector} {`);
    assert.ok(start >= 0);
    const rule = members.slice(start, members.indexOf('}', start));
    assert.match(rule, /background-color:/);
    assert.doesNotMatch(rule, /\bbackground\s*:/);
}
assert.match(members, /select\.modern-input:focus\s*\{[^}]*background-repeat:\s*no-repeat\s*!important/s);
assert.match(members, /\.exercise-library-search input:focus\s*\{[^}]*padding:\s*12px 16px 12px 44px/s);
assert.ok(members.includes('label for="exerciseSelectTab"'));
assert.ok(members.includes('label for="modalExSearch"'));

function renderFixturePhp(source) {
    return source.replace(/<\?php[\s\S]*?\?>/g, expression => {
        if (expression.includes('$booking_id')) return '31';
        if (expression.includes('$memberId')) return '42';
        if (expression.includes('$canManageBooking')) return 'true';
        if (expression.includes('is_trainer_assisted')) return 'true';
        if (expression.includes('verified_at')) return '2026-10-16 00:00:00';
        return '0';
    });
}

// Compile every inline script in both edited views, with PHP represented by fixtures.
for (const [name, html] of [['client-details', details], ['members', members]]) {
    for (const match of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)) {
        new vm.Script(renderFixturePhp(match[1]), { filename: `${name}-inline.js` });
    }
    assert.ok(html.includes('assets/js/trainer-dates.js?v=1'));
    assert.ok(html.includes('trainerGymToday()'));
    assert.ok(html.includes('initialDate: trainerGymDate()'));
    assert.ok(!html.includes("toISOString().split('T')[0]"));
}

for (const timezone of ['UTC', 'Asia/Manila', 'America/Los_Angeles']) {
    execFileSync(process.execPath, ['-e', `${dateSource}\nconst a = require('node:assert/strict');
        a.equal(trainerGymDate(new Date('2026-10-15T15:59:59Z')), '2026-10-15');
        a.equal(trainerGymDate(new Date('2026-10-15T16:00:00Z')), '2026-10-16');
        a.equal(trainerGymDate(new Date('2026-12-31T16:00:00Z')), '2027-01-01');`], {
        env: { ...process.env, TZ: timezone }, stdio: 'pipe'
    });
}

function fixture(view = 'details', canManage = true) {
    const elements = new Map();
    const notifications = [];
    const requests = [];
    const get = id => {
        if (!elements.has(id)) elements.set(id, {
            value: '', innerHTML: 'Save', disabled: false, resets: 0,
            addEventListener() {}, reset() { this.resets++; },
            style: {}, classList: { add() {}, remove() {}, toggle() {} }
        });
        return elements.get(id);
    };
    class Clock extends Date {
        constructor(...args) { super(...(args.length ? args : ['2026-10-15T20:15:00Z'])); }
    }
    const context = vm.createContext({
        Date: Clock, Intl, console: { log() {}, warn() {}, error() {} },
        document: { getElementById: get, addEventListener() {}, querySelectorAll: () => [] },
        window: { location: {} }, setTimeout() {}, confirm: () => true,
        fetch: async (url, options) => {
            requests.push({ url, data: JSON.parse(options.body) });
            if (context.networkFailure) throw new Error('Offline');
            const body = context.response || { success: true };
            return { ok: context.httpOk !== false, json: async () => body, text: async () => JSON.stringify(body) };
        }
    });
    const html = view === 'details' ? details : members;
    const marker = view === 'details' ? 'const bookingId' : 'let allClients';
    const source = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)]
        .find(match => match[1].includes(marker))[1];
    vm.runInContext(dateSource + '\n' + renderFixturePhp(source).replace(
        'const canManageBooking = true;', `const canManageBooking = ${canManage};`), context);
    context.showNotification = (message, type) => notifications.push({ message, type });
    for (const loader of ['loadTips', 'loadFood', 'loadProgress', 'loadSessions']) context[loader] = () => {};
    get('tipText').value = 'Trainer notes';
    get('mealType').value = 'lunch';
    get('foodItems').value = 'Rice, chicken and vegetables';
    get('calories').value = '500';
    get('progressDate').value = '2026-10-16';
    get('progressWeight').value = '65';
    get('progressHeight').value = '168';
    get('progressRemarks').value = 'Same-day progress notes';
    get('sessionTitle').value = 'Daily workout';
    get('sessionType').value = 'workout';
    get('sessionDate').value = '2026-10-16';
    get('sessionTime').value = '14:00';
    get('sessionDuration').value = '60';
    get('sessionNotes').value = 'Same-day instructions';
    return { context, get, notifications, requests };
}

(async () => {
    for (const [handler, form, payloadField, fieldId] of [
        ['handleTipSubmit', 'tipForm', 'tip_text', 'tipText'],
        ['handleFoodSubmit', 'foodForm', 'food_items', 'foodItems'],
        ['handleProgressSubmit', 'progressForm', 'remarks', 'progressRemarks'],
        ['handleSessionSubmit', 'sessionForm', 'notes', 'sessionNotes']
    ]) {
        for (const state of ['success', 'denied', 'network', 'http-error']) {
            const f = fixture();
            if (state === 'denied') f.context.response = { success: false, message: 'Booking is not eligible today' };
            if (state === 'network') f.context.networkFailure = true;
            if (state === 'http-error') {
                f.context.httpOk = false;
                f.context.response = { success: false, message: 'Server unavailable' };
            }
            const original = f.get(fieldId).value;
            const button = f.get('submitButton');
            await f.context[handler]({ preventDefault() {}, target: { querySelector: () => button } });
            assert.equal(f.requests.length, 1);
            assert.equal(f.requests[0].data.booking_id, 31, `${handler} selects exact booking`);
            assert.equal(f.requests[0].data[payloadField], original);
            if (handler !== 'handleProgressSubmit') assert.equal(f.requests[0].data.member_id, 42);
            assert.equal(f.notifications.at(-1).type, state === 'success' ? 'success' : 'warning');
            assert.equal(f.get(form).resets, state === 'success' ? 1 : 0);
            if (state !== 'success') assert.equal(f.get(fieldId).value, original);
            if (state === 'denied') assert.equal(f.notifications.at(-1).message, 'Booking is not eligible today');
            assert.equal(button.disabled, false);
            if (state === 'success' && handler === 'handleProgressSubmit') assert.equal(f.get('progressDate').value, '2026-10-16');
            if (state === 'success' && handler === 'handleSessionSubmit') assert.equal(f.get('sessionDate').value, '2026-10-16');
        }
    }
    const clientList = fixture('members');
    vm.runInContext("allClients = [{booking_id:31,name:'Same-day client',can_manage:true}, {booking_id:32,name:'Renewed client',can_manage:true}]", clientList.context);
    clientList.context.switchTab = () => {};
    clientList.get('progressForm').reset = function () {
        this.resets++;
        clientList.get('progressBookingId').value = '';
        clientList.get('progressDate').value = '';
    };
    for (const bookingId of [31, 32, 31]) {
        await clientList.context.viewProgress(bookingId);
        assert.equal(clientList.get('progressBookingId').value, bookingId);
        assert.equal(clientList.get('progressDate').value, '2026-10-16');
        clientList.get('progressForm').querySelector = () => clientList.get('submitButton');
        await clientList.context.handleProgressSubmit({ preventDefault() {}, target: clientList.get('progressForm') });
        assert.equal(clientList.requests.at(-1).data.booking_id, bookingId);
        assert.equal(clientList.get('submitButton').disabled, false);
    }
    const library = fixture('members');
    vm.runInContext("allClients = [{booking_id:31,can_manage:true}]; activeBookingId = 31; modalMasterExercises = [{id:1,name:'Barbell Squat',category:'Legs'}, {id:2,name:'Bench Press',category:'Chest'}, {id:3,name:'Seated Cable Row',category:'Back'}]", library.context);
    library.get('modalExSearch').value = 'CHEST';
    library.context.filterModalExercises();
    assert.ok(library.get('exerciseSelectTab').innerHTML.includes('Bench Press (Chest)'));
    assert.ok(!library.get('exerciseSelectTab').innerHTML.includes('Seated Cable Row'));
    library.get('modalExSearch').value = 'no matching exercise';
    library.context.filterModalExercises();
    assert.equal(library.get('exerciseSelectTab').innerHTML, '<option value="">Choose an exercise...</option>');
    library.get('modalExSearch').value = '';
    library.context.filterModalExercises();
    assert.ok(library.get('exerciseSelectTab').innerHTML.includes('Seated Cable Row (Back)'));
    library.get('exerciseSelectTab').value = '2';
    library.get('exerciseSetsTab').value = '4';
    library.get('exerciseRepsTab').value = '8-10';
    library.context.renderModalPlan = () => {};
    library.context.addExerciseToTabPlan();
    assert.equal(vm.runInContext('modalCurrentPlan[0].id', library.context), 2);
    assert.equal(vm.runInContext('modalCurrentPlan[0].sets', library.context), '4');
    assert.equal(library.get('exerciseSelectTab').value, '');
    library.get('exerciseSelectTab').value = '2';
    library.context.addExerciseToTabPlan();
    assert.equal(vm.runInContext('modalCurrentPlan.length', library.context), 1);
    assert.equal(library.notifications.at(-1).message, 'Exercise already in plan');
    const history = fixture('members');
    const historyRows = [
        {booking_id:31,user_id:42,name:'Renewed Member',email:'renewed@example.invalid',package_name:'Old Daily',booking_date:'2026-10-15',expires_at:'2026-10-15 23:59:59',status:'expired',is_expired:true,can_manage:false},
        {booking_id:32,user_id:42,name:'Renewed Member',email:'renewed@example.invalid',package_name:'Active Renewal',booking_date:'2026-10-16',expires_at:null,status:'verified',is_expired:false,can_manage:true},
        {booking_id:33,user_id:42,name:'Renewed Member',email:'renewed@example.invalid',package_name:'Upgraded Old Plan',booking_date:'2026-10-17',expires_at:'2026-11-16 23:59:59',status:'upgraded',is_expired:false,can_manage:false},
        {booking_id:34,user_id:43,name:'Expired Member',email:null,package_name:'Expired Daily',booking_date:'2026-10-14',expires_at:'2026-10-14 23:59:59',status:'expired',is_expired:true,can_manage:false},
        {booking_id:35,user_id:0,name:'Same Walk-in',email:null,is_walkin:true,package_name:'Walk-in',booking_date:'2026-10-14',expires_at:null,status:'expired',is_expired:true,can_manage:false},
        {booking_id:36,user_id:0,name:'Same Walk-in',email:null,is_walkin:true,package_name:'Walk-in',booking_date:'2026-10-14',expires_at:null,status:'expired',is_expired:true,can_manage:false},
        {booking_id:37,user_id:44,name:'Same-day Expired',email:'today@example.invalid',package_name:'Daily',booking_date:'2026-10-16',expires_at:'2026-10-16 01:00:00',status:'expired',is_expired:true,can_manage:true}
    ];
    vm.runInContext(`allClients = ${JSON.stringify(historyRows)};`, history.context);
    const groups = history.context.groupClientBookings(historyRows);
    assert.equal(groups.length, 5, 'One registered-member card; never merge same-name walk-ins');
    assert.deepEqual(Array.from(groups.find(g => g[0].user_id === 42), c => c.booking_id), [32, 33, 31], 'Writable renewal before newer historical row');
    history.context.renderClients(historyRows);
    const grid = history.get('membersGrid');
    assert.equal((grid.innerHTML.match(/class="member-card"/g) || []).length, 5);
    assert.ok(grid.innerHTML.includes('data-booking-id="32"'));
    assert.ok(grid.innerHTML.includes('value="31"'));
    assert.ok(grid.innerHTML.includes('value="33"'));
    assert.ok(grid.innerHTML.includes('Assigned Package History (3)'));
    assert.ok(grid.innerHTML.includes('class="package-history form-group"'));
    assert.ok(grid.innerHTML.includes('<select class="modern-input" id="packageHistory-'));
    assert.ok(grid.innerHTML.includes('No expiry'));
    assert.ok(!grid.innerHTML.includes('Jan 1, 1970'));
    assert.ok(grid.innerHTML.includes('Expired Member'));
    assert.ok(grid.innerHTML.includes('View Progress & Plan History'));
    history.context.selectClientBooking(31);
    assert.ok(grid.innerHTML.includes('data-booking-id="31"'));
    assert.ok(grid.innerHTML.includes('onclick="viewProgress(31)"'));
    assert.ok(grid.innerHTML.includes('onclick="manageClient(31)"'));
    history.context.manageClient(31);
    assert.equal(history.context.window.location.href, 'client-details.php?booking_id=31');
    history.context.selectClientBooking(33);
    assert.ok(grid.innerHTML.includes('status-pill upgraded'));
    assert.ok(grid.innerHTML.includes('Historical Package · #33'));
    const beforeInvalid = grid.innerHTML;
    history.context.selectClientBooking(999);
    assert.equal(grid.innerHTML, beforeInvalid, 'Unknown booking is not selectable');
    history.get('memberSearch').value = 'renewed';
    history.context.filterMembers();
    assert.equal((grid.innerHTML.match(/class="member-card"/g) || []).length, 1);
    assert.ok(grid.innerHTML.includes('data-booking-id="33"'), 'Search preserves history selection');
    history.get('memberSearch').value = '';
    history.context.selectClientBooking(32);
    assert.ok(grid.innerHTML.includes('data-booking-id="32"'));
    assert.equal(history.context.escapeClientText('<script>"&'), '&lt;script&gt;&quot;&amp;');

    const controls = ['progressDate', 'progressRemarks', 'submitButton', 'exerciseSelectTab', 'exerciseSetsTab', 'exerciseRepsTab', 'planRemoveButton'];
    history.context.document.querySelectorAll = () => controls.map(history.get);
    let lastTab;
    history.context.switchTab = tab => { lastTab = tab; };
    await history.context.viewProgress(31);
    assert.equal(lastTab, 'history');
    assert.ok(history.get('progressBookingNotice').textContent.includes('Booking #31'));
    assert.ok(history.get('progressBookingNotice').textContent.includes('History only'));
    assert.equal(history.get('tabLog').disabled, true);
    for (const id of controls) assert.equal(history.get(id).disabled, true);
    const beforeHistoricalWrites = history.requests.length;
    await history.context.handleProgressSubmit({ preventDefault() {} });
    await history.context.saveModalPlan();
    history.context.addExerciseToTabPlan();
    vm.runInContext("modalCurrentPlan = [{id:1,sets:3,reps:'12'}]", history.context);
    history.context.updateModalEx(0, 'sets', 9);
    history.context.removeModalEx(0);
    assert.equal(vm.runInContext('modalCurrentPlan[0].sets', history.context), 3);
    assert.equal(history.requests.length, beforeHistoricalWrites, 'History cannot submit new writes');
    await history.context.viewProgress(32);
    assert.equal(lastTab, 'log');
    assert.equal(history.get('tabLog').disabled, false);
    for (const id of controls) assert.equal(history.get(id).disabled, false, 'Renewal restores controls');
    assert.equal(history.get('modalPlanSaveBtn').disabled, true, 'Do not save stale/unloaded plans from another booking');
    await history.context.viewProgress(37);
    assert.equal(lastTab, 'log', 'Same-day expired may still save');
    assert.equal(history.get('tabLog').disabled, false);

    const load = fixture('members');
    const loadRequests = [];
    load.context.fetch = async url => {
        loadRequests.push(url);
        return { ok: true, text: async () => JSON.stringify({success:true,data:historyRows}), json: async () => [] };
    };
    await load.context.loadClients();
    assert.equal(loadRequests.length, 3, 'Initial list plus only two writable booking session reads');
    assert.ok(!loadRequests.some(url => url.includes('booking_id=31')));
    assert.ok(load.get('membersGrid').innerHTML.includes('Expired Member'));

    const race = fixture('members');
    vm.runInContext(`allClients = ${JSON.stringify(historyRows)}; modalMasterExercises = [{id:99,name:'Library'}];`, race.context);
    race.context.switchTab = () => {};
    const pendingPlans = new Map();
    race.context.fetch = url => new Promise(resolve => pendingPlans.set(Number(url.split('booking_id=')[1]), resolve));
    await race.context.viewProgress(31);
    const oldPlan = race.context.loadModalPlan();
    await race.context.viewProgress(32);
    const newPlan = race.context.loadModalPlan();
    pendingPlans.get(32)({json:async () => ({success:true,data:{exercises:[{id:2,name:'Renewed Routine',sets:4,reps:'8'}]}})});
    await newPlan;
    pendingPlans.get(31)({json:async () => ({success:true,data:{exercises:[{id:1,name:'Historical Routine',sets:3,reps:'12'}]}})});
    await oldPlan;
    assert.equal(vm.runInContext('modalCurrentPlan[0].id', race.context), 2, 'Delayed old history cannot replace the renewal routine');
    assert.equal(vm.runInContext('modalPlanBookingId', race.context), 32);
    assert.equal(race.get('modalPlanSaveBtn').disabled, false);
    race.context.document.querySelector = () => race.get('modalPlanSaveBtn');
    race.context.fetch = async (url, options) => {
        race.requests.push({url,data:JSON.parse(options.body)});
        return {json:async () => ({success:true})};
    };
    await race.context.saveModalPlan();
    assert.equal(race.requests[0].data.booking_id, 32);
    assert.equal(race.requests[0].data.exercises[0].id, 2, 'Save only the exact loaded renewal routine');

    for (const handler of ['handleTipSubmit','handleFoodSubmit','handleProgressSubmit','handleSessionSubmit']) {
        const readOnly = fixture('details', false);
        await readOnly.context[handler]({ preventDefault() {} });
        assert.equal(readOnly.requests.length, 0, `${handler} historical details make no new write`);
        assert.equal(readOnly.notifications.at(-1).type, 'warning');
    }
    const lockedDetails = fixture('details', false);
    for (const form of ['sessionForm','tipForm','foodForm','progressForm']) {
        lockedDetails.get(form).querySelectorAll = () => [lockedDetails.get(`${form}-input`), lockedDetails.get(`${form}-button`)];
        lockedDetails.get(form).insertAdjacentHTML = (_, html) => { lockedDetails.get(form).notice = html; };
    }
    lockedDetails.context.disableCoachingFeatures('Package history is available to view.');
    for (const form of ['sessionForm','tipForm','foodForm','progressForm']) {
        assert.equal(lockedDetails.get(`${form}-input`).disabled, true);
        assert.ok(lockedDetails.get(form).notice.includes('Package history'));
    }
    const edit = fixture('details', false);
    edit.get('tip-edit-text-7').value = 'Updated notes';
    await edit.context.saveTipEdit(7);
    assert.equal(edit.requests[0].data.booking_id, 31);
    assert.equal(edit.requests[0].data.member_id, 42);
    assert.equal(edit.requests[0].data.tip_id, 7);
    console.log('PASS: trainer UI history visibility/grouping, renewal priority/selection, read-only controls, exact IDs, stale-plan isolation, successful saves, visible failures, retained inputs, dropdown and Manila date boundaries. Offline fixtures only.');
})().catch(error => { console.error(error); process.exitCode = 1; });
