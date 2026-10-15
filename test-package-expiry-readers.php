<?php
declare(strict_types=1);

namespace PackageExpiryReaderRegression;

use DateTimeZone;
use Error;
use RuntimeException;
use Throwable;

// CLI-only/offline: php -n test-package-expiry-readers.php
// Evaluate actual endpoint/helper source; never include config/session or load mysqli.
// SQL contracts and DATETIME string comparisons only: no real TIMESTAMP/session conversion.
if (PHP_SAPI !== 'cli') exit;
const MYSQLI_ASSOC = 1;
const BASE_URL = 'https://offline.example.test';
const ENDPOINTS = ['bookings/get-all.php', 'bookings/check-status.php', 'walkin/get-all.php'];
const BOOKING_SELECT = 'SELECT b.*, b.package_name as booking_package_name, u.name as user_name, p.name as pkg_name, p.duration, p.price as pkg_price, p.is_trainer_assisted, b.expires_at, t.name as trainer_name FROM bookings b LEFT JOIN users u ON b.user_id = u.id LEFT JOIN packages p ON b.package_id = p.id LEFT JOIN trainers t ON b.trainer_id = t.id';
const WALKIN_SELECT = 'SELECT b.*, p.name as package_name, p.duration FROM bookings b LEFT JOIN packages p ON b.package_id = p.id WHERE b.user_id IS NULL';
const CURRENT_SELECT = "SELECT b.*, p.price as current_price, p.duration as current_duration FROM bookings b LEFT JOIN packages p ON b.package_id = p.id WHERE b.user_id = ? AND b.status IN ('verified', 'pending') ORDER BY b.created_at DESC LIMIT 1";
const UPGRADES_SELECT = 'SELECT p.* FROM packages p WHERE p.is_active = 1 AND p.price > ? ORDER BY p.price ASC';
const EXPIRE_ALL = "UPDATE bookings SET status = 'expired' WHERE status = 'verified' AND expires_at IS NOT NULL AND expires_at < ?";
const EXPIRE_WALKIN = "UPDATE bookings SET status = 'expired' WHERE user_id IS NULL AND status = 'verified' AND expires_at IS NOT NULL AND expires_at < ?";

$assertions = 0;
$cases = 0;
$failures = [];
$environment = ['clock' => '2026-12-31T15:59:59Z'];

function check(bool $condition, string $message): void
{
    global $assertions;
    ++$assertions;
    if (!$condition) throw new Error($message);
}
function same($expected, $actual, string $message): void
{
    check($expected === $actual, $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}
function test(string $label, callable $action): void
{
    global $failures;
    try { $action(); } catch (Throwable $error) { $failures[] = $label . ': ' . $error->getMessage(); }
}
function sqlKey(string $sql): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/\s*([(),])\s*/', '$1', $sql))));
}

class DateTimeImmutable extends \DateTimeImmutable
{
    public function __construct(string $datetime = 'now', ?DateTimeZone $timezone = null)
    {
        global $environment;
        parent::__construct($datetime === 'now' ? $environment['clock'] : $datetime, $timezone);
    }
}
function time(): int { global $environment; return (new \DateTimeImmutable($environment['clock']))->getTimestamp(); }
function date(string $format, ?int $timestamp = null): string { return \date($format, $timestamp ?? time()); }
function strtotime(string $datetime, ?int $baseTimestamp = null): int|false { return \strtotime($datetime, $baseTimestamp ?? time()); }
final class ResponseSignal extends Error
{
    public function __construct(public array $response) { parent::__construct((string)$response['message']); }
}
function sendResponse($success, $message, $data = null, $code = 200): never
{
    throw new ResponseSignal(compact('success', 'message', 'data', 'code'));
}
function getDBConnection(): MemoryDB
{
    global $environment;
    ++$environment['connections'];
    return $environment['db'];
}
function error_log(string $message): bool { global $environment; $environment['logs'][] = $message; return true; }
function requireLogin(): void { if (!isset($_SESSION['user_id'])) sendResponse(false, 'Login required', null, 401); }
function isAdmin(): bool { return ($_SESSION['user_role'] ?? '') === 'admin'; }
function isManager(): bool { return ($_SESSION['user_role'] ?? '') === 'manager'; }
function requireManagerAccess(): void
{
    global $environment;
    ++$environment['manager_checks'];
    requireLogin();
    if (!isAdmin() && !isManager()) sendResponse(false, 'Manager required', null, 403);
}

final class MemoryResult
{
    public int $num_rows;
    private int $offset = 0;
    public function __construct(private array $rows) { $this->num_rows = count($rows); }
    public function fetch_assoc(): ?array { return $this->rows[$this->offset++] ?? null; }
    public function fetch_all(int $mode): array
    {
        same(MYSQLI_ASSOC, $mode, 'Associative result mode');
        $remaining = array_slice($this->rows, $this->offset);
        $this->offset = count($this->rows);
        return $remaining;
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
        same(substr_count($this->sql, '?'), strlen($types), 'Placeholder/type count');
        same(strlen($types), count($values), 'Binding count');
        $this->types = $types;
        foreach ($values as &$value) $this->bindings[] =& $value;
        unset($value);
        return true;
    }
    public function execute(): bool
    {
        same(substr_count($this->sql, '?'), count($this->bindings), 'All placeholders bound before execution');
        $values = [];
        foreach ($this->bindings as $value) $values[] = $value;
        $this->rows = $this->db->execute($this->sql, $this->types, $values);
        return true;
    }
    public function get_result(): MemoryResult { return new MemoryResult($this->rows); }
    public function close(): void {}
}
final class MemoryDB
{
    public array $attempts = [];
    public array $queries = [];
    public array $mutations = [];
    public array $unknownSql = [];
    public string $listSql = '';
    private string $listTypes = '';
    private array $listValues = [];
    public array $remainingPaymentIds = [];
    public function __construct(public array $bookings, public array $packages, private array $options)
    {
        $walkin = $options['endpoint'] === 'walkin/get-all.php';
        $this->listSql = $walkin ? WALKIN_SELECT : BOOKING_SELECT . ($options['staff'] ? ' WHERE 1=1' : ' WHERE b.user_id = ?');
        if (!$walkin && !$options['staff']) {
            $this->listTypes = 'i';
            $this->listValues[] = $options['user_id'];
        }
        $get = $options['get'];
        if (($get['status'] ?? 'all') !== 'all') {
            $this->listSql .= ' AND b.status = ?';
            $this->listTypes .= 's';
            $this->listValues[] = $get['status'];
        }
        if ($walkin) foreach (['date_from' => '>=', 'date_to' => '<='] as $field => $operator) {
            if (!empty($get[$field])) {
                $this->listSql .= ' AND DATE(b.created_at) ' . $operator . ' ?';
                $this->listTypes .= 's';
                $this->listValues[] = $get[$field];
            }
        }
        if (!empty($get['search'])) {
            $this->listSql .= ' AND (b.name LIKE ? OR b.email LIKE ? OR b.package_name LIKE ? OR b.contact LIKE ?)';
            $this->listTypes .= 'ssss';
            foreach (range(1, 4) as $unused) $this->listValues[] = '%' . $get['search'] . '%';
        }
        $sorts = ['date-asc' => 'b.created_at ASC', 'date-desc' => 'b.created_at DESC',
            'amount-asc' => 'b.amount ASC', 'amount-desc' => 'b.amount DESC', 'name-asc' => 'b.name ASC', 'name-desc' => 'b.name DESC'];
        $this->listSql .= ' ORDER BY ' . ($sorts[$get['sort'] ?? 'date-desc'] ?? $sorts['date-desc']);
    }
    private function unknown(string $sql): never
    {
        $this->unknownSql[] = $sql;
        throw new Error('Unstubbed SQL (fail closed): ' . $sql);
    }
    private function approvedPrepared(): array
    {
        return $this->options['endpoint'] === 'bookings/check-status.php' ? [CURRENT_SELECT, UPGRADES_SELECT]
            : [$this->options['endpoint'] === 'walkin/get-all.php' ? EXPIRE_WALKIN : EXPIRE_ALL, $this->listSql,
                $this->options['endpoint'] === 'walkin/get-all.php'
                    ? 'SELECT status, payment_method, transaction_id, notes FROM payments WHERE booking_id = ?'
                    : 'SELECT payment_method, status FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1'];
    }
    public function prepare(string $sql): MemoryStatement
    {
        $this->attempts[] = $sql;
        if (!in_array(sqlKey($sql), array_map(__NAMESPACE__ . '\\sqlKey', $this->approvedPrepared()), true)) $this->unknown($sql);
        return new MemoryStatement($this, $sql);
    }
    public function query(string $sql): MemoryResult
    {
        $this->attempts[] = $sql;
        $this->queries[] = ['sql' => $sql, 'types' => '', 'values' => []];
        if ($this->options['endpoint'] === 'bookings/get-all.php'
            && in_array(sqlKey($sql), ["show columns from bookings like 'is_student'", "show columns from bookings like 'student_id_url'"], true)) {
            return new MemoryResult([['Field' => 'fixture_column']]); // Present schema: never allow migrations.
        }
        $this->unknown($sql);
    }
    private function bindings(string $expectedTypes, array $expectedValues, string $types, array $values): void
    {
        same($expectedTypes, $types, 'Exact SQL binding types');
        same($expectedValues, $values, 'Exact SQL binding values');
        foreach ($values as $i => $value) check(match ($types[$i]) {
            'i' => is_int($value), 's' => is_string($value), 'd' => is_float($value) || is_int($value), default => false,
        }, 'Native fixture value agrees with binding type');
    }
    public function execute(string $sql, string $types, array $values): array
    {
        $this->queries[] = compact('sql', 'types', 'values');
        $key = sqlKey($sql);
        if ($key === sqlKey(EXPIRE_ALL) || $key === sqlKey(EXPIRE_WALKIN)) {
            $this->bindings('s', [$this->options['gym_timestamp']], $types, $values);
            foreach ($this->bookings as $id => $row) {
                if (($key !== sqlKey(EXPIRE_WALKIN) || $row['user_id'] === null) && $row['status'] === 'verified'
                    && $row['expires_at'] !== null && $row['expires_at'] < $values[0]) {
                    $this->bookings[$id]['status'] = 'expired';
                    $this->mutations[] = ['id' => $id, 'values' => ['status' => 'expired']];
                }
            }
            return [];
        }
        if ($key === sqlKey(CURRENT_SELECT)) {
            $this->bindings('i', [$this->options['user_id']], $types, $values);
            $rows = array_values(array_filter($this->bookings, fn($b) => $b['user_id'] === $values[0] && in_array($b['status'], ['verified', 'pending'], true)));
            usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
            return array_map(fn($b) => $b + ['current_price' => $this->packages[$b['package_id']]['price'],
                'current_duration' => $this->packages[$b['package_id']]['duration']], array_slice($rows, 0, 1));
        }
        if ($key === sqlKey(UPGRADES_SELECT)) {
            $this->bindings('d', [$this->options['current_price'] ?? 100.0], $types, $values);
            $rows = array_values(array_filter($this->packages, fn($p) => $p['is_active'] === 1 && $p['price'] > $values[0]));
            usort($rows, fn($a, $b) => $a['price'] <=> $b['price']);
            return $rows;
        }
        if ($key === sqlKey($this->listSql)) {
            $this->bindings($this->listTypes, $this->listValues, $types, $values);
            $walkin = $this->options['endpoint'] === 'walkin/get-all.php';
            $get = $this->options['get'];
            $rows = array_values(array_filter($this->bookings, function ($b) use ($walkin, $get) {
                if ($walkin ? $b['user_id'] !== null : (!$this->options['staff'] && $b['user_id'] !== $this->options['user_id'])) return false;
                if (($get['status'] ?? 'all') !== 'all' && $b['status'] !== $get['status']) return false;
                if ($walkin && !empty($get['date_from']) && substr($b['created_at'], 0, 10) < $get['date_from']) return false;
                if ($walkin && !empty($get['date_to']) && substr($b['created_at'], 0, 10) > $get['date_to']) return false;
                if (!empty($get['search'])) {
                    $pattern = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote('%' . $get['search'] . '%', '/')) . '$/i';
                    foreach (['name', 'email', 'package_name', 'contact'] as $field) if (preg_match($pattern, $b[$field])) return true;
                    return false;
                }
                return true;
            }));
            [$field, $direction] = match ($get['sort'] ?? 'date-desc') {
                'date-asc' => ['created_at', 1], 'amount-asc' => ['amount', 1], 'amount-desc' => ['amount', -1],
                'name-asc' => ['name', 1], 'name-desc' => ['name', -1], default => ['created_at', -1],
            };
            usort($rows, fn($a, $b) => ($a[$field] <=> $b[$field]) * $direction);
            $this->remainingPaymentIds = array_column($rows, 'id');
            return array_map(function ($b) use ($walkin) {
                $p = $this->packages[$b['package_id']];
                return $walkin ? array_replace($b, ['package_name' => $p['name'], 'duration' => $p['duration']])
                    : $b + ['booking_package_name' => $b['package_name'], 'user_name' => $b['user_id'] === null ? null : 'Fixture member',
                        'pkg_name' => $p['name'], 'duration' => $p['duration'], 'pkg_price' => $p['price'],
                        'is_trainer_assisted' => 0, 'trainer_name' => null];
            }, $rows);
        }
        if (in_array($key, [sqlKey('SELECT payment_method, status FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1'),
            sqlKey('SELECT status, payment_method, transaction_id, notes FROM payments WHERE booking_id = ?')], true)) {
            check($this->remainingPaymentIds !== [], 'Payment lookup must follow a visible list row');
            $expectedId = $this->remainingPaymentIds[0];
            $this->bindings('i', [$expectedId], $types, $values);
            array_shift($this->remainingPaymentIds);
            return [];
        }
        $this->unknown($sql);
    }
}

// Strip only these exact approved includes. Evaluate the real shared helper separately.
function approvedSource(string $source, bool $endpoint): string
{
    $allowed = ["require_once'../config.php';" => 'config', "require_once'../session.php';" => 'session',
        "require_once__DIR__.'/../package-dates.php';" => 'package'];
    $counts = ['config' => 0, 'session' => 0, 'package' => 0];
    $tokens = token_get_all($source);
    $code = '';
    for ($i = 0, $n = count($tokens); $i < $n; ++$i) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
            $statement = '';
            do {
                $part = $tokens[$i];
                if (!is_array($part) || !in_array($part[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $statement .= is_array($part) ? $part[1] : $part;
                if ($part === ';') break;
                ++$i;
            } while ($i < $n);
            check($endpoint && isset($allowed[$statement]), 'Unapproved bootstrap: ' . $statement);
            ++$counts[$allowed[$statement]];
            $code .= ';';
        } elseif (is_array($token) && in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG], true)) {
            $code .= $token[0] === T_CLOSE_TAG ? ';' : '';
        } elseif (is_array($token) && $token[0] === T_INLINE_HTML) {
            check(trim($token[1]) === '', 'Unexpected inline output');
        } else {
            $code .= is_array($token) ? $token[1] : $token;
        }
    }
    if ($endpoint) same(['config' => 1, 'session' => 1, 'package' => 1], $counts, 'Exact approved bootstrap counts');
    return $code;
}
function loadActualSource(string $path, bool $endpoint): void
{
    $code = approvedSource(\file_get_contents($path), $endpoint);
    eval('namespace ' . __NAMESPACE__ . '; use \\DateTimeZone; use \\Exception; use \\Throwable; ' . $code);
}
function booking(int $id, ?int $owner, string $status = 'verified', ?string $expiry = '2026-12-31 23:59:59', array $extra = []): array
{
    return array_replace(['id' => $id, 'user_id' => $owner, 'package_id' => 7, 'package_name' => 'Daily Fixture',
        'booking_date' => '2026-12-31', 'expires_at' => $expiry, 'status' => $status,
        'created_at' => sprintf('2026-12-31 10:%02d:00', $id % 60), 'amount' => 80.0,
        'name' => 'Fixture ' . $id, 'email' => 'fixture' . $id . '@example.test', 'contact' => '09000000000',
        'trainer_id' => null, 'notes' => 'Keep purchase snapshot', 'receipt_url' => null,
        'is_student' => 0, 'student_id_url' => null, 'verified_at' => 'fixture-db-instant'], $extra);
}
function mixedBookings(): array
{
    return [1 => booking(1, 101), 2 => booking(2, 202), 3 => booking(3, null),
        4 => booking(4, 101, 'verified', null), 5 => booking(5, null, 'verified', null),
        6 => booking(6, 101, 'pending', '2026-12-30 23:59:59'), 7 => booking(7, null, 'pending', '2026-12-30 23:59:59'),
        8 => booking(8, 101, 'upgraded', '2026-12-30 23:59:59'), 9 => booking(9, null, 'upgraded', '2026-12-30 23:59:59'),
        10 => booking(10, 101, 'verified', '2027-01-04 23:59:59', ['package_id' => 8, 'booking_date' => '2026-12-29']),
        11 => booking(11, null, 'verified', '2027-01-04 23:59:59', ['package_id' => 8, 'booking_date' => '2026-12-29']),
        12 => booking(12, 101, 'verified', '2026-12-30 23:59:59'), 13 => booking(13, null, 'verified', '2026-12-30 23:59:59'),
        14 => booking(14, 202, 'verified', '2026-12-30 23:59:59'), 15 => booking(15, 101, 'rejected', '2026-12-30 23:59:59'),
        16 => booking(16, null, 'expired', '2026-12-30 23:59:59')];
}
function runEndpoint(string $endpoint, array $options = []): array
{
    global $environment, $cases;
    ++$cases;
    check(in_array($endpoint, ENDPOINTS, true), 'Known endpoint');
    $options = array_replace(['endpoint' => $endpoint, 'role' => 'member', 'user_id' => 101, 'anonymous' => false,
        'method' => 'GET', 'get' => [], 'tz' => 'UTC', 'clock' => '2026-12-31T15:59:59Z',
        'gym_timestamp' => '2026-12-31 23:59:59'], $options);
    $options['staff'] = in_array($options['role'], ['admin', 'manager'], true);
    $packages = [7 => ['id' => 7, 'name' => 'Daily Fixture', 'duration' => 'Daily', 'price' => 100.0, 'is_active' => 1],
        8 => ['id' => 8, 'name' => 'Weekly Fixture', 'duration' => 'Weekly', 'price' => 700.0, 'is_active' => 1],
        9 => ['id' => 9, 'name' => 'Higher Fixture', 'duration' => 'Monthly', 'price' => 900.0, 'is_active' => 1],
        10 => ['id' => 10, 'name' => 'Inactive Fixture', 'duration' => 'Monthly', 'price' => 800.0, 'is_active' => 0]];
    $db = new MemoryDB($options['bookings'] ?? mixedBookings(), $packages, $options);
    $before = $db->bookings;
    $environment = ['clock' => $options['clock'], 'db' => $db, 'connections' => 0, 'manager_checks' => 0, 'logs' => []];
    $_SESSION = $options['anonymous'] ? [] : ['user_id' => $options['user_id'], 'user_role' => $options['role']];
    $_GET = $options['get'];
    $_SERVER['REQUEST_METHOD'] = $options['method'];
    $originalTimezone = date_default_timezone_get();
    date_default_timezone_set($options['tz']);
    $level = ob_get_level();
    ob_start();
    try {
        try {
            loadActualSource(__DIR__ . '/api/' . $endpoint, true);
            throw new RuntimeException('Endpoint returned without sendResponse');
        } catch (ResponseSignal $r) {
            same([], $db->unknownSql, 'No caught unstubbed SQL');
            same([], $db->remainingPaymentIds, 'Exactly one correctly bound payment lookup per visible list row');
            same([], $environment['logs'], 'No endpoint error logs');
            same($options['tz'], date_default_timezone_get(), 'Endpoint/helper leaves host timezone unchanged');
            same('', (string)ob_get_contents(), 'No accidental endpoint output');
            return $r->response + ['db' => $db, 'before' => $before, 'connections' => $environment['connections'],
                'manager_checks' => $environment['manager_checks']];
        }
    } finally {
        while (ob_get_level() > $level) ob_end_clean();
        date_default_timezone_set($originalTimezone);
    }
}
function success(array $r): void { same(true, $r['success'], 'Endpoint success: ' . $r['message']); same(200, $r['code'], 'Success code'); }
function states(array $r, array $expiredIds): void
{
    $expected = $r['before'];
    foreach ($expiredIds as $id) $expected[$id]['status'] = 'expired';
    same($expected, $r['db']->bookings, 'Only intended status mutations; all purchase snapshots preserved');
    same($expiredIds, array_column($r['db']->mutations, 'id'), 'Exact mutated booking IDs');
}
function ids(array $r): array { return array_column($r['data'], 'id'); }
function sortedIds(array $r): array { $ids = ids($r); sort($ids); return $ids; }
function mutationBeforeRead(array $r, string $endpoint): void
{
    $queries = $r['db']->queries;
    $writes = array_keys(array_filter($queries, fn($q) => str_starts_with(sqlKey($q['sql']), 'update ')));
    $reads = array_keys(array_filter($queries, fn($q) => sqlKey($q['sql']) === sqlKey($r['db']->listSql)));
    same(1, count($writes), 'Exactly one prepared auto-expiry statement');
    same(1, count($reads), 'Exactly one list query');
    check($writes[0] < $reads[0], 'Auto-expiry executes before status-filtered SELECT');
    same(sqlKey($endpoint === 'walkin/get-all.php' ? EXPIRE_WALKIN : EXPIRE_ALL), sqlKey($queries[$writes[0]]['sql']), 'Exact mutation scope/comparison SQL');
}
function denied(array $r, int $code): void
{
    same(false, $r['success'], 'Guard denial');
    same($code, $r['code'], 'Guard response code');
    same(0, $r['connections'], 'Denied request never opens even memory DB');
    same([], $r['db']->attempts, 'Denied request never attempts any SQL');
    states($r, []);
}

try {
    check(!extension_loaded('mysqli'), 'Run PHP -n: mysqli must not be loaded');
    loadActualSource(__DIR__ . '/api/package-dates.php', false);
    test('Fail-closed harness guards', function () {
        foreach (["<?php require_once '../config.php'; require_once '../session.php'; require_once __DIR__.'/../package-dates.php'; include '../email.php';",
            "<?php require_once '../config.php'; require_once '../session.php';"] as $source) {
            $rejected = false;
            try { approvedSource($source, true); } catch (Error $e) { $rejected = true; }
            check($rejected, 'Unapproved/missing includes fail closed');
        }
        $r = runEndpoint('bookings/get-all.php');
        foreach (["UPDATE bookings SET status = 'expired' WHERE expires_at <= ?", 'ALTER TABLE bookings ADD unsafe INT',
            'DELETE FROM bookings', 'SELECT * FROM bookings'] as $sql) {
            $rejected = false;
            try { $r['db']->prepare($sql); } catch (Error $e) { $rejected = true; }
            check($rejected, 'Unknown SQL rejected before execution');
        }
        $before = $r['db']->bookings;
        foreach ([['i', [123]], ['s', ['2026-12-31 15:59:59']]] as [$types, $values]) {
            $rejected = false;
            try { $r['db']->execute(EXPIRE_ALL, $types, $values); } catch (Error $e) { $rejected = true; }
            check($rejected, 'Wrong timestamp binding type/value rejected');
        }
        same($before, $r['db']->bookings, 'Fail-closed probes cannot mutate fixtures');
    });

    foreach (['UTC', 'America/Los_Angeles', 'Asia/Manila'] as $tz) {
        foreach ([['2026-12-31T15:59:59Z', '2026-12-31 23:59:59', false],
            ['2026-12-31T16:00:00Z', '2027-01-01 00:00:00', true]] as [$clock, $gymTimestamp, $after]) {
            foreach (['bookings/get-all.php', 'walkin/get-all.php'] as $endpoint) {
                test($endpoint . ' daily boundary ' . $tz . ' ' . $gymTimestamp, function () use ($endpoint, $tz, $clock, $gymTimestamp, $after) {
                    $r = runEndpoint($endpoint, ['role' => 'manager', 'tz' => $tz, 'clock' => $clock, 'gym_timestamp' => $gymTimestamp]);
                    success($r);
                    states($r, $endpoint === 'walkin/get-all.php' ? ($after ? [3, 13] : [13]) : ($after ? [1, 2, 3, 12, 13, 14] : [12, 13, 14]));
                    same($endpoint === 'walkin/get-all.php' ? [3, 5, 7, 9, 11, 13, 16] : range(1, 16), sortedIds($r), 'Staff read visibility');
                    mutationBeforeRead($r, $endpoint);
                    foreach ($r['data'] as $b) {
                        same($b['user_id'] === null, $b['is_walkin'], 'Walk-in flag reflects ownership');
                        same(80.0, $b['amount'], 'Purchased discount not overwritten by joined package price');
                    }
                    if ($endpoint === 'walkin/get-all.php') same(1, $r['manager_checks'], 'Actual manager guard invoked');
                });
                foreach (['verified', 'expired'] as $filter) {
                    test($endpoint . ' status filter after expiry ' . $tz . ' ' . $filter . ' ' . $gymTimestamp, function () use ($endpoint, $tz, $clock, $gymTimestamp, $after, $filter) {
                        $r = runEndpoint($endpoint, ['role' => 'admin', 'tz' => $tz, 'clock' => $clock, 'gym_timestamp' => $gymTimestamp, 'get' => ['status' => $filter]]);
                        success($r);
                        $walkin = $endpoint === 'walkin/get-all.php';
                        $expected = $filter === 'verified' ? ($walkin ? ($after ? [5, 11] : [3, 5, 11]) : ($after ? [4, 5, 10, 11] : [1, 2, 3, 4, 5, 10, 11]))
                            : ($walkin ? ($after ? [3, 13, 16] : [13, 16]) : ($after ? [1, 2, 3, 12, 13, 14, 16] : [12, 13, 14, 16]));
                        same($expected, sortedIds($r), 'Status filtering observes mutated states');
                        mutationBeforeRead($r, $endpoint);
                    });
                }
            }
            foreach (['verified', 'pending', 'upgraded', 'expired', 'rejected', 'legacy-null'] as $status) {
                test('check-status boundary ' . $tz . ' ' . $status . ' ' . $gymTimestamp, function () use ($tz, $clock, $gymTimestamp, $after, $status) {
                    $actualStatus = $status === 'legacy-null' ? 'verified' : $status;
                    $r = runEndpoint('bookings/check-status.php', ['tz' => $tz, 'clock' => $clock, 'gym_timestamp' => $gymTimestamp,
                        'bookings' => [1 => booking(1, 101, $actualStatus, $status === 'legacy-null' ? null : '2026-12-31 23:59:59')]]);
                    success($r);
                    states($r, []);
                    $active = $status === 'legacy-null' || ($status === 'verified' && !$after);
                    same(!($active || $status === 'pending'), $r['data']['can_book'], 'Inclusive final second/null/pending booking decision');
                    same($active ? 'active_booking' : ($status === 'pending' ? 'pending_booking' : null), $r['data']['reason'], 'Booking reason');
                    $selected = in_array($actualStatus, ['verified', 'pending'], true);
                    same($selected ? 1 : null, $r['data']['current_booking']['id'] ?? null, 'Pending/verified only selection');
                    same($selected ? $actualStatus : null, $r['data']['current_booking']['status'] ?? null, 'Read never auto-expires status');
                    same($active ? [8, 9] : [], array_column($r['data']['available_upgrades'], 'id'), 'Active booking upgrade visibility excludes inactive/lower tiers');
                    if ($active) same([600.0, 800.0], array_column($r['data']['available_upgrades'], 'upgrade_price'), 'Upgrade price differences');
                    foreach ($r['db']->attempts as $sql) check(str_starts_with(sqlKey($sql), 'select '), 'check-status never attempts a write');
                });
            }
        }
        // Hard-coded weekly oracle: Dec 29 through Jan 4 inclusive, Jan 5 exclusive.
        same('2027-01-04 23:59:59', packageBookingDates('2026-12-29', 'Weekly')['expires_at'], 'Actual helper weekly range');
        foreach ([['2027-01-04T15:59:59Z', '2027-01-04 23:59:59', false],
            ['2027-01-04T16:00:00Z', '2027-01-05 00:00:00', true]] as [$clock, $gymTimestamp, $after]) {
            foreach (ENDPOINTS as $endpoint) {
                test($endpoint . ' weekly boundary ' . $tz . ' ' . $gymTimestamp, function () use ($endpoint, $tz, $clock, $gymTimestamp, $after) {
                    $walkin = $endpoint === 'walkin/get-all.php';
                    $r = runEndpoint($endpoint, ['role' => 'manager', 'tz' => $tz, 'clock' => $clock, 'gym_timestamp' => $gymTimestamp,
                        'bookings' => [10 => booking(10, $walkin ? null : 101, 'verified', '2027-01-04 23:59:59', ['package_id' => 8, 'booking_date' => '2026-12-29'])],
                        'current_price' => 700.0]);
                    success($r);
                    states($r, $endpoint !== 'bookings/check-status.php' && $after ? [10] : []);
                    if ($endpoint === 'bookings/check-status.php') {
                        same($after, $r['data']['can_book'], 'Weekly final second inclusive');
                        same($after ? null : 'active_booking', $r['data']['reason'], 'Weekly booking reason');
                    } else {
                        same($after ? 'expired' : 'verified', $r['data'][0]['status'], 'Weekly list status');
                        mutationBeforeRead($r, $endpoint);
                    }
                });
            }
        }
    }
    foreach (['member', 'trainer', 'manager', 'admin'] as $role) {
        test('Bookings ownership/read visibility ' . $role, function () use ($role) {
            $r = runEndpoint('bookings/get-all.php', ['role' => $role]);
            success($r);
            same(in_array($role, ['manager', 'admin'], true) ? range(1, 16) : [1, 4, 6, 8, 10, 12, 15], sortedIds($r), 'Non-staff sees only own rows, staff sees all');
            // This endpoint intentionally performs global maintenance for every logged-in reader;
            // owner-restricted SELECT visibility is distinct from its authorized maintenance scope.
            states($r, [12, 13, 14]);
        });
    }
    test('Member status filter and search retain ownership', function () {
        $r = runEndpoint('bookings/get-all.php', ['get' => ['status' => 'verified', 'search' => 'Fixture', 'sort' => 'date-asc']]);
        success($r);
        same([1, 4, 10], ids($r), 'Owner/status/search bound together; date-ascending order');
        mutationBeforeRead($r, 'bookings/get-all.php');
        $other = runEndpoint('bookings/get-all.php', ['get' => ['search' => 'fixture2@example.test']]);
        success($other);
        same([], ids($other), 'Searching another owner cannot expose their booking');
    });
    test('Walk-in date/status/search filters do not widen mutation scope', function () {
        $r = runEndpoint('walkin/get-all.php', ['role' => 'manager', 'clock' => '2026-12-31T16:00:00Z', 'gym_timestamp' => '2027-01-01 00:00:00',
            'get' => ['status' => 'expired', 'date_from' => '2026-12-31', 'date_to' => '2026-12-31', 'search' => 'Fixture', 'sort' => 'name-desc']]);
        success($r);
        same([3, 16, 13], ids($r), 'Combined filter values/order and walk-in-only visibility');
        states($r, [3, 13]);
        mutationBeforeRead($r, 'walkin/get-all.php');
    });
    foreach (['member', 'manager', 'admin'] as $role) {
        test('check-status session ownership/latest eligible booking ' . $role, function () use ($role) {
            $r = runEndpoint('bookings/check-status.php', ['role' => $role, 'get' => ['user_id' => 202],
                'bookings' => [1 => booking(1, 101), 2 => booking(2, 202, 'pending'), 3 => booking(3, null, 'pending'),
                    4 => booking(4, 101, 'upgraded'), 5 => booking(5, 101, 'rejected'), 6 => booking(6, 101, 'expired')]]);
            success($r);
            same(1, $r['data']['current_booking']['id'], 'URL owner ignored; newer ineligible/foreign rows excluded');
            same('active_booking', $r['data']['reason'], 'Own verified row remains active');
            states($r, []);
            $pending = runEndpoint('bookings/check-status.php', ['role' => $role, 'bookings' => [1 => booking(1, 101), 2 => booking(2, 101, 'pending')]]);
            success($pending);
            same(2, $pending['data']['current_booking']['id'], 'Newest eligible pending row wins');
            same('pending_booking', $pending['data']['reason'], 'Latest pending blocks booking');
            states($pending, []);
        });
    }
    foreach (ENDPOINTS as $endpoint) {
        test($endpoint . ' anonymous no unauthorized writes', fn() => denied(runEndpoint($endpoint, ['anonymous' => true]), 401));
        test($endpoint . ' wrong method no writes', fn() => denied(runEndpoint($endpoint, ['role' => 'manager', 'method' => 'POST']), 405));
        test($endpoint . ' empty data', function () use ($endpoint) {
            $r = runEndpoint($endpoint, ['role' => 'manager', 'bookings' => []]);
            success($r);
            states($r, []);
            same($endpoint === 'bookings/check-status.php' ? null : [], $endpoint === 'bookings/check-status.php' ? $r['data']['current_booking'] : $r['data'], 'Empty result supported');
        });
    }
    foreach (['member', 'trainer'] as $role) test('Walk-in ' . $role . ' denied before DB/mutation', fn() => denied(runEndpoint('walkin/get-all.php', ['role' => $role]), 403));

    if ($failures) {
        foreach ($failures as $failure) fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
        fwrite(STDERR, count($failures) . " failing groups; $assertions assertions; $cases actual-endpoint cases.\n");
        exit(1);
    }
    print "PASS: $assertions assertions; $cases actual-endpoint cases across UTC/LA/Manila frozen clocks. No DB, HTTP, application bootstrap, or external writes.\n";
    print "Scope: fail-closed in-memory SQL/binding contracts; no real TIMESTAMP/session conversion, schema migration, transaction isolation or concurrency emulation.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
