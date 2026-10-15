'use strict';

// Offline CLI regression: node test-package-dates.js. Only reads actual helpers;
// child processes use bounded timeouts and no application bootstrap or network.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { spawnSync } = require('node:child_process');

const root = __dirname;
const phpTest = path.join(root, 'test-package-booking-dates.php');
const configuredPhp = process.env.PACKAGE_DATES_PHP || 'C:/wamp64/bin/php/php8.3.14/php.exe';
const php = fs.existsSync(configuredPhp) ? configuredPhp : 'php';
const zones = ['UTC', 'America/Los_Angeles', 'Pacific/Kiritimati', 'Asia/Manila'];
let assertions = 0;
const failures = [];

function same(actual, expected, label) {
    assertions++;
    // Normalize VM-created plain records before comparing with host records.
    const normalize = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value));
    assert.deepStrictEqual(normalize(actual), normalize(expected), label);
}
function test(label, action) {
    try { action(); } catch (error) { failures.push(`${label}: ${error.message}`); }
}
function child(command, args, options = {}) {
    const result = spawnSync(command, args, {
        cwd: root, encoding: 'utf8', timeout: 30000, maxBuffer: 1024 * 1024,
        windowsHide: true, ...options,
    });
    if (result.error) throw result.error;
    assert.equal(result.status, 0, `Child failed: ${command} ${args.join(' ')}\n${result.stderr}${result.stdout}`);
    assert.equal(result.stderr, '', 'Child must not emit warnings/errors');
    return result.stdout;
}
function phpData(mode, input, tz = 'UTC') {
    return JSON.parse(child(php, ['-n', '-d', `date.timezone=${tz}`, phpTest, mode], {
        input: input === undefined ? undefined : JSON.stringify(input),
    }));
}
function expectedRange(v) {
    return v[2] === null ? null : {
        booking_date: v[0], duration_days: v[2], end_date: v[3],
        expires_at: `${v[3]} 23:59:59`, calendar_end: v[4],
    };
}
function loadActualHelper() {
    const context = vm.createContext({ Date, Intl });
    const sourcePath = path.join(root, 'assets/js/package-dates.js');
    vm.runInContext(fs.readFileSync(sourcePath, 'utf8'), context, { filename: sourcePath, timeout: 1000 });
    assert.ok(context.PackageDates, 'Actual helper must expose global PackageDates');
    return context.PackageDates;
}
function localSuite() {
    const dates = loadActualHelper();
    const vectors = phpData('--vectors');
    const phpOutput = phpData('--parity', vectors, process.env.TZ);
    for (const v of vectors.durations) test(`duration ${JSON.stringify(v[0])}`, () => same(dates.durationDays(v[0]), v[1], 'Independent duration oracle'));
    for (const v of vectors.dates) test(`calendar date ${JSON.stringify(v[0])}`, () => {
        const parsed = dates.calendarDate(v[0]);
        same(parsed !== null, v[1], 'Strict calendar validity');
        if (parsed) same(parsed.toISOString(), `${v[0]}T00:00:00.000Z`, 'No host-timezone calendar shifts');
    });
    for (const v of vectors.ranges) test(`range ${JSON.stringify(v)}`, () => same(dates.range(v[0], v[1]), expectedRange(v), 'Independent fixed-day range oracle'));

    // Agreement alone is not the oracle: both runtimes must match the explicit
    // expected dates above, including leap years and exclusive FullCalendar end.
    test('PHP/JS cross-runtime parity', () => {
        const jsOutput = {
            durations: vectors.durations.map(v => dates.durationDays(v[0])),
            dates: vectors.dates.map(v => dates.calendarDate(v[0])?.toISOString().slice(0, 10) ?? null),
            ranges: vectors.ranges.map(v => dates.range(v[0], v[1])),
        };
        same(jsOutput, phpOutput, 'Actual PHP and JS helper output parity');
        same(phpOutput.ranges, vectors.ranges.map(expectedRange), 'PHP also matches independent range oracle');
    });
    test('all aliases and optional pass/membership annotations', () => {
        const aliases = { daily: 1, day: 1, weekly: 7, week: 7, monthly: 30, month: 30,
            yearly: 365, year: 365, annual: 365, annually: 365 };
        for (const [alias, days] of Object.entries(aliases)) {
            for (const suffix of ['', ' Pass', ' Membership', ' (WHO Standard)', ' Pass (WHO Standard)', ' Membership (WHO Standard)']) {
                same(dates.durationDays(`  ${alias.toUpperCase()}${suffix}  `), days, `${alias}${suffix}`);
            }
        }
        for (const [unit, days] of Object.entries({ day: 1, week: 7, month: 30, year: 365 })) {
            same(dates.durationDays(`2 ${unit}`), days * 2, 'Positive integer singular unit');
            same(dates.durationDays(`2 ${unit}s (WHO Standard)`), days * 2, 'Positive integer plural unit');
        }
    });
    test('today changes at Manila midnight, not host midnight', () => {
        same(dates.today(new Date('2026-12-31T15:59:59Z')), '2026-12-31', 'Before Manila midnight');
        same(dates.today(new Date('2026-12-31T16:00:00Z')), '2027-01-01', 'Manila midnight');
        same(dates.today(new Date('2024-02-28T16:00:00Z')), '2024-02-29', 'Leap day at Manila midnight');
        same(dates.today(new Date('2024-02-29T16:00:00Z')), '2024-03-01', 'After leap day');
    });

    const timestampCases = [
        ['2024-02-29', '2024-02-28T16:00:00.000Z'],
        ['2024-02-29 00:00:00', '2024-02-28T16:00:00.000Z'],
        ['2024-02-29 23:59:59', '2024-02-29T15:59:59.000Z'],
        ['2024-02-29T23:59:59', '2024-02-29T15:59:59.000Z'],
        ['2024-02-29T23:59:59+08:00', '2024-02-29T15:59:59.000Z'],
        ['2026-12-31T16:00:00Z', '2026-12-31T16:00:00.000Z'],
        ['2026-12-31T16:00:00.125Z', '2026-12-31T16:00:00.125Z'],
        ['2026-12-31T08:00:00-08:00', '2026-12-31T16:00:00.000Z'],
        [' 2024-02-29 23:59:59 ', '2024-02-29T15:59:59.000Z'],
        ['', null], ['not a timestamp', null], ['2026-02-29', null], ['2024-02-30 00:00:00', null],
        ['2026-01-01 24:00:00', null], ['2026-01-01 12:60:00', null], ['2026-01-01 12:00:60', null],
        ['2026-1-01 00:00:00', null], ['2026-01-01 0:00:00', null], ['2026-01-01T00:00', null],
        ['2026-01-01T00:00:00+25:00', null], ['0999-01-01', null],
        ['2026-02-29T00:00:00Z', null], ['2024-02-30T00:00:00+08:00', null],
        ['2026-01-01T24:00:00Z', null], [null, null], [42, null], [false, null], [[], null],
    ];
    for (const [input, expected] of timestampCases) test(`timestamp ${JSON.stringify(input)}`, () => {
        same(dates.parseGymTimestamp(input)?.toISOString() ?? null, expected, 'Strict gym timestamp instant');
    });
    test('timestamp Date objects are copied; invalid Date rejected', () => {
        const original = new Date('2026-12-31T16:00:00Z');
        const parsed = dates.parseGymTimestamp(original);
        same(parsed.toISOString(), original.toISOString(), 'Date instant preserved');
        assertions++;
        assert.notEqual(parsed, original, 'Timestamp parsing must not return caller mutable Date');
        parsed.setUTCFullYear(2020);
        same(original.toISOString(), '2026-12-31T16:00:00.000Z', 'Original Date untouched');
        same(dates.parseGymTimestamp(new Date(NaN)), null, 'Invalid Date rejected');
    });

    const bookings = [
        [{ booking_date: '2024-02-29', duration: 'Daily' }, '2024-02-29T15:59:59.000Z', '2024-03-01'],
        [{ booking_date: '2024-02-28', duration: 'Weekly' }, '2024-03-05T15:59:59.000Z', '2024-03-06'],
        [{ date: '2026-12-29', duration: '1 Week' }, '2027-01-04T15:59:59.000Z', '2027-01-05'],
        [{ booking_date: '2024-02-28 06:00:00', duration: 'Weekly' }, '2024-03-05T15:59:59.000Z', '2024-03-06'],
        [{ booking_date: '2024-02-28', duration: '2 Years', expires_at: '2024-03-05 23:59:59' }, '2024-03-05T15:59:59.000Z', '2024-03-06'],
        [{ booking_date: 'invalid', duration: 'Forever', expires_at: '2024-02-29 23:59:59' }, '2024-02-29T15:59:59.000Z', '2024-03-01'],
        [{ booking_date: '2026-12-31', duration: 'Daily', expires_at: '2026-12-31T16:00:00Z' }, '2026-12-31T16:00:00.000Z', '2027-01-02'],
        [{ booking_date: '2024-02-29', duration: 'Daily', expires_at: 'malformed' }, null, null],
        [{ booking_date: '2024-02-29', duration: 'Weekly', expires_at: null }, '2024-03-06T15:59:59.000Z', '2024-03-07'],
        [{ booking_date: '2026-02-29', duration: 'Daily' }, null, null],
        [{ booking_date: '2026-01-01', duration: '0 Days' }, null, null],
        [{ expires_at: '9999-12-31 23:59:59' }, '9999-12-31T15:59:59.000Z', null],
        [{}, null, null],
    ];
    for (const [booking, expiry, end] of bookings) test(`expiry/calendarEnd ${JSON.stringify(booking)}`, () => {
        const before = JSON.stringify(booking);
        same(dates.expiry(booking)?.toISOString() ?? null, expiry, 'Stored expiry preferred over current package duration');
        same(dates.calendarEnd(booking), end, 'FullCalendar exclusive end after final Manila date');
        same(JSON.stringify(booking), before, 'Booking input not mutated');
    });
    test('displayDate uses Manila, including UTC previous day', () => {
        same(dates.displayDate('2026-12-31T16:00:00Z'), 'Jan 1, 2027', 'Manila display across year');
        same(dates.displayDate('2024-02-29'), 'Feb 29, 2024', 'Date-only leap-day display');
        same(dates.displayDate('2024-02-29 23:59:59'), 'Feb 29, 2024', 'Expiry display remains final covered day');
        same(dates.displayDate('malformed'), '—', 'Invalid display placeholder');
        same(dates.displayDate(null), '—', 'Missing display placeholder');
    });

    if (failures.length) throw new Error(`${process.env.TZ}: ${failures.length} failing groups; ${assertions} assertions attempted\n${failures.join('\n')}`);
    return assertions;
}

try {
    if (process.argv[2] === '--timezone') {
        console.log(JSON.stringify({ assertions: localSuite(), timezone: process.env.TZ }));
    } else {
        let total = 0;
        for (const tz of zones) test(`Host timezone ${tz}`, () => {
            const output = JSON.parse(child(process.execPath, [__filename, '--timezone'], {
                env: { ...process.env, TZ: tz },
            }));
            assert.equal(output.timezone, tz, 'Child host timezone configured');
            total += output.assertions;
        });
        if (failures.length) throw new Error(failures.join('\n'));
        console.log(`PASS: ${total} assertions across ${zones.length} host timezones; actual PHP/JS output parity and independent calendar oracles.`);
        console.log('Scope: helper-only VM fixtures; no browser/admin UI, database, HTTP, or external writes.');
    }
} catch (error) {
    console.error(`FAIL: ${error.message}`);
    process.exitCode = 1;
}
