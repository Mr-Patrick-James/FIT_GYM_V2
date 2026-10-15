<?php
declare(strict_types=1);

namespace TrainerBookingRegression;

use DateTimeImmutable;
use DateTimeZone;
use Error;
use Exception;
use RuntimeException;
use Throwable;

// Standalone, CLI-only: never include the application's DB/session bootstrap.
if (PHP_SAPI !== 'cli') {
    exit;
}

const ENDPOINTS = ['save-tip.php', 'save-food.php', 'log-progress.php', 'save-member-plan.php', 'save-session.php'];
// Independent SQL contracts: do not derive these from the implementation under test.
const ASSIGNMENT_SQL = '(b.trainer_id = ? OR (b.trainer_id IS NULL AND EXISTS (
    SELECT 1 FROM package_trainers pt WHERE pt.package_id = b.package_id AND pt.trainer_id = ?)))';
const WRITABLE_SQL = "((b.status = 'verified' AND (b.expires_at IS NULL OR b.expires_at >= ?))
    OR (b.status IN ('verified', 'expired') AND DATE(b.booking_date) = ?))";
const BOOKING_SELECT = 'SELECT b.*, p.name AS package_name, p.is_trainer_assisted
    FROM bookings b JOIN packages p ON b.package_id = p.id WHERE b.id = ? AND ';

$assertions = 0;
$environment = [];

function check(bool $condition, string $message): void
{
    global $assertions;
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function same($expected, $actual, string $message): void
{
    check($expected === $actual, $message . ' (expected ' . var_export($expected, true)
        . ', got ' . var_export($actual, true) . ')');
}

function sqlKey(string $sql): string
{
    $sql = preg_replace('/\s*([(),])\s*/', '$1', $sql);
    return strtolower(trim(preg_replace('/\s+/', ' ', $sql)));
}

// Error, not Exception: successful responses must escape endpoint catch(Exception)
// blocks without causing rollback or being mistaken for an application failure.
final class ResponseSignal extends Error
{
    public function __construct(public array $response)
    {
        parent::__construct((string)$response['message']);
    }
}

function sendResponse($success, $message, $data = null, $code = 200): never
{
    throw new ResponseSignal(compact('success', 'message', 'data', 'code'));
}

function requireTrainer(): void
{
    global $environment;
    ++$environment['auth_checks'];
}

function getCurrentUser(): array
{
    return ['id' => 7, 'name' => 'Regression Coach', 'role' => 'trainer'];
}

function getDBConnection(): MemoryDB
{
    global $environment;
    return $environment['db'];
}

function getRequestData(): array
{
    global $environment;
    return $environment['input'];
}

// Namespaced overrides keep actual endpoint input/logging code unchanged while
// preventing php://input reads and persistent notification-error log writes.
function file_get_contents(string $path): string
{
    check($path === 'php://input', 'Endpoint attempted an unexpected file read: ' . $path);
    return json_encode(getRequestData(), JSON_THROW_ON_ERROR);
}

function error_log(string $message): bool
{
    global $environment;
    $environment['logs'][] = $message;
    return true;
}

function createNotification($memberId, $title, $message, $type): void
{
    global $environment;
    $environment['notifications'][] = [
        'member_id' => $memberId, 'type' => $type,
        'mutations_before' => count($environment['db']->mutations),
    ];
    if ($environment['notification_failure'] === 'error') {
        throw new Error('Simulated notification error');
    }
    if ($environment['notification_failure'] === 'exception') {
        throw new RuntimeException('Simulated notification failure');
    }
}

final class MemoryResult
{
    public int $num_rows;
    private int $offset = 0;

    public function __construct(private array $rows)
    {
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array
    {
        return $this->rows[$this->offset++] ?? null;
    }
}

final class MemoryStatement
{
    public int $affected_rows = 0;
    public string $error = '';
    private array $bindings = [];
    private string $types = '';
    private array $rows = [];

    public function __construct(private MemoryDB $db, private string $sql) {}

    public function bind_param(string $types, &...$values): bool
    {
        same(substr_count($this->sql, '?'), strlen($types), 'SQL placeholder/type count');
        same(strlen($types), count($values), 'SQL binding count');
        $this->types = $types;
        $this->bindings = [];
        foreach ($values as &$value) {
            $this->bindings[] =& $value;
        }
        unset($value);
        return true;
    }

    public function execute(): bool
    {
        $values = [];
        foreach ($this->bindings as $value) {
            $values[] = $value;
        }
        [$this->rows, $this->affected_rows] = $this->db->execute($this->sql, $values, $this->types);
        return true;
    }

    public function get_result(): MemoryResult { return new MemoryResult($this->rows); }
    public function close(): void {}
}

final class MemoryDB
{
    public array $mutations = [];
    public array $transactions = [];
    public array $queries = [];
    public string $error = '';
    public int $insert_id = 1;

    public function __construct(public array $fixture) {}

    public function prepare(string $sql): MemoryStatement { return new MemoryStatement($this, $sql); }
    public function begin_transaction(): bool { $this->transactions[] = 'begin'; return true; }
    public function commit(): bool { $this->transactions[] = 'commit'; return true; }
    public function rollback(): bool { $this->transactions[] = 'rollback'; return true; }
    public function close(): void {}

    public function query(string $sql): MemoryResult|bool
    {
        $this->queries[] = ['sql' => $sql, 'values' => []];
        if (sqlKey($sql) === "show tables like 'trainer_sessions'") {
            return new MemoryResult([['trainer_sessions' => 'trainer_sessions']]);
        }
        if (preg_match('/^DELETE FROM member_exercise_plans WHERE booking_id = (\d+)$/i', trim($sql), $match)) {
            $this->mutations[] = ['operation' => 'delete', 'table' => 'member_exercise_plans',
                'values' => ['booking_id' => (int)$match[1]]];
            return true;
        }
        throw new RuntimeException('Unstubbed query (fail closed): ' . $sql);
    }

    public function execute(string $sql, array $values, string $types): array
    {
        $this->queries[] = compact('sql', 'values', 'types');
        $key = sqlKey($sql);
        if ($key === 'select id from trainers where user_id = ?') {
            same('i', $types, 'Profile user binding');
            $id = $this->fixture['profiles'][$values[0]] ?? null;
            return [$id === null ? [] : [['id' => $id]], 0];
        }

        $lookup = sqlKey(BOOKING_SELECT . ASSIGNMENT_SQL);
        $writableLookup = sqlKey(BOOKING_SELECT . ASSIGNMENT_SQL . ' AND ' . WRITABLE_SQL);
        if ($key === $lookup || $key === $writableLookup) {
            same($key === $lookup ? 'iii' : 'iiiss', $types, 'Booking placeholder order');
            // Exact ID only: never choose another booking/renewal for this member.
            $booking = $this->fixture['bookings'][$values[0]] ?? null;
            $package = $booking ? ($this->fixture['packages'][$booking['package_id']] ?? null) : null;
            if (!$booking || !$package) {
                return [[], 0];
            }
            $assigned = $booking['trainer_id'] !== null
                ? (int)$booking['trainer_id'] === (int)$values[1]
                : in_array((int)$values[2], $this->fixture['package_trainers'][$booking['package_id']] ?? [], true);
            if (!$assigned) {
                return [[], 0];
            }
            if ($key === $writableLookup) {
                // Independent interpretation of the two SQL predicates/bound times.
                $active = $booking['status'] === 'verified'
                    && ($booking['expires_at'] === null || $booking['expires_at'] >= $values[3]);
                $today = in_array($booking['status'], ['verified', 'expired'], true)
                    && substr($booking['booking_date'], 0, 10) === $values[4];
                if (!$active && !$today) {
                    return [[], 0];
                }
            }
            return [[array_merge($booking, [
                'package_name' => $package['name'], 'is_trainer_assisted' => $package['is_trainer_assisted'],
            ])], 0];
        }

        if ($key === 'select id from trainer_tips where id = ? and trainer_id = ? and member_id = ?') {
            same('iii', $types, 'Tip ownership binding');
            $tip = $this->fixture['tips'][$values[0]] ?? null;
            $own = $tip && $tip['trainer_id'] === $values[1] && $tip['member_id'] === $values[2];
            return [$own ? [['id' => $values[0]]] : [], 0];
        }
        if ($key === 'update trainer_tips set tip_text = ? where id = ? and trainer_id = ? and member_id = ?') {
            same('siii', $types, 'Tip update ownership binding');
            $tip = $this->fixture['tips'][$values[1]] ?? null;
            check($tip !== null && $tip['trainer_id'] === $values[2] && $tip['member_id'] === $values[3],
                'Attempted unauthorized tip mutation');
            $affected = (int)($tip['tip_text'] !== $values[0]);
            $this->fixture['tips'][$values[1]]['tip_text'] = $values[0];
            $this->mutations[] = ['operation' => 'update', 'table' => 'trainer_tips',
                'values' => ['tip_id' => $values[1], 'tip_text' => $values[0]], 'affected_rows' => $affected];
            return [[], $affected];
        }
        if (preg_match('/^INSERT INTO (trainer_tips|food_recommendations|member_progress|member_exercise_plans|trainer_sessions)\s*\(([^)]+)\)\s*VALUES\s*\(([?,\s]+)\)$/i', trim($sql), $match)) {
            $columns = array_map('trim', explode(',', $match[2]));
            same(count($columns), count($values), 'Mutation column/binding count');
            $this->mutations[] = ['operation' => 'insert', 'table' => strtolower($match[1]),
                'values' => array_combine($columns, $values)];
            ++$this->insert_id;
            return [[], 1];
        }
        throw new RuntimeException('Unstubbed prepared SQL (fail closed): ' . $sql);
    }
}

// Remove bootstrap statements with PHP tokens, not regex. Everything else is
// evaluated verbatim in this namespace, where input/DB/response/log stubs live.
function loadActualSource(string $path, int $expectedRequires): void
{
    $source = \file_get_contents($path);
    check($source !== false, 'Cannot read actual source: ' . $path);
    $tokens = token_get_all($source);
    $code = '';
    $removed = 0;
    for ($i = 0, $count = count($tokens); $i < $count; ++$i) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
            ++$removed;
            while (++$i < $count && $tokens[$i] !== ';') {}
            check($i < $count, 'Unterminated bootstrap include');
            $code .= ';';
            continue;
        }
        if (is_array($token) && in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG], true)) {
            $code .= $token[0] === T_CLOSE_TAG ? ';' : '';
            continue;
        }
        if (is_array($token) && $token[0] === T_INLINE_HTML) {
            check(trim($token[1]) === '', 'Unexpected inline endpoint output');
            continue;
        }
        $code .= is_array($token) ? $token[1] : $token;
    }
    same($expectedRequires, $removed, 'Unexpected bootstrap wiring: ' . basename($path));
    eval('namespace ' . __NAMESPACE__ . '; use \\DateTimeImmutable; use \\DateTimeZone; use \\Throwable; use \\Exception; ' . $code);
}

function fixture(array $options = []): array
{
    $now = trainerGymNow();
    $today = $now->format('Y-m-d');
    $booking = [
        'id' => 501, 'user_id' => 101, 'trainer_id' => 41, 'package_id' => 7,
        'status' => 'expired', 'booking_date' => $today . ' 06:00:00',
        'expires_at' => $now->modify('-1 day')->format('Y-m-d H:i:s'),
    ];
    if (isset($options['booking_day'])) {
        $booking['booking_date'] = $now->modify(sprintf('%+d days', $options['booking_day']))->format('Y-m-d') . ' 06:00:00';
    }
    $booking = array_replace($booking, $options['booking'] ?? []);
    $renewal = array_replace($booking, [
        'id' => 502, 'user_id' => 101, 'trainer_id' => 41, 'status' => 'verified',
        'booking_date' => $today, 'expires_at' => $now->modify('+30 days')->format('Y-m-d H:i:s'),
    ]);
    return [
        'profiles' => empty($options['missing_profile']) ? [7 => 41] : [],
        'packages' => [7 => ['name' => 'Fixture package', 'is_trainer_assisted' => $options['assisted'] ?? 1]],
        'package_trainers' => [7 => $options['package_trainers'] ?? [41]],
        'bookings' => [501 => $booking, 502 => $renewal, 503 => array_replace($renewal, ['id' => 503])],
        'tips' => [
            77 => ['trainer_id' => 41, 'member_id' => 101, 'tip_text' => 'Keep moving'],
            88 => ['trainer_id' => 42, 'member_id' => 101, 'tip_text' => 'Other coach'],
        ],
    ];
}

function setEnvironment(array $options = []): void
{
    global $environment;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $now = trainerGymNow();
    $environment = [
        'db' => new MemoryDB(fixture($options)), 'auth_checks' => 0, 'logs' => [], 'notifications' => [],
        'notification_failure' => $options['notification_failure'] ?? '',
        'input' => array_replace([
            'booking_id' => 501, 'member_id' => 101, 'tip_text' => 'Keep moving',
            'meal_type' => 'lunch', 'food_items' => 'Rice and chicken', 'calories' => 400,
            'logged_at' => $now->format('Y-m-d'), 'weight' => 70, 'height' => 170,
            'session_date' => $now->modify(sprintf('%+d days', $options['session_day'] ?? 0))->format('Y-m-d'),
            'session_time' => '08:00', 'duration' => 60,
            'exercises' => [['id' => 9, 'sets' => 3, 'reps' => '10', 'notes' => 'Controlled']],
        ], $options['input'] ?? []),
    ];
    // Session expects IDs, whereas the plan endpoint expects exercise objects.
    if (($options['endpoint'] ?? '') === 'save-session.php') {
        $environment['input']['exercises'] = [9];
    }
}

function denied(callable $action, int $code, string $label): void
{
    global $environment;
    try {
        $action();
    } catch (ResponseSignal $signal) {
        same(false, $signal->response['success'], $label);
        same($code, $signal->response['code'], $label . ' response code');
        same([], $environment['db']->mutations, $label . ' must not mutate');
        same([], $environment['db']->transactions, $label . ' must not start a transaction');
        return;
    }
    throw new RuntimeException($label . ': expected denial');
}

function helperTests(): void
{
    global $environment;
    $timezone = date_default_timezone_get();
    $midnight = new DateTimeImmutable('2026-10-15T16:00:00Z');
    same('2026-10-16', trainerGymToday($midnight), 'UTC instant at Manila midnight');
    same('2026-10-15', trainerGymToday($midnight->modify('-1 second')), 'Second before Manila midnight');
    same('Asia/Manila', trainerGymNow($midnight)->getTimezone()->getName(), 'Explicit gym timezone');
    same('00:00:00', trainerGymNow($midnight)->format('H:i:s'), 'Manila midnight clock');
    same($timezone, date_default_timezone_get(), 'Helper does not change global timezone');
    same(sqlKey(ASSIGNMENT_SQL), sqlKey(trainerBookingAssignmentSql()), 'Assignment SQL contract');
    same(sqlKey(WRITABLE_SQL), sqlKey(trainerBookingWritableSql()), 'Writable SQL contract');

    $instant = new DateTimeImmutable('2026-10-16T04:00:00Z'); // Manila noon.
    $cases = [
        ['expired same day', 'expired', '2026-10-16 06:00:00', '2026-10-15 23:59:59', true],
        ['expired yesterday', 'expired', '2026-10-15', '2026-10-15 23:59:59', false],
        ['expired future day', 'expired', '2026-10-17', '2026-10-15 23:59:59', false],
        ['verified stale same day', 'verified', '2026-10-16', '2026-10-16 11:59:59', true],
        ['verified stale previous day', 'verified', '2026-10-15', '2026-10-16 11:59:59', false],
        ['verified stale future day', 'verified', '2026-10-17', '2026-10-16 11:59:59', false],
        ['verified unexpired historical', 'verified', '2026-10-01', '2026-10-17 00:00:00', true],
        ['verified exact expiry', 'verified', '2026-10-01', '2026-10-16 12:00:00', true],
        ['verified null expiry', 'verified', '2026-10-01', null, true],
        ['expired null same day', 'expired', '2026-10-16', null, true],
        ['expired null historical', 'expired', '2026-10-01', null, false],
        ['rejected today', 'rejected', '2026-10-16', null, false],
        ['pending today', 'pending', '2026-10-16', '2099-01-01 00:00:00', false],
        ['upgraded today', 'upgraded', '2026-10-16', null, false],
        ['missing status', '', '2026-10-16', null, false],
    ];
    foreach ($cases as [$label, $status, $date, $expiry, $expected]) {
        $booking = ['status' => $status, 'booking_date' => $date, 'expires_at' => $expiry];
        same($expected, trainerBookingIsWritable($booking, $instant), $label . ' pure helper');
        $db = new MemoryDB(fixture(['booking' => $booking]));
        $stmt = $db->prepare(BOOKING_SELECT . trainerBookingAssignmentSql() . ' AND ' . trainerBookingWritableSql());
        $id = 501; $trainer = 41;
        $timestamp = trainerGymNow($instant)->format('Y-m-d H:i:s');
        $day = trainerGymToday($instant);
        $stmt->bind_param('iiiss', $id, $trainer, $trainer, $timestamp, $day);
        $stmt->execute();
        same($expected, $stmt->get_result()->fetch_assoc() !== null, $label . ' independent SQL stub');
    }
    same(true, trainerBookingIsWritable(['status' => 'expired', 'booking_date' => '2026-10-16'], $midnight), 'Same-day opens at Manila midnight');
    same(false, trainerBookingIsWritable(['status' => 'expired', 'booking_date' => '2026-10-16'], $midnight->modify('-1 second')), 'Not same-day in preceding UTC second');

    foreach (['2024-02-29', '2026-10-16', '2026-12-31'] as $date) {
        same(true, trainerValidCalendarDate($date), 'Valid calendar date ' . $date);
    }
    foreach (['2026-02-29', '2026-02-30', '2026-04-31', '2026-00-10', '2026-13-01', '2026-10-00',
        '2026-10-32', '2026-1-01', '2026-10-16 00:00:00', "2026-10-16\n", '', null, 20261016, []] as $date) {
        same(false, trainerValidCalendarDate($date), 'Invalid calendar date ' . var_export($date, true));
    }

    foreach ([['booking' => ['trainer_id' => null]], ['package_trainers' => []], []] as $options) {
        setEnvironment($options);
        same(41, requireTrainerProfileId(getDBConnection(), 7), 'Real profile guard');
        same(501, requireWritableTrainerBooking(getDBConnection(), 501, 41, 101)['id'], 'Real writable guard selects exact booking');
        same(501, $environment['db']->queries[1]['values'][0], 'Lookup binds requested ID');
        same([501, 41, 41], $environment['db']->queries[1]['values'], 'Lookup binds both trainer positions');
    }
    foreach ([
        ['explicit foreign trainer wins over package', ['booking' => ['trainer_id' => 42]], 501, 101, 403],
        ['fallback requires mapped trainer', ['booking' => ['trainer_id' => null], 'package_trainers' => []], 501, 101, 403],
        ['wrong member', [], 501, 202, 403],
        ['zero member', [], 501, 0, 403],
        ['missing requested ID despite renewals', [], 99999, 101, 403],
        ['required ID', [], 0, 101, 400],
        ['negative ID', [], -1, 101, 400],
        ['old expired booking despite two renewals', ['booking_day' => -1], 501, 101, 403],
        ['future expired booking despite two renewals', ['booking_day' => 1], 501, 101, 403],
        ['stale verified old booking', ['booking_day' => -1, 'booking' => ['status' => 'verified']], 501, 101, 403],
        ['missing joined package', ['booking' => ['package_id' => 999]], 501, 101, 403],
    ] as [$label, $options, $id, $member, $code]) {
        setEnvironment($options);
        denied(fn() => requireWritableTrainerBooking(getDBConnection(), $id, 41, $member), $code, $label);
    }
    setEnvironment(['booking_day' => -1]);
    same(501, findTrainerBooking(getDBConnection(), 501, 41)['id'], 'Historical exact lookup remains readable');
    same(502, requireWritableTrainerBooking(getDBConnection(), 502, 41, 101)['id'], 'Explicitly selected renewal may be writable');
    same(503, requireWritableTrainerBooking(getDBConnection(), 503, 41, 101)['id'], 'Second explicitly selected renewal');
    setEnvironment(['missing_profile' => true]);
    same(null, findTrainerProfileId(getDBConnection(), 7), 'Missing profile lookup safely returns null');
    denied(fn() => requireTrainerProfileId(getDBConnection(), 7), 403, 'Missing profile guard');
}

function childEndpoint(string $endpoint): never
{
    global $environment;
    check(in_array($endpoint, ENDPOINTS, true), 'Unknown endpoint');
    $options = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    setEnvironment(array_replace($options, ['endpoint' => $endpoint]));
    try {
        loadActualSource(__DIR__ . '/api/trainers/' . $endpoint, 3);
        throw new RuntimeException('Endpoint returned without sendResponse');
    } catch (ResponseSignal $signal) {
        $output = $signal->response + [
            'mutations' => $environment['db']->mutations,
            'transactions' => $environment['db']->transactions,
            'queries' => $environment['db']->queries,
            'notifications' => $environment['notifications'], 'logs' => $environment['logs'],
            'auth_checks' => $environment['auth_checks'],
        ];
    } finally {
        restore_error_handler();
    }
    print json_encode($output, JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}

function runEndpoint(string $endpoint, array $options): array
{
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-n', '-d', 'date.timezone=UTC', __FILE__, '--endpoint', $endpoint],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
    check(is_resource($process), 'Could not start isolated PHP child');
    $input = json_encode($options, JSON_THROW_ON_ERROR);
    same(strlen($input), fwrite($pipes[0], $input), 'Write complete in-memory child fixture');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    same(0, $status, $endpoint . ' child exit: ' . $stderr . $stdout);
    same('', $stderr, $endpoint . ' child must not emit warnings/errors');
    return json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
}

function endpointCase(string $endpoint, string $label, array $options, bool $success, int $code = 200): array
{
    $result = runEndpoint($endpoint, $options);
    $prefix = $endpoint . ': ' . $label;
    same($success, $result['success'], $prefix . ' response');
    same($code, $result['code'], $prefix . ' code');
    same(1, $result['auth_checks'], $prefix . ' trainer authentication wiring');
    if (!$success) {
        same([], $result['mutations'], $prefix . ' no mutation before denial');
        same([], $result['transactions'], $prefix . ' no transaction before denial');
        same([], $result['notifications'], $prefix . ' no notification before denial');
    } else {
        check(count($result['mutations']) > 0, $prefix . ' actually mutated fake DB');
        same(in_array($endpoint, ['log-progress.php', 'save-member-plan.php'], true) ? ['begin', 'commit'] : [],
            $result['transactions'], $prefix . ' transaction committed, never rolled back');
        foreach ($result['mutations'] as $mutation) {
            if (isset($mutation['values']['booking_id'])) {
                same($options['input']['booking_id'] ?? 501, $mutation['values']['booking_id'], $prefix . ' mutation targets selected booking');
            }
        }
        foreach ($result['notifications'] as $notification) {
            check($notification['mutations_before'] > 0, $prefix . ' notify only after saving');
        }
    }
    return $result;
}

function endpointTests(): int
{
    $cases = 0;
    $memberEndpoints = ['save-tip.php', 'save-food.php', 'save-session.php'];
    foreach (ENDPOINTS as $endpoint) {
        foreach ([
            ['same-day expired explicit assignment', []],
            ['same-day expired NULL-package fallback', ['booking' => ['trainer_id' => null]]],
            ['explicit assignment needs no package mapping', ['package_trainers' => []]],
            ['same-day stale verified', ['booking' => ['status' => 'verified']]],
            ['historical verified without expiry', ['booking_day' => -1, 'booking' => ['status' => 'verified', 'expires_at' => null]]],
        ] as [$label, $options]) {
            endpointCase($endpoint, $label, $options, true);
            ++$cases;
        }
        foreach ([
            ['foreign explicit trainer cannot use fallback', ['booking' => ['trainer_id' => 42]], 403],
            ['NULL assignment without package mapping', ['booking' => ['trainer_id' => null], 'package_trainers' => []], 403],
            ['previous-day expired despite renewals', ['booking_day' => -1], 403],
            ['future-day expired despite renewals', ['booking_day' => 1], 403],
            ['previous-day stale verified despite renewals', ['booking_day' => -1, 'booking' => ['status' => 'verified']], 403],
            ['missing exact booking despite renewals', ['input' => ['booking_id' => 99999]], 403],
            ['missing trainer profile', ['missing_profile' => true], 403],
            ['missing booking ID', ['input' => ['booking_id' => 0]], 400],
            ['rejected booking', ['booking' => ['status' => 'rejected']], 403],
            ['pending booking', ['booking' => ['status' => 'pending']], 403],
            ['upgraded booking', ['booking' => ['status' => 'upgraded']], 403],
        ] as [$label, $options, $code]) {
            endpointCase($endpoint, $label, $options, false, $code);
            ++$cases;
        }
        if (in_array($endpoint, $memberEndpoints, true)) {
            endpointCase($endpoint, 'selected booking belongs to another member', ['booking' => ['user_id' => 202]], false, 403);
            endpointCase($endpoint, 'caller supplies another member', ['input' => ['member_id' => 202]], false, 403);
            $cases += 2;
        } else {
            // These endpoints derive the member solely from booking_id, not from a
            // separate caller-supplied member_id. Package gates are tested below.
            endpointCase($endpoint, 'member is derived from selected booking', ['input' => ['member_id' => 202]], true);
            ++$cases;
        }
        $allowed = $endpoint === 'log-progress.php'; // Existing progress design has no package gate.
        endpointCase($endpoint, 'non-assisted package', ['assisted' => 0], $allowed,
            $allowed || $endpoint === 'save-member-plan.php' ? 200 : 403);
        ++$cases;
    }

    $result = endpointCase('save-tip.php', 'unchanged own historical tip succeeds with affected_rows=0',
        ['booking_day' => -1, 'input' => ['tip_id' => 77, 'booking_id' => 0]], true);
    same(0, $result['mutations'][0]['affected_rows'], 'Unchanged own tip really reports zero affected rows');
    endpointCase('save-tip.php', 'another trainer tip denied', ['input' => ['tip_id' => 88]], false, 403);
    endpointCase('save-tip.php', 'own tip wrong member denied', ['input' => ['tip_id' => 77, 'member_id' => 202]], false, 403);
    endpointCase('save-tip.php', 'nonexistent tip denied', ['input' => ['tip_id' => 999]], false, 403);
    $cases += 4;

    foreach (['save-tip.php', 'save-food.php', 'save-session.php'] as $endpoint) {
        $result = endpointCase($endpoint, 'notification exception cannot fail saved recommendation', ['notification_failure' => 'exception'], true);
        same(1, count($result['notifications']), $endpoint . ' actually attempts notification');
        same(1, count($result['logs']), $endpoint . ' captures notification failure without disk logging');
        ++$cases;
    }
    foreach (['save-tip.php', 'save-food.php'] as $endpoint) {
        endpointCase($endpoint, 'notification Error is also contained by shared wrapper', ['notification_failure' => 'error'], true);
        ++$cases;
    }
    foreach ([-1, 1] as $offset) {
        endpointCase('save-session.php', 'expired visit cannot schedule on another day', ['session_day' => $offset], false, 403);
        ++$cases;
    }
    foreach (['2026-02-30', '2026-02-29', '2026-13-01', '2026-10-16 00:00:00'] as $date) {
        endpointCase('save-session.php', 'invalid session calendar date ' . $date, ['input' => ['session_date' => $date]], false, 400);
        endpointCase('log-progress.php', 'invalid progress calendar date ' . $date, ['input' => ['logged_at' => $date]], false, 400);
        $cases += 2;
    }
    return $cases;
}

try {
    loadActualSource(__DIR__ . '/api/trainers/booking-access.php', 0);
    if (($argv[1] ?? '') === '--endpoint') {
        childEndpoint($argv[2] ?? '');
    }
    helperTests();
    $cases = endpointTests();
    print "PASS: $assertions assertions; $cases isolated actual-endpoint cases. No application bootstrap or external writes.\n";
    print "Scope: in-memory SQL contract simulation, not MySQL integration; progress/plan use booking-derived members; progress has no entitlement gate.\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
