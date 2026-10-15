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

function fixture(view = 'details') {
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
    vm.runInContext(dateSource + '\n' + renderFixturePhp(source), context);
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
    vm.runInContext("allClients = [{booking_id:31,name:'Same-day client'}, {booking_id:32,name:'Renewed client'}]", clientList.context);
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
    vm.runInContext("modalMasterExercises = [{id:1,name:'Barbell Squat',category:'Legs'}, {id:2,name:'Bench Press',category:'Chest'}, {id:3,name:'Seated Cable Row',category:'Back'}]", library.context);
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
    const edit = fixture();
    edit.get('tip-edit-text-7').value = 'Updated notes';
    await edit.context.saveTipEdit(7);
    assert.equal(edit.requests[0].data.booking_id, 31);
    assert.equal(edit.requests[0].data.member_id, 42);
    assert.equal(edit.requests[0].data.tip_id, 7);
    console.log('PASS: trainer UI exact-booking payloads, successful saves, visible failures, retained inputs, Manila/UTC/year boundaries and inline script syntax. Offline fixtures only.');
})().catch(error => { console.error(error); process.exitCode = 1; });
