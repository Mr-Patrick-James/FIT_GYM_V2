<?php
declare(strict_types=1);

namespace PackageBookingRegression;

use DateTimeZone;
use Error;
use Exception;
use RuntimeException;
use Throwable;

// CLI-only, offline. Run with: php -n test-package-booking-dates.php
// Source is evaluated, never bootstrapped. Children have no mysqli extension.
if (PHP_SAPI !== 'cli') exit;

$assertions = 0;
$failures = [];
$environment = ['clock' => '2026-12-31T16:00:00Z'];
const ENDPOINTS = ['bookings/create.php', 'bookings/change-plan.php', 'walkin/create.php',
    'bookings/upgrade.php', 'bookings/update.php', 'packages/create.php', 'packages/update.php'];

function check(bool $condition, string $message): void
{
    global $assertions;
    ++$assertions;
    if (!$condition) throw new RuntimeException($message);
}
function same($expected, $actual, string $message): void
{
    check($expected === $actual, $message . ': expected ' . var_export($expected, true)
        . ', got ' . var_export($actual, true));
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

// Freeze only the evaluated namespace's clocks, not production source or config.
class DateTimeImmutable extends \DateTimeImmutable
{
    public function __construct(string $datetime = 'now', ?DateTimeZone $timezone = null)
    {
        global $environment;
        parent::__construct($datetime === 'now' ? $environment['clock'] : $datetime, $timezone);
    }
}
class DateTime extends \DateTime
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
function getDBConnection(): MemoryDB { global $environment; return $environment['db']; }
function getRequestData(): array { global $environment; return $environment['input']; }
function file_get_contents(string $path): string
{
    check($path === 'php://input', 'Unexpected endpoint file read: ' . $path);
    return json_encode(getRequestData(), JSON_THROW_ON_ERROR);
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
function createNotification(...$args): void { global $environment; $environment['notifications'][] = $args; }
function sendBookingNotificationEmail(...$args): bool { global $environment; $environment['emails'][] = $args; return true; }
function sendBookingVerificationEmail(...$args): bool { return sendBookingNotificationEmail(...$args); }
function sendBookingRejectionEmail(...$args): bool { return sendBookingNotificationEmail(...$args); }
function sendTrainerNewBookingEmail(...$args): bool { return sendBookingNotificationEmail(...$args); }
function sendTrainerNewClientEmail(...$args): bool { return sendBookingNotificationEmail(...$args); }
function sendTrainerPackageAssignmentEmail(...$args): bool { return sendBookingNotificationEmail(...$args); }

final class MemoryResult
{
    public int $num_rows;
    private int $offset = 0;
    public function __construct(private array $rows) { $this->num_rows = count($rows); }
    public function fetch_assoc(): ?array { return $this->rows[$this->offset++] ?? null; }
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
        foreach ($values as &$value) $this->bindings[] =& $value;
        unset($value);
        return true;
    }
    public function execute(): bool
    {
        $values = [];
        foreach ($this->bindings as $value) $values[] = $value;
        [$this->rows, $this->affected_rows] = $this->db->execute($this->sql, $values, $this->types);
        return true;
    }
    public function get_result(): MemoryResult { return new MemoryResult($this->rows); }
    public function close(): void {}
}
final class MemoryDB
{
    public array $queries = [];
    public array $mutations = [];
    public array $transactions = [];
    public array $unknownSql = [];
    public int $insert_id = 600;
    public string $error = '';
    public function __construct(public array $bookings, public array $packages) {}
    public function prepare(string $sql): MemoryStatement { return new MemoryStatement($this, $sql); }
    public function begin_transaction(): bool { $this->transactions[] = 'begin'; return true; }
    public function commit(): bool { $this->transactions[] = 'commit'; return true; }
    public function rollback(): bool { $this->transactions[] = 'rollback'; return true; }
    public function close(): void {}
    private function unknown(string $sql): never
    {
        $this->unknownSql[] = $sql;
        // Retain unknown SQL even if an endpoint catches Throwable around notifications.
        throw new Error('Unstubbed SQL (fail closed): ' . $sql);
    }
    private function write(string $table, string $operation, array $values, ?int $id = null): array
    {
        $this->mutations[] = compact('table', 'operation', 'values', 'id');
        if ($table === 'bookings') {
            if ($operation === 'insert') {
                $id = ++$this->insert_id;
                $this->bookings[$id] = $values + ['id' => $id, 'status' => 'pending', 'trainer_id' => null];
            } elseif ($id !== null && isset($this->bookings[$id])) {
                $this->bookings[$id] = array_replace($this->bookings[$id], $values);
            }
        }
        return [[], 1];
    }
    public function query(string $sql): MemoryResult|bool
    {
        $this->queries[] = ['sql' => $sql, 'values' => []];
        $key = sqlKey($sql);
        if (in_array($key, ["show columns from bookings like 'is_student'", "show columns from bookings like 'student_id_url'",
            "show columns from packages like 'diet_info'", "show columns from packages like 'guidance_info'"], true)) {
            return new MemoryResult([['Field' => 'fixture_column']]);
        }
        if (preg_match("/^update bookings set status = 'expired' where user_id = (\d+) and status = 'verified' and expires_at is not null and expires_at < now\(\)$/", $key, $m)) {
            $this->write('bookings', 'auto-expire', ['user_id' => (int)$m[1]]);
            foreach ($this->bookings as &$booking) {
                if ($booking['user_id'] === (int)$m[1] && $booking['status'] === 'verified'
                    && $booking['expires_at'] !== null && $booking['expires_at'] < date('Y-m-d H:i:s')) $booking['status'] = 'expired';
            }
            unset($booking);
            return true;
        }
        if (preg_match('/^delete from package_trainers where package_id = (\d+)$/', $key, $m)) {
            $this->write('package_trainers', 'delete', ['package_id' => (int)$m[1]]);
            return true;
        }
        $this->unknown($sql);
    }
    public function execute(string $sql, array $values, string $types): array
    {
        $this->queries[] = compact('sql', 'values', 'types');
        $key = sqlKey($sql);
        if ($key === 'select name,email from users where id = ?') {
            same('i', $types, 'User lookup binding');
            return [$values[0] === 101 ? [['name' => 'Offline Member', 'email' => 'member@example.test']] : [], 0];
        }
        $packageQueries = [
            'select id,price,duration from packages where name = ? and is_active = 1',
            'select id,name,price,duration from packages where name = ? and is_active = 1 limit 1',
            'select id,name,price,duration from packages where id = ? and is_active = 1',
            'select id from packages where id = ?',
        ];
        if (in_array($key, $packageQueries, true)) {
            $byName = str_contains($key, 'where name');
            same($byName ? 's' : 'i', $types, 'Package lookup binding');
            $rows = array_values(array_filter($this->packages, fn($p) =>
                ($byName ? $p['name'] === $values[0] : $p['id'] === (int)$values[0])
                && (!str_contains($key, 'is_active') || $p['is_active'] === 1)));
            // Honor SELECT projection: missing duration must not be supplied by the stub.
            preg_match('/^select (.+) from/', $key, $m);
            $columns = array_fill_keys(explode(',', $m[1]), true);
            return [array_map(fn($row) => array_intersect_key($row, $columns), $rows), 0];
        }
        if ($key === 'select * from bookings where id = ? and user_id = ? limit 1') {
            same('ii', $types, 'Edit ownership bindings');
            $b = $this->bookings[(int)$values[0]] ?? null;
            return [$b && $b['user_id'] === (int)$values[1] ? [$b] : [], 0];
        }
        if ($key === sqlKey("UPDATE bookings SET status = 'expired' WHERE user_id = ? AND status = 'verified' AND expires_at IS NOT NULL AND expires_at < ?")) {
            same('is', $types, 'Auto-expiry owner and gym timestamp bindings');
            same('2027-01-01 00:00:00', $values[1], 'Auto-expiry uses Manila clock');
            $affected = 0;
            foreach ($this->bookings as $id => $b) {
                if ($b['user_id'] === (int)$values[0] && $b['status'] === 'verified'
                    && $b['expires_at'] !== null && $b['expires_at'] < $values[1]) {
                    $this->write('bookings', 'auto-expire', ['status' => 'expired'], $id);
                    ++$affected;
                }
            }
            return [[], $affected];
        }
        if ($key === sqlKey("select id,package_name,status,expires_at from bookings where user_id = ? and status in ('verified','pending') order by created_at desc limit 1")) {
            same('i', $types, 'Existing booking ownership');
            $rows = array_values(array_filter($this->bookings, fn($b) => $b['user_id'] === (int)$values[0]
                && in_array($b['status'], ['verified', 'pending'], true)));
            return [array_slice($rows, 0, 1), 0];
        }
        if ($key === "select b.*,p.price as current_price,p.duration as current_duration from bookings b left join packages p on b.package_id = p.id where b.user_id = ? and b.status = 'verified' order by b.created_at desc limit 1") {
            same('i', $types, 'Upgrade owner binding');
            foreach ($this->bookings as $b) {
                if ($b['user_id'] === (int)$values[0] && $b['status'] === 'verified') {
                    $p = $this->packages[$b['package_id']];
                    return [[$b + ['current_price' => $p['price'], 'current_duration' => $p['duration']]], 0];
                }
            }
            return [[], 0];
        }
        if ($key === 'select b.*,p.duration,p.name as package_name from bookings b left join packages p on b.package_id = p.id where b.id = ?') {
            same('i', $types, 'Verification ID binding');
            $b = $this->bookings[(int)$values[0]] ?? null;
            if (!$b) return [[], 0];
            $p = $this->packages[$b['package_id']] ?? [];
            return [[array_replace($b, ['duration' => $p['duration'] ?? null, 'package_name' => $p['name'] ?? null])], 0];
        }
        if (in_array($key, ["select id from users where role = 'admin'", "select id from users where role = 'admin' and id != ?",
            'select t.email,t.name,t.user_id from trainers t join package_trainers pt on pt.trainer_id = t.id where pt.package_id = ? and t.is_active = 1',
            'select pt.trainer_id from package_trainers pt join trainers t on t.id = pt.trainer_id where pt.package_id = ? and t.is_active = 1 order by pt.trainer_id asc limit 1'], true)) {
            return [[], 0]; // Bounded: no trainer/admin recipients in the fixture.
        }
        if ($key === sqlKey('UPDATE bookings SET verified_at = CURRENT_TIMESTAMP WHERE id = ?')) {
            same('i', $types, 'DB-clock verification binds only an integer booking ID');
            same([501], $values, 'DB-clock verification must not bind a Manila wall-clock timestamp');
            // Symbolic DB-clock value, deliberately not a converted TIMESTAMP/session value.
            return $this->write('bookings', 'update', ['verified_at' => 'DB_CURRENT_TIMESTAMP'], $values[0]);
        }
        $updates = [
            'update bookings set package_id = ?,package_name = ?,amount = ?,booking_date = ?,contact = ?,notes = ?,receipt_url = ?,expires_at = ? where id = ?'
                => ['package_id', 'package_name', 'amount', 'booking_date', 'contact', 'notes', 'receipt_url', 'expires_at'],
            'update bookings set status = ?,notes = ? where id = ?' => ['status', 'notes'],
            'update bookings set status = ?,notes = ?,trainer_id = ? where id = ?' => ['status', 'notes', 'trainer_id'],
            'update bookings set expires_at = ? where id = ?' => ['expires_at'],
        ];
        $updates = array_combine(array_map(__NAMESPACE__ . '\\sqlKey', array_keys($updates)), array_values($updates));
        if ($key === sqlKey('UPDATE bookings SET expires_at = ? WHERE id = ? AND expires_at IS NULL')) {
            same('si', $types, 'Null-only expiry backfill binding');
            $id = (int)$values[1];
            if (!isset($this->bookings[$id]) || $this->bookings[$id]['expires_at'] !== null) return [[], 0];
            return $this->write('bookings', 'update', ['expires_at' => $values[0]], $id);
        }
        if (isset($updates[$key])) {
            $id = (int)array_pop($values);
            return $this->write('bookings', 'update', array_combine($updates[$key], $values), $id);
        }
        if (preg_match("/^update bookings set status = 'upgraded',notes = concat\(ifnull\(notes,''\),' upgraded to: fixture pass on \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}'\)where id = \?$/", $key)) {
            same('i', $types, 'Upgrade old booking ID');
            return $this->write('bookings', 'update', ['status' => 'upgraded'], (int)$values[0]);
        }
        // Exact INSERT shapes; values are mapped, not expiry algorithms reimplemented.
        $inserts = [
            'insert into bookings(user_id,name,email,contact,package_id,package_name,amount,booking_date,expires_at,notes,receipt_url,is_student,student_id_url) values(?,?,?,?,?,?,?,?,?,?,?,?,?)',
            "insert into bookings(user_id,name,email,contact,package_id,package_name,amount,booking_date,expires_at,notes,receipt_url,status) values(?,?,?,?,?,?,?,?,?,?,?,'verified')",
            "insert into bookings(user_id,name,email,contact,package_id,package_name,amount,booking_date,notes,receipt_url,expires_at,status) values(?,?,?,?,?,?,?,?,?,?,?,'verified')",
            "insert into bookings(user_id,name,email,contact,package_id,package_name,amount,booking_date,expires_at,notes,receipt_url,status) values(?,?,?,?,?,?,?,?,?,?,?,'pending')",
            'insert into packages(name,duration,price,tag,description,is_trainer_assisted,goal,diet_info,guidance_info) values(?,?,?,?,?,?,?,?,?)',
            "insert into notifications(user_id,title,message,type) values(?,'booking verified','your booking has been verified! your membership is now active.','success')",
        ];
        if (in_array($key, array_map(__NAMESPACE__ . '\\sqlKey', $inserts), true)) {
            preg_match('/^insert into (\w+)\(([^)]+)\)values\((.+)\)$/s', $key, $m);
            $columns = explode(',', $m[2]);
            $expressions = explode(',', $m[3]);
            $mapped = [];
            foreach ($columns as $i => $column) $mapped[$column] = $expressions[$i] === '?' ? array_shift($values) : trim($expressions[$i], "'");
            same([], $values, 'All insert bindings consumed');
            return $this->write($m[1], 'insert', $mapped);
        }
        $payments = [
            "insert into payments(user_id,booking_id,amount,status,payment_method,transaction_id,receipt_url,notes,created_at) values(?,?,?,?,?,concat('walk_',?),?,?,now()) on duplicate key update amount = values(amount),status = values(status),payment_method = values(payment_method),receipt_url = values(receipt_url),notes = values(notes),updated_at = now()",
            "insert into payments(user_id,booking_id,amount,status,payment_method,transaction_id,receipt_url,created_at) values(?,?,?,'completed','upgrade payment',concat('up_',?),?,now())",
            "insert into payments(user_id,booking_id,amount,status,payment_method,transaction_id,receipt_url,created_at) values(?,?,?,'completed','booking payment',concat('bk_',?),?,now()) on duplicate key update amount = values(amount),status = values(status),receipt_url = values(receipt_url)",
        ];
        if (in_array($key, array_map(__NAMESPACE__ . '\\sqlKey', $payments), true)) {
            return $this->write('payments', 'insert', ['user_id' => $values[0], 'booking_id' => $values[1], 'amount' => $values[2], 'bindings' => $values]);
        }
        if ($key === sqlKey('update packages set name = ?,duration = ?,price = ?,tag = ?,description = ?,is_trainer_assisted = ?,goal = ?,diet_info = ?,guidance_info = ?,updated_at = now() where id = ?')) {
            $id = (int)array_pop($values);
            return $this->write('packages', 'update', array_combine(['name', 'duration', 'price', 'tag', 'description', 'is_trainer_assisted', 'goal', 'diet_info', 'guidance_info'], $values), $id);
        }
        $this->unknown($sql);
    }
}

// Strip only exact, approved bootstrap statements using tokens. New includes fail
// closed; retain the helper by loading its actual source separately in this namespace.
function loadActualSource(string $path, bool $endpoint): void
{
    $tokens = token_get_all(\file_get_contents($path));
    $code = '';
    $counts = ['config' => 0, 'session' => 0, 'email' => 0, 'package' => 0];
    $allowed = [
        "require_once'../config.php';" => 'config', "require_once'../session.php';" => 'session',
        "require_once'../email.php';" => 'email',
        "require_once__DIR__.'/../config.php';" => 'config', "require_once__DIR__.'/../session.php';" => 'session',
        "require_once__DIR__.'/../email.php';" => 'email', "require_once__DIR__.'/../package-dates.php';" => 'package',
    ];
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
            check(trim($token[1]) === '', 'Unexpected inline HTML');
        } else {
            $code .= is_array($token) ? $token[1] : $token;
        }
    }
    if ($endpoint) {
        same(1, $counts['config'], 'Exact config bootstrap count');
        same(1, $counts['session'], 'Exact session bootstrap count');
        same(1, $counts['package'], 'Actual endpoint must require the shared package-date helper');
        check($counts['email'] <= 5, 'Bounded email bootstrap count');
    }
    eval('namespace ' . __NAMESPACE__ . '; use \\DateTimeZone; use \\Exception; use \\Throwable; ' . $code);
}

// Independent expected dates: no modify()/strtotime() used to derive the oracle.
function vectors(): array
{
    return [
        'durations' => [
            ['Daily', 1], ['daily Pass', 1], ['Day Membership (WHO Standard)', 1], ['Weekly', 7],
            ['Weekly (WHO Standard)', 7], ['week pass', 7], ['Monthly Membership', 30],
            ['Yearly', 365], ['Annual Pass', 365], ['Annually (WHO Standard)', 365],
            [' 2 DAYS ', 2], ['2weeks', 14], ['3 Months (WHO Standard)', 90], ['2 Years', 730],
            ['7', 7], ['36500', 36500], ['100 years', 36500], ['5214 weeks', 36498], ['1216 months', 36480],
            ['', null], ['0', null], ['0 Days', null], ['-1 week', null], ['+1 Day', null],
            ['1.5 weeks', null], ['01 Days', null], ['1e2', null], ['36501', null], ['101 years', null],
            ['5215 weeks', null], ['1217 months', null], ['999999999999999999999999999999999999 Days', null],
            ['Forever', null], ['Daily extra', null], ['Weekly Pass garbage', null], ['Weekly ((WHO Standard))', null],
            ['7 days trailing', null], ['7 days\ninvalid', null], [null, null], [7, null], [false, null], [[], null],
        ],
        'dates' => [
            ['1000-01-01', true], ['2024-02-29', true], ['2000-02-29', true], ['9999-12-31', true],
            ['2026-12-31', true], ['1900-02-29', false], ['2026-02-29', false], ['2024-02-30', false],
            ['2026-04-31', false], ['2026-13-01', false], ['2026-00-01', false], ['2026-01-00', false],
            ['2026-01-32', false], ['0999-12-31', false], ['0000-01-01', false], ['10000-01-01', false],
            ['2026-1-01', false], ['2026-01-1', false], [' 2026-01-01', false], ["2026-01-01\n", false],
            ['2026-01-01 00:00:00', false], ['2026-01-01T00:00:00Z', false], ['', false], [null, false], [20260101, false], [[], false],
        ],
        'ranges' => [
            ['2024-02-29', 'Daily', 1, '2024-02-29', '2024-03-01'],
            ['2024-02-28', 'Weekly (WHO Standard)', 7, '2024-03-05', '2024-03-06'],
            ['2026-01-29', '1 Week', 7, '2026-02-04', '2026-02-05'],
            ['2026-12-29', 'Weekly Pass', 7, '2027-01-04', '2027-01-05'],
            ['2024-02-01', '1 Month', 30, '2024-03-01', '2024-03-02'],
            ['2026-01-31', 'Monthly', 30, '2026-03-01', '2026-03-02'],
            ['2024-02-29', '1 Year', 365, '2025-02-27', '2025-02-28'],
            ['2023-03-01', 'Annual', 365, '2024-02-28', '2024-02-29'],
            ['2026-03-08', '7', 7, '2026-03-14', '2026-03-15'],
            ['1000-01-01', 'Daily', 1, '1000-01-01', '1000-01-02'],
            ['9999-12-30', 'Daily', 1, '9999-12-30', '9999-12-31'],
            ['9999-12-31', 'Daily', null, null, null], ['9999-12-30', 'Weekly', null, null, null],
            ['2026-02-29', 'Weekly', null, null, null], ['2026-10-16', '0 Days', null, null, null],
            [null, 'Daily', null, null, null], ['2026-10-16', null, null, null, null],
        ],
    ];
}
function expectedRange(array $v): ?array
{
    return $v[2] === null ? null : ['booking_date' => $v[0], 'duration_days' => $v[2],
        'end_date' => $v[3], 'expires_at' => $v[3] . ' 23:59:59', 'calendar_end' => $v[4]];
}
function parity(array $v): array
{
    return ['durations' => array_map(fn($x) => packageDurationDays($x[0]), $v['durations']),
        'dates' => array_map(fn($x) => packageCalendarDate($x[0])?->format('Y-m-d'), $v['dates']),
        'ranges' => array_map(fn($x) => packageBookingDates($x[0], $x[1]), $v['ranges'])];
}
function helperTests(): void
{
    $original = date_default_timezone_get();
    try {
        foreach (['UTC', 'America/Los_Angeles', 'Pacific/Kiritimati', 'Asia/Manila'] as $tz) {
            date_default_timezone_set($tz);
            foreach (vectors()['durations'] as $v) same($v[1], packageDurationDays($v[0]), $tz . ' duration ' . json_encode($v[0]));
            foreach (vectors()['dates'] as $v) {
                $parsed = packageCalendarDate($v[0]);
                same($v[1], $parsed !== null, $tz . ' strict date ' . json_encode($v[0]));
                if ($parsed) { same($v[0], $parsed->format('Y-m-d'), 'Date round trip'); same('Asia/Manila', $parsed->getTimezone()->getName(), 'Gym calendar timezone'); }
            }
            foreach (vectors()['ranges'] as $v) same(expectedRange($v), packageBookingDates($v[0], $v[1]), $tz . ' independent range ' . json_encode($v));
            $instant = new DateTimeImmutable('2026-12-31T16:00:00Z');
            same('2027-01-01 00:00:00', packageGymNow($instant)->format('Y-m-d H:i:s'), $tz . ' Manila midnight');
            same('2026-12-31 23:59:59', packageGymNow($instant->modify('-1 second'))->format('Y-m-d H:i:s'), $tz . ' before midnight');
            same($tz, date_default_timezone_get(), 'Helpers must not mutate global timezone');
        }
        foreach ([['2026-02-29', 'Daily', 400], ['2026-10-16', '0 Days', 422], ['9999-12-31', 'Daily', 400], [null, null, 400]] as [$start, $duration, $code]) {
            try { requirePackageBookingDates($start, $duration); throw new RuntimeException('Missing helper denial'); }
            catch (ResponseSignal $r) { same(false, $r->response['success'], 'Required helper denies'); same($code, $r->response['code'], 'Required helper code'); }
        }
        same(expectedRange(vectors()['ranges'][0]), requirePackageBookingDates('2024-02-29', 'Daily'), 'Required helper valid result');
    } finally { date_default_timezone_set($original); }
}
function setEnvironment(string $endpoint, array $options): void
{
    global $environment;
    $environment = ['clock' => '2026-12-31T16:00:00Z', 'logs' => [], 'emails' => [], 'notifications' => [], 'manager_checks' => 0];
    date_default_timezone_set($options['tz'] ?? 'UTC');
    $_SERVER['REQUEST_METHOD'] = $options['method'] ?? ($endpoint === 'packages/update.php' ? 'PUT' : 'POST');
    $_GET = [];
    $_SESSION = empty($options['anonymous']) ? ['user_id' => 101, 'user_role' => $options['role'] ??
        (in_array($endpoint, ['walkin/create.php', 'bookings/update.php', 'packages/create.php', 'packages/update.php'], true) ? 'manager' : 'member')] : [];
    $environment['input'] = array_replace([
        'id' => $endpoint === 'packages/update.php' ? 9 : 501, 'booking_id' => 501, 'package_id' => 9,
        'package' => 'Fixture Pass', 'date' => '2024-02-28', 'contact' => '09000000000', 'notes' => 'Offline test',
        'receipt' => '/offline-receipt.png', 'expires_at' => '2099-12-31 23:59:59', 'duration' => '999 years',
        'customer_name' => 'Walk-in Fixture', 'customer_email' => 'walkin@example.test', 'customer_contact' => '09000000000',
        'payment_method' => 'cash', 'status' => 'verified', 'name' => 'Fixture Pass', 'price' => '700',
        'trainer_ids' => [], 'is_trainer_assisted' => false,
    ], $options['input'] ?? []);
    foreach ($options['omit_input'] ?? [] as $field) unset($environment['input'][$field]);
    $packages = [7 => ['id' => 7, 'name' => 'Original Pass', 'price' => 100.0, 'duration' => $options['original_duration'] ?? '1 Month', 'is_active' => 1],
        9 => ['id' => 9, 'name' => 'Fixture Pass', 'price' => 700.0, 'duration' => $options['duration'] ?? 'Daily', 'is_active' => 1]];
    $booking = array_replace(['id' => 501, 'user_id' => 101, 'name' => 'Offline Member', 'email' => 'member@example.test',
        'contact' => '09000000000', 'package_id' => 7, 'package_name' => 'Original Pass', 'amount' => 100.0,
        'booking_date' => '2024-02-28', 'expires_at' => '2024-03-05 23:59:59', 'receipt_url' => '/original.png',
        'status' => $endpoint === 'bookings/upgrade.php' ? 'verified' : 'pending', 'trainer_id' => null, 'notes' => '',
        'created_at' => '2024-02-01 00:00:00'], $options['booking'] ?? []);
    if ($endpoint === 'bookings/upgrade.php' && !array_key_exists('expires_at', $options['booking'] ?? [])) $booking['expires_at'] = '2027-12-31 23:59:59';
    $bookings = $endpoint === 'bookings/create.php' && empty($options['existing']) ? [] : [501 => $booking];
    $environment['db'] = new MemoryDB($bookings, $packages);
}
function childEndpoint(string $endpoint): never
{
    global $environment;
    check(in_array($endpoint, ENDPOINTS, true), 'Unknown endpoint');
    $options = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    setEnvironment($endpoint, $options);
    $level = ob_get_level();
    try {
        loadActualSource(__DIR__ . '/api/' . $endpoint, true);
        throw new RuntimeException('Endpoint returned without sendResponse');
    } catch (ResponseSignal $r) {
        check($environment['db']->unknownSql === [], 'Caught unknown SQL: ' . implode('; ', $environment['db']->unknownSql));
        $out = $r->response + ['mutations' => $environment['db']->mutations, 'transactions' => $environment['db']->transactions,
            'queries' => $environment['db']->queries, 'bookings' => $environment['db']->bookings,
            'emails' => $environment['emails'], 'notifications' => $environment['notifications'], 'manager_checks' => $environment['manager_checks']];
    } finally {
        while (ob_get_level() > $level) ob_end_clean();
    }
    print json_encode($out, JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
}
function runEndpoint(string $endpoint, array $options): array
{
    $process = proc_open([PHP_BINARY, '-n', '-d', 'date.timezone=UTC', __FILE__, '--endpoint', $endpoint],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
    check(is_resource($process), 'Start isolated PHP child');
    $json = json_encode($options, JSON_THROW_ON_ERROR);
    same(strlen($json), fwrite($pipes[0], $json), 'Write full fixture');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    same(0, proc_close($process), $endpoint . ' child exit: ' . $stderr . $stdout);
    same('', $stderr, 'No child warnings or errors');
    return json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
}
function endpointCase(string $endpoint, array $options, bool $success, int|array $code = 200): array
{
    $r = runEndpoint($endpoint, $options);
    same($success, $r['success'], $endpoint . ' response: ' . $r['message']);
    check(in_array($r['code'], (array)$code, true), $endpoint . ' HTTP-style response code: ' . $r['code']);
    if (!$success) {
        same([], $r['mutations'], 'No booking/payment/package write before denial');
        same([], $r['transactions'], 'No transaction before invalid input/guard denial');
        same([], $r['emails'], 'No email before denial');
        same([], $r['notifications'], 'No notification before denial');
        // Existing-booking conflict may execute harmless auto-expiry maintenance;
        // malformed data and other guards must not even attempt a write statement.
        if ($r['code'] !== 409) foreach ($r['queries'] as $query) {
            check(!preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|REPLACE|CREATE|DROP|TRUNCATE)\b/i', $query['sql']),
                'No attempted SQL write before invalid input/guard denial');
        }
    } else {
        check(count($r['mutations']) > 0, 'Success must actually write to memory DB');
        same(in_array($endpoint, ['bookings/upgrade.php', 'packages/create.php', 'packages/update.php'], true)
            ? ['begin', 'commit'] : [], $r['transactions'], 'Transaction outcome');
    }
    return $r;
}
function tableWrites(array $result, string $table, ?string $operation = null): array
{
    return array_values(array_filter($result['mutations'], fn($m) => $m['table'] === $table && ($operation === null || $m['operation'] === $operation)));
}
function endpointTests(): int
{
    $cases = 0;
    foreach (['bookings/create.php', 'bookings/change-plan.php', 'walkin/create.php', 'bookings/upgrade.php'] as $endpoint) {
        foreach (['Daily' => '2024-02-28 23:59:59', 'Weekly (WHO Standard)' => '2024-03-05 23:59:59'] as $duration => $expiry) {
            $zones = $endpoint === 'bookings/upgrade.php' ? ['UTC', 'America/Los_Angeles', 'Pacific/Kiritimati'] : ['UTC'];
            foreach ($zones as $tz) {
                ++$cases;
                test($endpoint . ' ' . $duration . ' ' . $tz, function () use ($endpoint, $duration, $expiry, $tz) {
                    $r = endpointCase($endpoint, ['duration' => $duration, 'tz' => $tz], true);
                    $writes = tableWrites($r, 'bookings', $endpoint === 'bookings/change-plan.php' ? 'update' : 'insert');
                    same(1, count($writes), 'Exactly one booking insert/edit');
                    $b = $writes[0]['values'];
                    $upgrade = $endpoint === 'bookings/upgrade.php';
                    same($upgrade ? '2027-01-01' : '2024-02-28', $b['booking_date'], 'Chosen DATE preserved; upgrade uses Manila today');
                    same($upgrade ? ($duration === 'Daily' ? '2027-01-01 23:59:59' : '2027-01-07 23:59:59') : $expiry,
                        $b['expires_at'], 'Server duration/expiry, never caller-supplied expiry');
                    if ($endpoint === 'walkin/create.php') {
                        same(null, $b['user_id'], 'Walk-in stays unregistered'); same('verified', $b['status'], 'Walk-in verified');
                        same(1, $r['manager_checks'], 'Actual walk-in manager gate');
                        same([], tableWrites($r, 'bookings', 'update'), 'No overwrite of walk-in start after insert');
                    }
                    if (in_array($endpoint, ['walkin/create.php', 'bookings/upgrade.php'], true)) {
                        same(1, count(tableWrites($r, 'payments')), 'One payment record');
                    }
                    if ($upgrade) {
                        same('pending', $b['status'], 'Upgrade receipt remains pending');
                        same('upgraded', $r['bookings'][501]['status'], 'Old booking preserved as upgraded');
                    }
                });
            }
        }
        foreach (['0 Days', 'Forever', '1.5 weeks', '36501 days'] as $duration) {
            ++$cases;
            test($endpoint . ' invalid DB duration ' . $duration, fn() => endpointCase($endpoint, ['duration' => $duration], false, 422));
        }
        if ($endpoint !== 'bookings/upgrade.php') {
            foreach (['2026-02-29', '2024-02-30', '2026-1-01', '2026-10-16 00:00:00', '', '9999-12-31'] as $start) {
                ++$cases;
                test($endpoint . ' invalid start ' . $start, fn() => endpointCase($endpoint, ['input' => ['date' => $start]], false, 400));
            }
        }
        ++$cases;
        test($endpoint . ' login guard', fn() => endpointCase($endpoint, ['anonymous' => true], false, 401));
    }
    foreach (['UTC', 'America/Los_Angeles', 'Asia/Manila'] as $tz) {
        foreach (['Daily' => '2027-01-01 23:59:59', 'Weekly' => '2027-01-07 23:59:59'] as $duration => $expiry) {
            ++$cases;
            test('Walk-in omitted start defaults to Manila today ' . $tz . ' ' . $duration, function () use ($tz, $duration, $expiry) {
                $r = endpointCase('walkin/create.php', ['tz' => $tz, 'duration' => $duration, 'omit_input' => ['date']], true);
                $writes = tableWrites($r, 'bookings');
                same(1, count($writes), 'Default date performs exactly one booking insert');
                same('insert', $writes[0]['operation'], 'Default date is not a later start-date overwrite');
                same('2027-01-01', $writes[0]['values']['booking_date'], 'Default is Manila date, not host date');
                same($expiry, $writes[0]['values']['expires_at'], 'Default-date purchased range');
                same(null, $writes[0]['values']['user_id'], 'Default-date walk-in is unregistered');
                same(1, count(tableWrites($r, 'payments')), 'Default-date walk-in records payment');
            });
        }
        ++$cases;
        test('Verification uses DB clock, not host/Manila literal ' . $tz, function () use ($tz) {
            $r = endpointCase('bookings/update.php', ['tz' => $tz], true);
            $queries = array_values(array_filter($r['queries'], fn($q) => str_contains(strtolower($q['sql']), 'verified_at')));
            same(1, count($queries), 'Exactly one verification timestamp SQL statement');
            same('UPDATE bookings SET verified_at = CURRENT_TIMESTAMP WHERE id = ?', $queries[0]['sql'], 'Exact DB-clock SQL');
            same('i', $queries[0]['types'], 'Only integer verification ID binding');
            same([501], $queries[0]['values'], 'No wall-clock literal binding');
            same('DB_CURRENT_TIMESTAMP', $r['bookings'][501]['verified_at'], 'Symbolic DB-clock write');
            same('2024-02-28', $r['bookings'][501]['booking_date'], 'DB-clock verification preserves purchase start');
            same('2024-03-05 23:59:59', $r['bookings'][501]['expires_at'], 'DB-clock verification preserves purchase expiry');
        });
    }
    foreach ([['pending', '2027-01-01 23:59:59'], ['verified', '2027-12-31 23:59:59'], ['verified', null]] as [$status, $expiry]) {
        ++$cases;
        test('Create preserves existing ' . $status . ' guard', fn() => endpointCase('bookings/create.php',
            ['existing' => true, 'booking' => ['status' => $status, 'expires_at' => $expiry]], false, 409));
    }
    foreach ([['user_id' => 202], ['status' => 'verified'], ['status' => 'rejected']] as $booking) {
        ++$cases;
        test('Edit ownership/pending ' . json_encode($booking), fn() => endpointCase('bookings/change-plan.php',
            ['booking' => $booking], false, isset($booking['user_id']) ? 404 : 409));
    }
    foreach ([['user_id' => 202], ['status' => 'pending']] as $booking) {
        ++$cases;
        test('Upgrade current owner/verified guard ' . json_encode($booking), fn() => endpointCase('bookings/upgrade.php', ['booking' => $booking], false, 404));
    }
    ++$cases;
    test('Walk-in role guard', fn() => endpointCase('walkin/create.php', ['role' => 'member'], false, 403));
    foreach (['Daily', 'Weekly', '2 Years', 'Forever'] as $laterDuration) {
        ++$cases;
        test('Verification preserves purchase snapshot despite package now ' . $laterDuration, function () use ($laterDuration) {
            $r = endpointCase('bookings/update.php', ['original_duration' => $laterDuration], true);
            same('2024-02-28', $r['bookings'][501]['booking_date'], 'Verification must not move original start');
            same('2024-03-05 23:59:59', $r['bookings'][501]['expires_at'], 'Stored purchased expiry unchanged');
            foreach (tableWrites($r, 'bookings') as $write) {
                check(!isset($write['values']['booking_date']), 'No verification write to original start');
                if (isset($write['values']['expires_at'])) same('2024-03-05 23:59:59', $write['values']['expires_at'], 'No snapshot extension');
            }
            same(1, count(tableWrites($r, 'payments')), 'Verification records payment');
            same('2024-03-05 23:59:59', $r['emails'][0][0]['expiry_date'], 'Email uses purchase snapshot');
        });
    }
    foreach (['Daily' => '2024-02-28 23:59:59', 'Weekly' => '2024-03-05 23:59:59'] as $duration => $expiry) {
        ++$cases;
        test('Verification fills missing expiry from ORIGINAL start ' . $duration, function () use ($duration, $expiry) {
            $r = endpointCase('bookings/update.php', ['original_duration' => $duration, 'booking' => ['expires_at' => null]], true);
            same('2024-02-28', $r['bookings'][501]['booking_date'], 'Original calendar start unchanged');
            same($expiry, $r['bookings'][501]['expires_at'], 'Missing expiry not calculated from verification date');
        });
    }
    foreach ([['original_duration' => '0 Days', 'booking' => ['expires_at' => null]],
        ['booking' => ['expires_at' => null, 'booking_date' => '2026-02-29']]] as $options) {
        ++$cases;
        test('Verification missing expiry invalid source denied before writes ' . json_encode($options),
            fn() => endpointCase('bookings/update.php', $options, false, isset($options['original_duration']) ? 422 : 400));
    }
    ++$cases;
    test('Verification member role guard', fn() => endpointCase('bookings/update.php', ['role' => 'member'], false, 401));
    foreach (['packages/create.php', 'packages/update.php'] as $endpoint) {
        foreach (['Daily', 'Weekly (WHO Standard)'] as $duration) {
            ++$cases;
            test($endpoint . ' valid ' . $duration, function () use ($endpoint, $duration) {
                $r = endpointCase($endpoint, ['input' => ['duration' => $duration]], true);
                same($duration, tableWrites($r, 'packages')[0]['values']['duration'], 'Validated package duration persisted');
            });
        }
        foreach (['Forever', '0 Days', '1.5 weeks', '36501 days'] as $duration) {
            ++$cases;
            test($endpoint . ' invalid ' . $duration, fn() => endpointCase($endpoint, ['input' => ['duration' => $duration]], false, [400, 422]));
        }
        ++$cases;
        test($endpoint . ' role guard', fn() => endpointCase($endpoint, ['role' => 'member', 'input' => ['duration' => 'Daily']], false, 403));
    }
    return $cases;
}

try {
    check(!extension_loaded('mysqli'), 'Use php -n: real mysqli must not be loaded');
    loadActualSource(__DIR__ . '/api/package-dates.php', false);
    if (($argv[1] ?? '') === '--vectors') { print json_encode(vectors(), JSON_THROW_ON_ERROR); exit(0); }
    if (($argv[1] ?? '') === '--parity') { print json_encode(parity(json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR)), JSON_THROW_ON_ERROR); exit(0); }
    if (($argv[1] ?? '') === '--endpoint') childEndpoint($argv[2] ?? '');
    test('Shared helper regression', fn() => helperTests());
    $cases = endpointTests();
    if ($failures) {
        foreach ($failures as $failure) fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
        fwrite(STDERR, count($failures) . " failing groups; $assertions parent assertions; $cases bounded endpoint cases.\n");
        exit(1);
    }
    print "PASS: $assertions parent assertions; $cases isolated actual-endpoint cases. No DB, HTTP, application bootstrap, or external writes.\n";
    print "Scope: SQL contracts in memory; no real TIMESTAMP/session conversion, trainer/admin recipients, schema migrations, transaction isolation or concurrency emulation.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
