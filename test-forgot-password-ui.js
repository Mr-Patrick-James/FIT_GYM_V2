'use strict';

// Run with: node test-forgot-password-ui.js
// Executes the entire actual landing-page script with isolated DOM/API fixtures.
// Never contacts the application, sends mail, stores accounts or changes passwords.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { TextEncoder } = require('node:util');
const source = fs.readFileSync('assets/js/main.js', 'utf8');
const view = fs.readFileSync('index.php', 'utf8');
const forms = ['loginForm', 'signupForm', 'otpVerificationForm', 'forgotPasswordForm', 'forgotOtpForm', 'newPasswordForm'];
const resetIds = ['forgotSubmitBtn', 'forgotVerifyBtn', 'newPasswordSubmitBtn', 'forgotResendOtp'];
for (const id of [...forms, ...resetIds, 'newPasswordOtpTimer']) assert.ok(view.includes(`id="${id}"`), 'Real markup has ' + id);
assert.ok(!source.includes("password: '__VERIFY_ONLY__'"), 'Frontend must never submit a placeholder password');
assert.ok(view.includes('assets/js/main.js?v=1.4'), 'Bust cached broken script');

// The VM fixture cannot model a hidden ancestor: validate the actual modal tree.
const modalMarkup = view.slice(view.indexOf('<div class="modal-overlay" id="authModal">'), view.indexOf('<script src="assets/js/main.js'));
const tagStack = [];
const seenForms = new Set();
const voidTags = new Set(['input', 'br', 'img', 'hr', 'meta', 'link', 'area', 'base', 'col', 'embed', 'param', 'source', 'track', 'wbr']);
for (const match of modalMarkup.matchAll(/<!--[\s\S]*?-->|<\/?([a-z][\w:-]*)\b[^>]*>/gi)) {
    if (!match[1]) continue;
    const tag = match[1].toLowerCase();
    if (match[0].startsWith('</')) {
        assert.equal(tagStack.pop()?.tag, tag, 'Balanced actual auth modal markup');
        continue;
    }
    const id = match[0].match(/\bid="([^"]+)"/)?.[1];
    const classes = (match[0].match(/\bclass="([^"]+)"/)?.[1] || '').split(/\s+/);
    if (forms.includes(id)) {
        assert.ok(!seenForms.has(id), 'One copy of auth screen ' + id);
        assert.ok(tagStack.at(-1)?.classes.includes('auth-card'), id + ' must be directly inside auth-card, not a hidden form');
        seenForms.add(id);
    }
    if (!voidTags.has(tag) && !match[0].endsWith('/>')) tagStack.push({ tag, classes });
}
assert.equal(tagStack.length, 0, 'Auth modal tags close completely');
assert.equal(seenForms.size, forms.length, 'All six real screens share the auth-card');

function fixture() {
    const elements = new Map();
    const requests = [];
    const errors = [];
    const successes = [];
    const intervals = new Map();
    const storageWrites = [];
    let nextInterval = 1;
    let reply = async () => ({ ok: true, json: async () => ({ success: true, data: { expires_at_timestamp: 1792152300 } }) });
    let clock = 1792152000000;
    class ClockDate extends Date { static now() { return clock; } }
    function element(id) {
        if (!elements.has(id)) {
            const classes = new Set();
            elements.set(id, {
                id, value: '', style: {}, disabled: false, textContent: '', innerHTML: '', listeners: {}, resetCount: 0,
                classList: { add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c) },
                addEventListener(type, fn) { this.listeners[type] = fn; },
                reset() {
                    this.resetCount++;
                    if (id === 'loginFormElement') { element('loginEmail').value = ''; element('loginPassword').value = ''; }
                },
                focus() { this.focused = true; }, querySelector() { return element(id + '-submit'); },
                querySelectorAll() { return []; }, setAttribute() {}, appendChild() {}, remove() {}, scrollIntoView() {}
            });
        }
        return elements.get(id);
    }
    const selectors = { '#loginForm form': 'loginFormElement', '#signupForm form': 'signupFormElement', '#otpVerificationForm form': 'otpVerificationFormElement' };
    const context = vm.createContext({
        console: { log() {}, error() {}, warn() {} }, Date: ClockDate, TextEncoder,
        setInterval(fn) { const id = nextInterval++; intervals.set(id, fn); return id; },
        clearInterval(id) { intervals.delete(id); }, setTimeout() {},
        localStorage: { getItem: () => null, setItem: (...args) => storageWrites.push(args), removeItem() {} },
        window: { addEventListener() {}, location: { replace() {} } },
        document: {
            getElementById: element, addEventListener() {}, querySelectorAll: () => [],
            querySelector: selector => selectors[selector] ? element(selectors[selector]) : null,
            createElement: () => element('created'), head: { appendChild() {} }, documentElement: { setAttribute() {} }
        },
        fetch: async (url, options) => {
            assert.ok(['api/auth/forgot-password.php', 'api/auth/reset-password.php'].includes(url), 'Unexpected API: ' + url);
            assert.equal(options.method, 'POST');
            const request = { url, body: JSON.parse(options.body) };
            requests.push(request);
            return reply(request);
        }
    });
    vm.runInContext(source, context, { filename: 'main.js' });
    // Keep the actual handlers and form switcher; record notifications independently.
    context.showError = (message, form) => errors.push({ message, form: form?.id });
    context.showSuccess = (message, form) => successes.push({ message, form: form?.id });
    const run = code => vm.runInContext(code, context);
    function response(body, ok = true) { reply = async () => ({ ok, json: async () => body }); }
    async function submit(id) {
        assert.ok(element(id).listeners.submit, 'Actual source registered ' + id);
        return element(id).listeners.submit.call(element(id), { preventDefault() {} });
    }
    function active() { return forms.filter(id => element(id).classList.contains('active')); }
    function otp(code = '012345') { [...code].forEach((digit, i) => { element('forgotOtpInput' + (i + 1)).value = digit; }); }
    function password(value = 'My new password 123') { element('newPassword').value = value; element('newPasswordConfirm').value = value; }
    function idle() { resetIds.forEach(id => assert.equal(element(id).disabled, false, id + ' recovers')); }
    return { context, run, element, requests, errors, successes, intervals, storageWrites, submit, active, otp, password, idle, response,
        reply(fn) { reply = fn; }, advance(ms) { clock += ms; [...intervals.values()].forEach(fn => fn()); } };
}

async function requestCode(f) {
    f.context.showForgotPassword();
    f.element('forgotEmail').value = ' MEMBER@EXAMPLE.TEST ';
    f.response({ success: true, data: { expires_at_timestamp: 1792152300 } });
    await f.submit('forgotPasswordFormElement');
    assert.deepEqual(f.active(), ['forgotOtpForm']);
}
async function verifyCode(f) {
    f.otp();
    f.response({ success: true, data: { verified: true } });
    await f.submit('forgotOtpFormElement');
    assert.deepEqual(f.active(), ['newPasswordForm']);
}

(async () => {
    const f = fixture();
    for (const [type, id] of [['login', 'loginForm'], ['signup', 'signupForm'], ['otp', 'otpVerificationForm'], ['forgotPassword', 'forgotPasswordForm'], ['forgotOtp', 'forgotOtpForm'], ['newPassword', 'newPasswordForm']]) {
        f.context.switchForm(type);
        assert.deepEqual(f.active(), [id], 'Exactly one auth screen: ' + type);
    }
    f.context.switchForm('unknown');
    assert.deepEqual(f.active(), ['newPasswordForm'], 'Unknown screen does not hide current form');
    f.element('loginEmail').value = 'member@example.test';
    f.context.showForgotPassword();
    assert.equal(f.element('forgotEmail').value, 'member@example.test');
    assert.deepEqual(f.active(), ['forgotPasswordForm']);
    await requestCode(f);
    assert.deepEqual(f.requests[0].body, { email: 'member@example.test' });
    assert.equal(f.element('forgotOtpEmailDisplay').textContent, 'member@example.test');
    assert.equal(f.intervals.size, 1);
    assert.equal(f.element('forgotOtpTimer').textContent, 'Code expires in 5:00');
    f.otp('12');
    await f.submit('forgotOtpFormElement');
    assert.equal(f.requests.length, 1, 'Incomplete code never hits API');
    f.otp('abcdef');
    await f.submit('forgotOtpFormElement');
    assert.equal(f.requests.length, 1, 'Nonnumeric code never hits API');
    for (const [body, ok] of [[{ success: false, message: 'Invalid reset code' }, false], [{ success: true, data: { verified: true } }, false], [{ success: true, data: {} }, true]]) {
        f.otp();
        f.response(body, ok);
        await f.submit('forgotOtpFormElement');
        assert.deepEqual(f.active(), ['forgotOtpForm'], 'Rejected/unconfirmed verify never advances');
        assert.equal(f.run('forgotPasswordData.otp'), '');
        f.idle();
    }
    await verifyCode(f);
    assert.deepEqual(f.requests.at(-1).body, { email: 'member@example.test', otp: '012345', _step: 'verify' });
    assert.equal(f.element('newPassword').focused, true);
    assert.equal(f.intervals.size, 1, 'Verification does not extend/stop expiry countdown');
    const beforePassword = f.requests.length;
    f.password('123');
    await f.submit('newPasswordFormElement');
    f.password('a'.repeat(73));
    await f.submit('newPasswordFormElement');
    f.password('é'.repeat(40));
    await f.submit('newPasswordFormElement');
    f.password();
    f.element('newPasswordConfirm').value = 'Different';
    await f.submit('newPasswordFormElement');
    assert.equal(f.requests.length, beforePassword, 'Invalid/oversized UTF-8/mismatched password does not submit');
    f.password();
    f.response({ success: false, message: 'Temporary server failure' }, false);
    await f.submit('newPasswordFormElement');
    assert.deepEqual(f.active(), ['newPasswordForm']);
    assert.equal(f.element('newPassword').value, 'My new password 123', 'Recoverable errors preserve input');
    f.idle();
    f.response({ success: true });
    await f.submit('newPasswordFormElement');
    assert.deepEqual(f.requests.at(-1).body, { email: 'member@example.test', otp: '012345', password: 'My new password 123', _step: 'reset' });
    assert.deepEqual(f.active(), ['loginForm']);
    assert.equal(f.element('loginEmail').value, 'member@example.test', 'Prefill survives actual login form reset');
    assert.equal(f.element('newPassword').value, '');
    assert.equal(f.element('newPasswordConfirm').value, '');
    assert.equal(f.run('forgotPasswordData.email'), '');
    assert.equal(f.run('forgotPasswordData.otp'), '');
    assert.equal(f.intervals.size, 0);
    assert.equal(f.successes.length, 1);
    assert.deepEqual(f.storageWrites, [], 'Reset does not store password or create a fake login session');
    f.idle();

    for (const reason of ['expired_otp', 'invalid_otp']) {
        const rejected = fixture();
        await requestCode(rejected);
        await verifyCode(rejected);
        rejected.password();
        rejected.response({ success: false, message: 'Request another reset code', data: { reason } }, false);
        await rejected.submit('newPasswordFormElement');
        assert.deepEqual(rejected.active(), ['forgotOtpForm']);
        assert.equal(rejected.run('forgotPasswordData.otp'), '');
        assert.equal(rejected.element('forgotOtpInput1').value, '');
        assert.equal(rejected.errors.at(-1).form, 'forgotOtpFormElement');
        rejected.idle();
    }
    for (const failure of ['application', 'http', 'network', 'json', 'null']) {
        const failed = fixture();
        failed.context.showForgotPassword();
        failed.element('forgotEmail').value = 'member@example.test';
        if (failure === 'network') failed.reply(async () => { throw new Error('Network fixture'); });
        else if (failure === 'json') failed.reply(async () => ({ ok: true, json: async () => { throw new Error('Non-JSON fixture'); } }));
        else failed.response(failure === 'null' ? null : { success: failure === 'http', message: 'Unable to send' }, failure !== 'http');
        await failed.submit('forgotPasswordFormElement');
        assert.deepEqual(failed.active(), ['forgotPasswordForm'], failure + ' request remains on email form');
        assert.ok(failed.errors.length);
        failed.idle();
    }
    const resend = fixture();
    await requestCode(resend);
    await verifyCode(resend);
    resend.context.showForgotCodeForm();
    assert.deepEqual(resend.active(), ['forgotOtpForm']);
    assert.equal(resend.run('forgotPasswordData.otp'), '');
    const oldExpiry = resend.run('forgotPasswordData.otpExpiry');
    resend.response({ success: false, message: 'Email unavailable' }, false);
    await resend.context.resendForgotOTP();
    resend.idle();
    assert.equal(resend.run('forgotPasswordData.otpExpiry'), oldExpiry, 'Failed resend does not invent a new server expiry');
    assert.equal(resend.errors.at(-1).form, 'forgotOtpFormElement');
    resend.response({ success: true, data: { expires_at_timestamp: 1792152600 } });
    await resend.context.resendForgotOTP();
    assert.equal(resend.run('forgotPasswordData.otpExpiry'), 1792152600000);
    assert.equal(resend.element('forgotResendOtp').textContent, 'Resend');
    resend.advance(600000);
    assert.equal(resend.element('forgotOtpTimer').textContent, 'Code expired. Please request a new one.');
    assert.equal(resend.element('newPasswordOtpTimer').textContent, 'Code expired. Please request a new one.');
    assert.equal(resend.intervals.size, 0);
    const expiredRequests = resend.requests.length;
    resend.context.closeModal();
    await resend.context.resendForgotOTP();
    assert.equal(resend.requests.length, expiredRequests, 'No resend without reset email state');
    resend.idle();

    // Double clicks and late responses cannot reopen a reset the user closed.
    for (const action of ['request', 'verify', 'reset', 'resend']) {
        const late = fixture();
        if (action !== 'request') await requestCode(late);
        if (action === 'reset') { await verifyCode(late); late.password(); }
        if (action === 'verify') late.otp();
        if (action === 'request') { late.context.showForgotPassword(); late.element('forgotEmail').value = 'late@example.test'; }
        let resolve;
        late.reply(() => new Promise(done => { resolve = done; }));
        const id = { request: 'forgotPasswordFormElement', verify: 'forgotOtpFormElement', reset: 'newPasswordFormElement' }[action];
        const start = () => action === 'resend' ? late.context.resendForgotOTP() : late.submit(id);
        const requestCount = late.requests.length;
        const pending = start();
        await Promise.resolve();
        await start();
        assert.equal(late.requests.length, requestCount + 1, action + ' blocks duplicate requests');
        assert.equal(late.element('forgotResendOtp').disabled, true);
        late.context.closeModal();
        late.context.switchForm('login');
        resolve({ ok: true, json: async () => ({ success: true, data: { verified: true, expires_at_timestamp: 1792152600 } }) });
        await pending;
        assert.deepEqual(late.active(), ['loginForm'], action + ' stale response stays dismissed');
        assert.equal(late.run('forgotPasswordData.email'), '');
        assert.equal(late.run('forgotPasswordData.otp'), '');
        assert.equal(late.intervals.size, 0);
        assert.equal(late.successes.length, 0);
        late.idle();
    }
    // Actual paste and keyboard handlers preserve six-digit codes with leading zero.
    const inputs = fixture();
    inputs.element('forgotOtpInput1').listeners.paste.call(inputs.element('forgotOtpInput1'), { preventDefault() {}, clipboardData: { getData: () => 'Code: 012345!' } });
    assert.equal(Array.from({ length: 6 }, (_, i) => inputs.element('forgotOtpInput' + (i + 1)).value).join(''), '012345');
    assert.equal(inputs.element('forgotOtpInput6').focused, true);
    inputs.element('forgotOtpInput2').value = '';
    inputs.element('forgotOtpInput2').listeners.keydown.call(inputs.element('forgotOtpInput2'), { key: 'Backspace' });
    assert.equal(inputs.element('forgotOtpInput1').focused, true);
    console.log('PASS: full actual-script reset UI, six form modes, verify-only requests, rejected-code handling, final reset, resend recovery, timers, login prefill, duplicate/stale guards and UTF-8 password validation. Offline fixtures only.');
})().catch(error => { console.error(error); process.exitCode = 1; });
