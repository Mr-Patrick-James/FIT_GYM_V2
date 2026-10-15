<?php
declare(strict_types=1);

namespace TrainerClientHistoryRegression;

use DateTimeZone;
use Error;
use ErrorException;
use RuntimeException;
use Throwable;

// CLI-only, fixed fixtures, one -n child. Never require app config/session or an endpoint.
if (PHP_SAPI !== 'cli') {
    exit;
}

const FIXED_NOW = '2026-10-16T04:00:00Z'; // Noon in Manila; no midnight/expiry race.
const GYM_DATE = '2026-10-16';
const HISTORY_SQL = "b.status IN ('verified', 'expired', 'upgraded')";
const ASSIGNMENT_SQL = '(b.trainer_id = ? OR (b.trainer_id IS NULL AND EXISTS (
    SELECT 1 FROM package_trainers pt WHERE pt.package_id = b.package_id AND pt.trainer_id = ?)))';
const WRITABLE_SQL = "((b.status = 'verified' AND (b.expires_at IS NULL OR b.expires_at >= ?))
    OR (b.status IN ('verified', 'expired') AND DATE(b.booking_date) = ?))";
// Independent expected SELECT: catches latest-only grouping, lost IDs/fields and changed joins.
const CLIENT_SELECT = 'SELECT b.id as booking_id,
    COALESCE(u.id, 0) as user_id, COALESCE(u.name, b.name) as name,
    COALESCE(u.email, b.email) as email, COALESCE(u.contact, b.contact) as contact,
    COALESCE(u.address, \'Walk-in Customer\') as address,
    COALESCE(u.created_at, b.created_at) as created_at,
    p.name as package_name, p.duration, p.is_trainer_assisted,
    b.expires_at, b.status, b.verified_at, b.booking_date,
    uq.primary_goal, uq.exercise_history, uq.workout_days_per_week,
    CASE WHEN b.user_id IS NULL THEN 1 ELSE 0 END as is_walkin
    FROM bookings b LEFT JOIN users u ON b.user_id = u.id
    JOIN packages p ON b.package_id = p.id
    LEFT JOIN user_questionnaire uq ON u.id = uq.user_id WHERE ';
const CLIENT_ORDER = ' ORDER BY b.booking_date DESC, COALESCE(b.verified_at, b.created_at) DESC, b.id DESC';
const HISTORY_WARNING = 'Package history - view saved records here. New updates require an active or same-day booking assigned to you.';
const TODAY_WARNING = 'Same-day gym booking - trainer updates are available today (Philippine time)';
const WALKIN_WARNING = 'Walk-in customer - has limited access to online features';

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

// Inject only the clock constructor, not the helper's rules or row transformation.
final class FixedClock extends \DateTimeImmutable
{
    public function __construct(string $datetime = 'now', ?DateTimeZone $timezone = null)
    {
        parent::__construct($datetime === 'now' ? FIXED_NOW : $datetime, $timezone);
    }
}

// Error escapes an endpoint catch(Exception) rather than becoming a false failure.
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
    global $environment;
    return ['id' => $environment['user_id'], 'name' => 'Offline Coach', 'role' => 'trainer'];
}

function getDBConnection(): MemoryDB
{
    global $environment;
    return $environment['db'];
}

function error_log(string $message): bool
{
    global $environment;
    $environment['logs'][] = $message; // Never write a persistent debug log.
    return true;
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
    private string $types = '';
    private array $bindings = [];
    private array $rows = [];

    public function __construct(private MemoryDB $db, private string $sql) {}

    public function bind_param(string $types, &...$values): bool
    {
        same(substr_count($this->sql, '?'), strlen($types), 'SQL placeholder/type count');
        same(strlen($types), count($values), 'SQL parameter count');
        $this->types = $types;
        foreach ($values as &$value) {
            $this->bindings[] =& $value;
        }
        unset($value);
        return true;
    }

    public function execute(): bool
    {
        $this->rows = $this->db->select($this->sql, array_values($this->bindings), $this->types);
        return true;
    }

    public function get_result(): MemoryResult { return new MemoryResult($this->rows); }
    public function close(): void {}
}

final class MemoryDB
{
    public array $queries = [];
    public array $schemaStubs = [];
    public bool $closed = false;

    public function __construct(public array $fixture) {}

    public function prepare(string $sql): MemoryStatement
    {
        return new MemoryStatement($this, $sql);
    }

    public function query(string $sql): MemoryResult|bool
    {
        // Only this known endpoint DDL is accepted, recorded, and NOT executed.
        if (preg_match('/^CREATE TABLE IF NOT EXISTS `user_questionnaire`\s*\(/', trim($sql))) {
            same(1, substr_count($sql, ';'), 'Only one in-memory DDL statement');
            $this->schemaStubs[] = $sql;
            return true;
        }
        if (sqlKey($sql) === 'select * from trainers where user_id = 7') {
            $this->queries[] = ['sql' => $sql, 'values' => [], 'types' => ''];
            return new MemoryResult([]);
        }
        throw new RuntimeException('Unstubbed query (fail closed): ' . $sql);
    }

    public function select(string $sql, array $values, string $types): array
    {
        $this->queries[] = compact('sql', 'values', 'types');
        if (sqlKey($sql) === 'select id from trainers where user_id = ?') {
            same('i', $types, 'Trainer profile bind type');
            same([$this->fixture['user_id']], $values, 'Authenticated user profile lookup');
            $id = $this->fixture['profiles'][$values[0]] ?? null;
            return $id === null ? [] : [['id' => $id]];
        }

        // Validate the ACTUAL emitted SQL, not just helper output or source strings.
        $expected = CLIENT_SELECT . HISTORY_SQL . ' AND ' . ASSIGNMENT_SQL . CLIENT_ORDER;
        same(sqlKey($expected), sqlKey($sql), 'Exact client history SELECT contract');
        check(preg_match('/\bWHERE\b(.*)\bORDER BY\b/is', $sql, $match) === 1, 'Client WHERE exists');
        same(sqlKey(HISTORY_SQL . ' AND ' . ASSIGNMENT_SQL), sqlKey($match[1]), 'History and assignment only in WHERE');
        check(!preg_match('/expires_at|booking_date|\bdate\s*\(|\bnow\s*\(/i', $match[1]), 'History WHERE has no expiry/day predicate');
        same('ii', $types, 'Client SELECT binds ii, without timestamp/day parameters');
        $trainer = $this->fixture['profiles'][$this->fixture['user_id']];
        same([$trainer, $trainer], $values, 'Exact trainer ID in both assignment positions');

        $rows = [];
        foreach ($this->fixture['bookings'] as $booking) {
            // Independent oracle, intentionally NOT calling any implementation helper.
            if (!in_array($booking['status'], ['verified', 'expired', 'upgraded'], true)) {
                continue;
            }
            $assigned = $booking['trainer_id'] !== null
                ? $booking['trainer_id'] === $values[0]
                : in_array($values[1], $this->fixture['package_trainers'][$booking['package_id']] ?? [], true);
            if (!$assigned) {
                continue;
            }
            $row = $booking;
            $row['booking_id'] = $row['id'];
            unset($row['id'], $row['trainer_id'], $row['package_id']);
            $row['is_walkin'] = $row['user_id'] === null ? 1 : 0;
            $row['user_id'] ??= 0;
            $rows[] = $row; // Raw SELECT-shaped rows; no visibility flags/warnings here.
        }
        usort($rows, static function (array $a, array $b): int {
            return strcmp($b['booking_date'], $a['booking_date'])
                ?: strcmp($b['verified_at'] ?? $b['created_at'], $a['verified_at'] ?? $a['created_at'])
                ?: $b['booking_id'] <=> $a['booking_id'];
        });
        return $rows;
    }

    public function close(): void { $this->closed = true; }
}

function fixture(): array
{
    $base = [
        'id' => 0, 'user_id' => 101, 'trainer_id' => 41, 'package_id' => 7,
        'name' => 'Offline Member', 'email' => 'fixture@example.invalid', 'contact' => 'fixture',
        'address' => 'Fixture address', 'created_at' => '2026-10-01 08:00:00',
        'package_name' => 'Fixture package', 'duration' => 30, 'is_trainer_assisted' => 1,
        'status' => 'expired', 'expires_at' => '2026-10-15 23:59:59',
        'verified_at' => '2026-10-01 08:30:00', 'booking_date' => '2026-10-15 06:00:00',
        'primary_goal' => 'Stay fit / general health', 'exercise_history' => 'Beginner',
        'workout_days_per_week' => '3-4 days',
    ];
    $today = '2026-10-16 06:00:00';
    $future = '2026-11-16 23:59:59';
    $variants = [
        501 => [], // Old booking and two renewals for the same exact member.
        502 => ['status' => 'verified', 'expires_at' => $future, 'booking_date' => $today],
        503 => ['status' => 'verified', 'expires_at' => null, 'booking_date' => $today],
        504 => ['trainer_id' => 42, 'status' => 'verified', 'expires_at' => $future, 'booking_date' => '2026-10-17 06:00:00'],
        505 => ['status' => 'upgraded', 'expires_at' => $future],
        506 => ['booking_date' => $today],
        507 => ['status' => 'verified', 'expires_at' => null],
        508 => ['status' => 'verified', 'expires_at' => $future],
        509 => ['status' => 'verified'],
        510 => ['status' => 'pending', 'booking_date' => $today, 'expires_at' => $future],
        511 => ['status' => 'rejected', 'booking_date' => $today, 'expires_at' => null],
        512 => ['trainer_id' => 42], // Package 7 maps 41, but explicit 42 wins.
        513 => ['trainer_id' => null, 'status' => 'verified', 'expires_at' => $future],
        514 => ['trainer_id' => null, 'package_id' => 8],
        515 => ['package_id' => 8], // Explicit 41 needs no package mapping to 41.
        516 => ['trainer_id' => 0], // Zero is not NULL: must not use fallback.
        517 => ['user_id' => null, 'name' => 'Same Walk-in', 'address' => 'Walk-in Customer'],
        518 => ['user_id' => null, 'name' => 'Same Walk-in', 'address' => 'Walk-in Customer', 'booking_date' => $today],
        519 => ['status' => 'upgraded', 'expires_at' => $future, 'booking_date' => $today],
        520 => ['status' => 'verified', 'booking_date' => $today],
        521 => ['status' => 'verified', 'expires_at' => '2026-10-16 12:00:00'],
        522 => ['expires_at' => $future], // Actual expired status wins even with future expiry.
        523 => ['expires_at' => null],
        524 => ['status' => 'upgraded'],
        525 => ['booking_date' => '2026-10-17 06:00:00'],
        526 => ['user_id' => null, 'status' => 'verified', 'expires_at' => $future, 'address' => 'Walk-in Customer'],
        527 => ['trainer_id' => null, 'package_id' => 9],
    ];
    $bookings = [];
    foreach ($variants as $id => $changes) {
        $bookings[] = array_replace($base, ['id' => $id, 'user_id' => $id <= 504 ? 101 : $id + 1000], $changes);
    }
    return ['user_id' => 7, 'profiles' => [7 => 41, 8 => 42],
        'package_trainers' => [7 => [41], 8 => [42], 9 => []], 'bookings' => $bookings];
}

// Token stripping with an exact bootstrap allowlist; evaluate everything else verbatim.
function loadActualSource(string $path, array $allowedRequires): void
{
    $source = \file_get_contents($path);
    check($source !== false, 'Cannot read source: ' . $path);
    $tokens = token_get_all($source);
    $code = '';
    $removed = [];
    for ($i = 0, $count = count($tokens); $i < $count; ++$i) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
            $statement = $token[1];
            while (++$i < $count && $tokens[$i] !== ';') {
                $statement .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
            }
            check($i < $count, 'Unterminated bootstrap include');
            $removed[] = trim($statement) . ';';
            $code .= ';';
            continue;
        }
        if (is_array($token) && in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG], true)) {
            $code .= $token[0] === T_CLOSE_TAG ? ';' : '';
            continue;
        }
        if (is_array($token) && $token[0] === T_INLINE_HTML) {
            same('', trim($token[1]), 'No inline endpoint output');
            continue;
        }
        if (is_array($token) && $token[0] === T_EXIT) {
            throw new RuntimeException('Actual source must respond through the isolated response stub');
        }
        $code .= is_array($token) ? $token[1] : $token;
    }
    same($allowedRequires, $removed, 'Exact stripped bootstrap wiring: ' . basename($path));
    eval('namespace ' . __NAMESPACE__ . '; use \\' . __NAMESPACE__ . '\\FixedClock as DateTimeImmutable; '
        . 'use \\DateTimeZone; use \\Throwable; use \\Exception; ' . $code);
}

function runEndpoint(array $fixture): array
{
    global $environment;
    $environment = ['db' => new MemoryDB($fixture), 'user_id' => $fixture['user_id'], 'auth_checks' => 0, 'logs' => []];
    $_SESSION = ['user_id' => $fixture['user_id'], 'role' => 'trainer'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $level = ob_get_level();
    try {
        loadActualSource(__DIR__ . '/api/trainers/get-clients.php', [
            "require_once '../config.php';", "require_once '../session.php';",
            "require_once __DIR__ . '/booking-access.php';",
        ]);
        throw new RuntimeException('Actual endpoint returned without sendResponse');
    } catch (ResponseSignal $signal) {
        same('', (string)ob_get_contents(), 'Endpoint emits no stray output');
        same(1, $environment['auth_checks'], 'Actual trainer auth guard invoked');
        return $signal->response;
    } finally {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        $_SESSION = [];
    }
}

function indexClients(array $response, array $ids): array
{
    same(true, $response['success'], 'Clients response success');
    same(200, $response['code'], 'Clients response code');
    same('Clients retrieved successfully', $response['message'], 'Actual endpoint success message');
    $indexed = [];
    foreach ($response['data'] as $row) {
        check(!isset($indexed[$row['booking_id']]), 'No duplicate/collapsed booking IDs');
        $indexed[$row['booking_id']] = $row;
    }
    ksort($indexed);
    same($ids, array_keys($indexed), 'Exact independently expected visible booking IDs');
    return $indexed;
}

function regressionTests(): void
{
    global $environment;
    $timezone = date_default_timezone_get();
    loadActualSource(__DIR__ . '/api/trainers/booking-access.php', []);
    same('2026-10-16 12:00:00', trainerGymNow()->format('Y-m-d H:i:s'), 'Fixed actual helper clock in Manila');
    same(sqlKey(HISTORY_SQL), sqlKey(trainerBookingHistorySql()), 'Paid history helper contract');
    same(sqlKey(ASSIGNMENT_SQL), sqlKey(trainerBookingAssignmentSql()), 'Assignment helper unchanged');
    same(sqlKey(WRITABLE_SQL), sqlKey(trainerBookingWritableSql()), 'Write SQL helper unchanged');

    $fixture = fixture();
    $clients = indexClients(runEndpoint($fixture), [501, 502, 503, 505, 506, 507, 508, 509,
        513, 515, 517, 518, 519, 520, 521, 522, 523, 524, 525, 526]);
    // Explicit expectations, NOT derived from trainerBookingIsWritable or endpoint code.
    // booking ID => [actually expired, writable, membership state, optional warning].
    $expected = [
        501 => [true, false, 'expired', HISTORY_WARNING],
        502 => [false, true, 'active', null],
        503 => [false, true, 'active', null],
        505 => [false, false, 'upgraded', HISTORY_WARNING],
        506 => [true, true, 'expired', TODAY_WARNING],
        507 => [false, true, 'active', null],
        508 => [false, true, 'active', null],
        509 => [true, false, 'expired', HISTORY_WARNING],
        513 => [false, true, 'active', null],
        515 => [true, false, 'expired', HISTORY_WARNING],
        517 => [true, false, 'expired', HISTORY_WARNING],
        518 => [true, true, 'expired', TODAY_WARNING],
        519 => [false, false, 'upgraded', HISTORY_WARNING],
        520 => [true, true, 'expired', TODAY_WARNING],
        521 => [false, true, 'active', null],
        522 => [true, false, 'expired', HISTORY_WARNING],
        523 => [true, false, 'expired', HISTORY_WARNING],
        524 => [true, false, 'upgraded', HISTORY_WARNING],
        525 => [true, false, 'expired', HISTORY_WARNING],
        526 => [false, true, 'active', WALKIN_WARNING],
    ];
    foreach ($expected as $id => [$expired, $writable, $state, $warning]) {
        $row = $clients[$id];
        same($expired, $row['is_expired'], "Booking $id actual expiry/status flag");
        same($writable, $row['can_manage'], "Booking $id actual unchanged write helper result");
        same($state, $row['membership_state'], "Booking $id membership label");
        same(GYM_DATE, $row['gym_date'], "Booking $id gym calendar date");
        same(in_array($id, [517, 518, 526], true), $row['is_walkin'], "Booking $id boolean walk-in flag");
        same($warning, $row['status_warning'] ?? null, "Booking $id warning precedence");
        same($warning !== null, array_key_exists('status_warning', $row), "Booking $id optional warning presence");
        $raw = $fixture['bookings'][$id - 501];
        foreach (['status', 'expires_at', 'booking_date', 'name', 'package_name', 'is_trainer_assisted'] as $field) {
            same($raw[$field], $row[$field], "Booking $id preserves raw $field");
        }
    }
    same([101, 101, 101], [$clients[501]['user_id'], $clients[502]['user_id'], $clients[503]['user_id']], 'History and both exact renewal IDs survive for one member');
    same([0, 0], [$clients[517]['user_id'], $clients[518]['user_id']], 'Walk-ins have zero member IDs but distinct booking IDs');
    same($clients[517]['name'], $clients[518]['name'], 'Same-name walk-ins must not collapse');
    same(1, count($environment['db']->schemaStubs), 'Known CREATE TABLE captured only in memory');
    same(2, count($environment['db']->queries), 'Only profile and history SELECT executed in memory');
    same(true, $environment['db']->closed, 'Actual endpoint closes fake connection');

    // The new trainer sees their booking, not the former trainer's historical rows.
    $other = $fixture;
    $other['user_id'] = 8;
    $otherClients = indexClients(runEndpoint($other), [504, 512, 514]);
    same(101, $otherClients[504]['user_id'], 'Other-trainer renewal belongs to the same member');
    same(true, $otherClients[504]['can_manage'], 'Other trainer can manage their own active renewal');

    $empty = $fixture;
    $empty['bookings'] = [];
    indexClients(runEndpoint($empty), []);

    $missing = $fixture;
    $missing['profiles'] = [];
    $response = runEndpoint($missing);
    same(false, $response['success'], 'Missing profile fails safely');
    same(404, $response['code'], 'Missing profile response code');
    same(null, $response['data'], 'Missing profile has no clients');
    same([], $environment['db']->schemaStubs, 'Missing profile never reaches DDL');
    same(false, $environment['db']->closed, 'Missing profile returns before successful-query close');
    same($timezone, date_default_timezone_get(), 'Actual helper/endpoint do not change global timezone');
}

function isolatedChild(): void
{
    // -n prevents php.ini auto-prepends/extensions/log settings from reaching the app.
    same(false, php_ini_loaded_file(), 'Child ignores php.ini');
    same(false, extension_loaded('mysqli'), 'No live mysqli connection available');
    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        regressionTests();
    } finally {
        restore_error_handler();
    }
}

// One child, 27 fixture bookings, four endpoint executions, 15-second wall-clock bound.
function runIsolated(): void
{
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-n', '-d', 'date.timezone=UTC', '-d', 'max_execution_time=12',
        __FILE__, '--isolated'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
    check(is_resource($process), 'Cannot start isolated CLI child');
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 15;
    $timedOut = false;
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        if (microtime(true) >= $deadline) {
            $timedOut = true;
            proc_terminate($process);
            break;
        }
        usleep(10000); // Bounded external-process poll, not a retry loop.
    } while (true);
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($process);
    check(!$timedOut, 'Isolated CLI child exceeded 15 seconds');
    same(0, $status['exitcode'] >= 0 ? $status['exitcode'] : $closed, 'Child exit: ' . $stderr . $stdout);
    same('', $stderr, 'No child warnings/errors');
    check(str_starts_with($stdout, 'PASS:'), 'Child must explicitly report passing assertions');
    print $stdout;
}

try {
    if (($argv[1] ?? '') === '--isolated') {
        isolatedChild();
        print "PASS: $assertions assertions; 27 fixture bookings; 4 actual-endpoint runs; no application bootstrap or external writes.\n";
        print "Scope: independent in-memory SELECT/SQL contracts and actual row transformation; not MySQL, HTTP/session authentication, or save-endpoint integration.\n";
    } else {
        same(1, count($argv), 'Usage: php test-trainer-client-history.php');
        runIsolated();
    }
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
