<?php
/**
 * Offline source-level regression: run php.exe -n test-forgot-password.php.
 * No application bootstrap, HTTP, database, mail, sessions or filesystem writes.
 * The SQL fixture checks guarded statement semantics, NOT MySQL/MyISAM atomicity
 * or crash safety. Real random_int/password_hash are used behind counted wrappers.
 */
namespace FitGymForgotPasswordOffline;

use Error;
use Exception;
use RuntimeException;

final class GuardFailure extends Error {}
final class ResponseSignal extends Error
{
    public function __construct(public array $response) { parent::__construct('Endpoint response'); }
}

function check(bool $condition, string $message): void
{
    if (!$condition) throw new GuardFailure($message);
}

final class Sandbox
{
    public static ?MemoryDB $active = null;
    public static array $sources = [];
    public static int $passed = 0;
    public static int $failed = 0;
}

/** Strict token whitelist; remove only the two literal require_once statements. */
function safeSource(string $source, string $endpoint): string
{
    $tokens = \token_get_all($source, TOKEN_PARSE);
    $allowedTokens = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_VARIABLE, T_STRING,
        T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_IF, T_ELSE, T_TRY, T_CATCH, T_THROW,
        T_NEW, T_OBJECT_OPERATOR, T_BOOLEAN_OR, T_BOOLEAN_AND, T_IS_IDENTICAL,
        T_IS_NOT_IDENTICAL, T_IS_SMALLER_OR_EQUAL, T_COALESCE, T_LOGICAL_OR, T_DOUBLE_ARROW];
    $variables = ['$_SERVER', '$data', '$email', '$otp', '$step', '$password', '$conn',
        '$stmt', '$user', '$expiry', '$e', '$otpData', '$hashed', '$userId', '$otpId',
        '$update', '$changed'];
    $functions = ['sendResponse', 'getRequestData', 'getDBConnection', 'is_string',
        'is_int', 'trim', 'strtolower', 'filter_var', 'random_int', 'str_pad',
        'sendPasswordResetEmail', 'error_log', 'in_array', 'preg_match', 'strlen',
        'strpos', 'password_hash', 'array_key_exists'];
    $methods = ['prepare', 'bind_param', 'execute', 'get_result', 'fetch_assoc', 'close'];
    $identifiers = \array_merge($functions, $methods, ['RuntimeException', 'Exception',
        'null', 'true', 'false', 'FILTER_VALIDATE_EMAIL', 'STR_PAD_LEFT',
        'PASSWORD_BCRYPT', 'error', 'affected_rows']);
    $allowedTokens[] = T_STRING_CAST;
    $allowedTokens[] = T_INT_CAST;
    $out = '';
    $includes = [];
    $significant = [];
    for ($i = 0, $count = \count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        if (\is_array($token) && $token[0] === T_OPEN_TAG) {
            check($i === 0, 'Unexpected PHP opening tag');
            continue;
        }
        if (\is_array($token) && $token[0] === T_REQUIRE_ONCE) {
            $parts = [];
            for ($j = $i + 1; $j < $count; $j++) {
                $part = $tokens[$j];
                if (\is_array($part) && \in_array($part[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
                $parts[] = $part;
                if ($part === ';') break;
            }
            check(\count($parts) === 2 && \is_array($parts[0]) &&
                $parts[0][0] === T_CONSTANT_ENCAPSED_STRING && $parts[1] === ';',
                'Unexpected include expression');
            $literal = $parts[0][1];
            check(\in_array($literal, ["'../config.php'", "'../email.php'"], true), 'Unexpected include path');
            $includes[] = $literal;
            $i = $j;
            continue;
        }
        if (\is_array($token)) {
            check(\in_array($token[0], $allowedTokens, true), 'Unexpected source token');
            if ($token[0] === T_VARIABLE) check(\in_array($token[1], $variables, true), 'Unexpected source variable');
            if ($token[0] === T_STRING) check(\in_array($token[1], $identifiers, true), 'Unexpected source function or identifier');
            if (!\in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $significant[] = $token;
            $out .= $token[1];
        } else {
            check(\str_contains('{}();[],.=!?:><', $token), 'Unexpected source operator');
            $significant[] = $token;
            $out .= $token;
        }
    }
    $expectedIncludes = $endpoint === 'forgot' ? ["'../config.php'", "'../email.php'"] : ["'../config.php'"];
    check($includes === $expectedIncludes, 'Endpoint bootstrap imports changed');
    foreach ($significant as $i => $token) {
        $next = $significant[$i + 1] ?? null;
        $previous = $significant[$i - 1] ?? null;
        if ($next === '(') {
            check(!\is_array($token) || $token[0] !== T_VARIABLE, 'Dynamic function call forbidden');
            check($token !== ')' && $token !== ']', 'Indirect function call forbidden');
            if (\is_array($token) && $token[0] === T_STRING) {
                if (\is_array($previous) && $previous[0] === T_OBJECT_OPERATOR) {
                    check(\in_array($token[1], $methods, true), 'Unexpected object method');
                } elseif (\is_array($previous) && $previous[0] === T_NEW) {
                    check($token[1] === 'RuntimeException', 'Unexpected constructed class');
                } else {
                    check(\in_array($token[1], $functions, true), 'Unexpected direct call');
                }
            }
        }
        if (\is_array($token) && $token[0] === T_OBJECT_OPERATOR) {
            check(\is_array($next) && $next[0] === T_STRING, 'Dynamic object access forbidden');
        }
    }
    return "namespace FitGymForgotPasswordOffline; use \\Exception; use \\RuntimeException;\n" . $out;
}

final class MemoryDB
{
    public const SQL = [
        'account' => 'SELECT id, name, email_verified FROM users WHERE email = ? LIMIT 1',
        'delete' => 'DELETE FROM otps WHERE email = ?',
        'insert' => 'INSERT INTO otps (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))',
        'expiry' => 'SELECT expires_at, UNIX_TIMESTAMP(expires_at) as expires_at_timestamp FROM otps WHERE email = ? AND code = ? ORDER BY created_at DESC LIMIT 1',
        'lookup' => 'SELECT o.id, u.id AS user_id, TIMESTAMPDIFF(SECOND, NOW(), o.expires_at) AS seconds_remaining FROM otps o INNER JOIN users u ON u.email = o.email WHERE o.email = ? AND o.code = ? AND o.used = 0 AND u.email_verified = 1 ORDER BY o.created_at DESC, o.id DESC LIMIT 1',
        'redeem' => 'UPDATE users u INNER JOIN otps o ON o.email = u.email SET u.password = ?, u.updated_at = NOW(), o.used = 1 WHERE u.id = ? AND o.id = ? AND u.email_verified = 1 AND o.used = 0 AND o.expires_at > NOW()',
    ];
    public array $users = [];
    public array $otps = [];
    public int $now = 1900000000;
    public int $nextOtpId = 100;
    public array $request = [];
    public string $endpoint = '';
    public array $trace = [];
    public array $statements = [];
    public array $connections = [];
    public array $logs = [];
    public array $mail = [];
    public array $randomCalls = [];
    public array $hashCalls = [];
    public array $secrets = [];
    public ?array $selected = null;
    public array $failures = [];
    public bool $sendOk = true;
    public bool $sendThrows = false;
    public int $dbCalls = 0;
    public $beforeRedeem = null;
    public ?array $afterContender = null;

    public function __construct(string $role = 'user')
    {
        $old = \password_hash('Original-password!', PASSWORD_BCRYPT);
        $this->users = [
            7 => ['id' => 7, 'email' => 'member@example.test', 'name' => 'Member',
                'role' => $role, 'email_verified' => 1, 'password' => $old, 'updated_at' => 123],
            19 => ['id' => 19, 'email' => 'other@example.test', 'name' => 'Other',
                'role' => 'admin', 'email_verified' => 1, 'password' => $old, 'updated_at' => 123],
        ];
        $this->secrets = [$old, 'Original-password!', '__VERIFY_ONLY__'];
    }

    public function seed(string $code = '314159', int $remaining = 300, int $used = 0, string $email = 'member@example.test'): int
    {
        $id = $this->nextOtpId++;
        $this->otps[$id] = ['id' => $id, 'email' => $email, 'code' => $code,
            'expires_at' => $this->now + $remaining, 'used' => $used, 'created_at' => $this->now];
        $this->secrets[] = $code;
        return $id;
    }

    public function snapshot(): array { return [$this->users, $this->otps]; }
    public function email(): string
    {
        return \is_string($this->request['email'] ?? null) ? \trim(\strtolower($this->request['email'])) : '';
    }
    public function code(): string { return \trim((string)($this->request['otp'] ?? '')); }
    public function generated(): string
    {
        check(\count($this->randomCalls) === 1, 'OTP must come from one production random_int call');
        return \str_pad((string)$this->randomCalls[0][2], 6, '0', STR_PAD_LEFT);
    }
    public function failure(string $operation): ?string { return $this->failures[$operation] ?? null; }
    public function injectedException(): RuntimeException
    {
        // Deliberately sensitive diagnostic: endpoint must log only generic text.
        return new RuntimeException('Sensitive fixture failure ' . \implode(' ', $this->secrets));
    }
    public function eligibleRows(string $email, string $code): array
    {
        $rows = [];
        foreach ($this->otps as $otp) {
            if ($otp['email'] !== $email || $otp['code'] !== $code || $otp['used'] !== 0) continue;
            foreach ($this->users as $user) {
                if ($user['email'] === $otp['email'] && $user['email_verified'] === 1) {
                    $rows[] = $otp + ['user_id' => $user['id'], 'seconds_remaining' => $otp['expires_at'] - $this->now];
                }
            }
        }
        \usort($rows, static fn($a, $b) => ($b['created_at'] <=> $a['created_at']) ?: ($b['id'] <=> $a['id']));
        return $rows;
    }
}

final class MemoryConnection
{
    public bool $closed = false;
    public function __construct(private MemoryDB $db) {}
    public function prepare(string $sql): MemoryStatement
    {
        check(!$this->closed, 'Preparing on closed connection');
        $normalized = \preg_replace('/\s+/', ' ', \trim($sql));
        $operation = \array_search($normalized, MemoryDB::SQL, true);
        check($operation !== false, 'Unexpected SQL shape (no alternate updates or transactions)');
        check(\in_array($operation, $this->db->endpoint === 'forgot' ? ['account', 'delete', 'insert', 'expiry'] : ['lookup', 'redeem'], true), 'SQL used by wrong endpoint');
        $this->db->trace[] = $operation;
        if ($this->db->failure($operation) === 'prepare') throw $this->db->injectedException();
        $stmt = new MemoryStatement($this->db, $operation, $sql);
        $this->db->statements[] = $stmt;
        return $stmt;
    }
    public function close(): void { $this->closed = true; }
    public function __call(string $name, array $args): never { throw new GuardFailure('Unexpected connection method'); }
    public function __get(string $name): never { throw new GuardFailure('Unexpected connection property'); }
}

final class MemoryResult
{
    private bool $read = false;
    public function __construct(private ?array $row) {}
    public function fetch_assoc(): ?array
    {
        check(!$this->read, 'Unexpected repeated result fetch');
        $this->read = true;
        return $this->row;
    }
    public function __call(string $name, array $args): never { throw new GuardFailure('Unexpected result method'); }
}

final class MemoryStatement
{
    public string $error = '';
    public int $affected_rows = 0;
    public bool $closed = false;
    public bool $executed = false;
    private ?array $bindings = null;
    private string $types = '';
    private ?array $row = null;

    public function __construct(private MemoryDB $db, public string $operation, private string $sql) {}
    public function bind_param(string $types, &...$bindings): bool
    {
        $expected = ['account' => 's', 'delete' => 's', 'insert' => 'ss', 'expiry' => 'ss', 'lookup' => 'ss', 'redeem' => 'sii'][$this->operation];
        check(!$this->closed && $this->bindings === null, 'Unexpected statement binding lifecycle');
        check($types === $expected && \count($bindings) === \strlen($types) && \substr_count($this->sql, '?') === \count($bindings), 'Incorrect placeholder signature or count');
        $this->types = $types;
        $this->bindings = [];
        foreach ($bindings as &$value) $this->bindings[] =& $value;
        unset($value);
        return true;
    }
    public function execute(): bool
    {
        check(!$this->closed && !$this->executed && $this->bindings !== null, 'Unexpected statement execution lifecycle');
        $this->executed = true;
        $p = $this->bindings;
        foreach ($p as $i => $value) check($this->types[$i] === 's' ? \is_string($value) : \is_int($value), 'Incorrect bound value type');
        if ($this->operation !== 'redeem') check($p[0] === $this->db->email(), 'Incorrect bound account email');
        if ($this->operation === 'lookup') check($p[1] === $this->db->code(), 'Incorrect bound reset code');
        if (\in_array($this->operation, ['insert', 'expiry'], true)) {
            check($p[1] === $this->db->generated() && \preg_match('/^\d{6}$/D', $p[1]) === 1, 'Inserted code must be actual padded cryptographic draw');
        }
        if ($this->operation === 'redeem') {
            check($this->db->selected !== null && $p[1] === $this->db->selected['user_id'] && $p[2] === $this->db->selected['id'], 'Incorrect eligible user or OTP id binding');
            check(\count($this->db->hashCalls) === 1 && $p[0] === $this->db->hashCalls[0][2], 'Update must bind freshly generated bcrypt hash');
            check(\password_verify($this->db->request['password'], $p[0]), 'Bound hash does not match submitted password');
        }
        $failure = $this->db->failure($this->operation);
        if ($failure === 'throw') throw $this->db->injectedException();
        if ($failure === 'execute') return false;
        if ($failure === 'error') { $this->error = 'Sensitive statement error ' . \implode(' ', $this->db->secrets); return true; }
        switch ($this->operation) {
            case 'account':
                foreach ($this->db->users as $user) if ($user['email'] === $p[0]) { $this->row = \array_intersect_key($user, \array_flip(['id', 'name', 'email_verified'])); break; }
                break;
            case 'delete':
                foreach ($this->db->otps as $id => $otp) if ($otp['email'] === $p[0]) { unset($this->db->otps[$id]); $this->affected_rows++; }
                break;
            case 'insert':
                $this->db->seed($p[1]);
                $this->affected_rows = 1;
                break;
            case 'expiry':
                $rows = \array_values(\array_filter($this->db->otps, static fn($o) => $o['email'] === $p[0] && $o['code'] === $p[1]));
                \usort($rows, static fn($a, $b) => $b['created_at'] <=> $a['created_at']);
                if ($rows && $failure !== 'missing') $this->row = ['expires_at' => \gmdate('Y-m-d H:i:s', $rows[0]['expires_at']), 'expires_at_timestamp' => (string)$rows[0]['expires_at']];
                break;
            case 'lookup':
                $rows = $this->db->eligibleRows($p[0], $p[1]);
                if ($rows) $this->row = $this->db->selected = \array_intersect_key($rows[0], \array_flip(['id', 'user_id', 'seconds_remaining']));
                break;
            case 'redeem':
                if ($this->db->beforeRedeem !== null) {
                    ($this->db->beforeRedeem)($this->db, $p[1], $p[2]);
                    $this->db->afterContender = $this->db->snapshot();
                    $this->db->beforeRedeem = null;
                }
                // Re-evaluate the JOIN and every predicate at execution, not at lookup.
                $u = $this->db->users[$p[1]] ?? null;
                $o = $this->db->otps[$p[2]] ?? null;
                if ($u && $o && $u['email'] === $o['email'] && $u['email_verified'] === 1 && $o['used'] === 0 && $o['expires_at'] > $this->db->now) {
                    $this->db->users[$p[1]]['password'] = $p[0];
                    $this->db->users[$p[1]]['updated_at'] = $this->db->now;
                    $this->db->otps[$p[2]]['used'] = 1;
                    $this->affected_rows = 2;
                }
                break;
        }
        return true;
    }
    public function get_result(): MemoryResult
    {
        check($this->executed && !$this->closed && \in_array($this->operation, ['account', 'expiry', 'lookup'], true), 'Unexpected result request');
        if ($this->db->failure($this->operation) === 'result') throw $this->db->injectedException();
        return new MemoryResult($this->row);
    }
    public function close(): void { $this->closed = true; }
    public function __call(string $name, array $args): never { throw new GuardFailure('Unexpected statement method'); }
    public function __get(string $name): never { throw new GuardFailure('Unexpected statement property'); }
}

function active(): MemoryDB
{
    check(Sandbox::$active !== null, 'Endpoint dependency outside sandbox');
    return Sandbox::$active;
}
function getRequestData(): array { return active()->request; }
function getDBConnection(): MemoryConnection
{
    $db = active();
    $db->dbCalls++;
    check($db->dbCalls === 1, 'Unexpected repeated database bootstrap');
    if ($db->failure('connection')) throw $db->injectedException();
    $connection = new MemoryConnection($db);
    $db->connections[] = $connection;
    return $connection;
}
function sendResponse($success, $message, $data = null, $status = 200): never
{
    check(\is_bool($success) && \is_string($message) && \is_int($status), 'Unexpected response envelope');
    throw new ResponseSignal(['success' => $success, 'message' => $message, 'data' => $data, 'status' => $status]);
}
function error_log($message): bool
{
    check(\is_string($message), 'Unexpected log payload');
    active()->logs[] = $message;
    return true;
}
function random_int($min, $max): int
{
    $db = active();
    check($db->endpoint === 'forgot' && $min === 0 && $max === 999999, 'Unexpected cryptographic generator bounds');
    if ($db->failure('random')) throw $db->injectedException();
    $draw = \random_int($min, $max);
    $db->randomCalls[] = [$min, $max, $draw];
    return $draw;
}
function password_hash($password, $algorithm): string
{
    $db = active();
    check($db->endpoint === 'reset' && ($db->request['_step'] ?? 'reset') === 'reset', 'Verify-only attempted password hashing');
    check($password === ($db->request['password'] ?? null) && $password !== '__VERIFY_ONLY__' && $algorithm === PASSWORD_BCRYPT, 'Unexpected password hashing call');
    if ($db->failure('hash')) throw $db->injectedException();
    $hash = \password_hash($password, $algorithm);
    $db->hashCalls[] = [$password, $algorithm, $hash];
    $db->secrets[] = $hash;
    return $hash;
}
function sendPasswordResetEmail($email, $code, $name): bool
{
    $db = active();
    check($db->endpoint === 'forgot' && $email === $db->email() && $code === $db->generated(), 'Unexpected mail bindings');
    $eligible = false;
    foreach ($db->users as $user) if ($user['email'] === $email && $user['email_verified'] === 1 && $user['name'] === $name) $eligible = true;
    check($eligible, 'Email sent to ineligible account');
    foreach ($db->connections as $conn) check($conn->closed, 'Mail must be sent after database connection closes');
    $db->mail[] = [$email, $code, $name];
    if ($db->sendThrows) throw $db->injectedException();
    return $db->sendOk;
}

function assertPrivate(MemoryDB $db, array $response): void
{
    $allowedKeys = ['email', 'expires_at', 'expires_at_timestamp', 'verified', 'reason'];
    if ($response['data'] !== null) {
        check(\is_array($response['data']), 'Unexpected response data type');
        foreach ($response['data'] as $key => $value) {
            check(\in_array($key, $allowedKeys, true) && \is_scalar($value), 'Sensitive or unexpected response data key');
            foreach ($db->secrets as $secret) if ($secret !== '') check((string)$value !== $secret, 'Secret exposed in response data');
        }
    }
    foreach (\array_merge([$response['message']], $db->logs) as $text) {
        foreach ($db->secrets as $secret) if ($secret !== '') check(!\str_contains($text, $secret), 'Secret exposed in response or logs');
    }
    foreach ($db->logs as $log) check(\in_array($log, ['Password reset email delivery failed', 'Password reset code request failed', 'Password reset request failed'], true), 'Non-generic diagnostic log');
}

function invoke(MemoryDB $db, string $endpoint, array $data, string $method = 'POST'): array
{
    check(Sandbox::$active === null && \session_status() === PHP_SESSION_NONE, 'Unexpected active sandbox or session');
    $db->request = $data;
    $db->endpoint = $endpoint;
    $db->trace = $db->statements = $db->connections = $db->logs = $db->mail = $db->randomCalls = $db->hashCalls = [];
    $db->selected = null;
    $db->afterContender = null;
    $db->dbCalls = 0;
    if (\is_string($data['password'] ?? null)) $db->secrets[] = $data['password'];
    $server = $_SERVER;
    $_SERVER = ['REQUEST_METHOD' => $method];
    $includes = \get_included_files();
    Sandbox::$active = $db;
    \ob_start();
    try {
        try {
            // Only already-validated actual endpoint text is evaluated; no replacements
            // of SQL, generation, hashing, control flow, responses or exception handlers.
            (static function (string $source): void { eval($source); })(Sandbox::$sources[$endpoint]);
            throw new GuardFailure('Endpoint returned without terminating response');
        } catch (ResponseSignal $signal) {
            $response = $signal->response;
        }
    } finally {
        $output = \ob_get_clean();
        Sandbox::$active = null;
        $_SERVER = $server;
    }
    check($output === '', 'Endpoint produced unexpected output');
    check(\get_included_files() === $includes && \session_status() === PHP_SESSION_NONE, 'Endpoint loaded application files or started session');
    foreach ($db->connections as $conn) check($conn->closed, 'Endpoint leaked database connection');
    assertPrivate($db, $response);
    return $response;
}

function expectResponse(array $r, bool $success, int $status, ?string $reason = null): void
{
    check($r['success'] === $success && $r['status'] === $status, 'Unexpected response success/status');
    check(($r['data']['reason'] ?? null) === $reason, 'Unexpected response reason');
}
function resetData(array $changes = []): array
{
    return \array_replace(['email' => 'member@example.test', 'otp' => '314159', '_step' => 'reset', 'password' => 'Replacement-password!'], $changes);
}
function noMutation(MemoryDB $db, array $before): void
{
    check($db->snapshot() === $before, 'Unexpected user or OTP mutation');
    check($db->hashCalls === [] && !\in_array('redeem', $db->trace, true), 'Rejected or verify-only request attempted hashing/update');
}
function scenario(string $name, callable $test): void
{
    try {
        $test();
        Sandbox::$passed++;
    } catch (\Throwable $e) {
        Sandbox::$failed++;
        // Do not dump fixture state, responses, bindings, mail or sensitive exceptions.
        $detail = $e instanceof GuardFailure ? $e->getMessage() : 'Unexpected ' . $e::class;
        \fwrite(STDERR, 'FAIL ' . $name . ': ' . $detail . PHP_EOL);
    }
}
function rejects(callable $work): void
{
    try { $work(); } catch (GuardFailure $e) { return; }
    throw new GuardFailure('Unsafe operation was not rejected');
}

if (PHP_SAPI !== 'cli') throw new GuardFailure('Run this offline regression from PHP CLI');
check(\php_ini_loaded_file() === false && \php_ini_scanned_files() === false, 'Use PHP -n (no configuration bootstrap)');
check(!\extension_loaded('mysqli'), 'Offline regression must not load mysqli');
\set_error_handler(static function (): never { throw new GuardFailure('Runtime warning in offline fixture'); });

try {
    foreach (['forgot' => 'forgot-password.php', 'reset' => 'reset-password.php'] as $key => $file) {
        $source = \file_get_contents(__DIR__ . '/api/auth/' . $file);
        check($source !== false, 'Endpoint source missing');
        Sandbox::$sources[$key] = safeSource($source, $key);
    }
} catch (\Throwable $e) {
    \fwrite(STDERR, 'FAIL endpoint source safety validation' . PHP_EOL);
    exit(1);
}

scenario('sandbox rejects unexpected includes/functions/dynamic calls', static function (): void {
    $source = \file_get_contents(__DIR__ . '/api/auth/reset-password.php');
    foreach ([
        \str_replace("'../config.php'", "'../../config.php'", $source),
        $source . "\nsession_start();",
        $source . "\nfile_get_contents('https://example.test');",
        $source . "\nrequire '../email.php';",
        $source . "\n" . '$data();',
        $source . "\n\\random_int(0, 999999);",
        $source . "\nfunction hidden() {}",
    ] as $unsafe) {
        // Validation itself must reject before any source evaluation.
        rejects(static fn() => safeSource($unsafe, 'reset'));
    }
});

scenario('fixture fails closed for SQL, signatures and bindings', static function (): void {
    $db = new MemoryDB(); $db->request = resetData(); $db->endpoint = 'reset';
    $conn = new MemoryConnection($db);
    foreach (['UPDATE users SET password = ? WHERE id = ?', 'UPDATE otps SET used = 1 WHERE id = ?', 'START TRANSACTION',
        \str_replace('AND o.used = 0', '', MemoryDB::SQL['redeem']),
        \str_replace('AND u.email_verified = 1', '', MemoryDB::SQL['lookup']),
        \str_replace('AND o.expires_at > NOW()', '', MemoryDB::SQL['redeem'])] as $sql) rejects(static fn() => $conn->prepare($sql));
    rejects(static fn() => $conn->begin_transaction());
    $stmt = $conn->prepare(MemoryDB::SQL['lookup']); $email = $db->email(); $code = $db->code();
    rejects(static function () use ($stmt, &$email, &$code): void { $stmt->bind_param('si', $email, $code); });
    $stmt->bind_param('ss', $email, $code); $email = 'other@example.test';
    rejects(static fn() => $stmt->execute());
    $db->seed(); $db->selected = ['id' => 100, 'user_id' => 7];
    $stmt = $conn->prepare(MemoryDB::SQL['redeem']); $hash = 'not-a-hash'; $userId = 19; $otpId = 100;
    $stmt->bind_param('sii', $hash, $userId, $otpId);
    rejects(static fn() => $stmt->execute());
});

foreach (['manager', 'admin', 'trainer', 'user'] as $role) {
    foreach (['reset', 'omitted'] as $finalStep) {
        scenario('full lifecycle ' . $role . '/' . $finalStep, static function () use ($role, $finalStep): void {
            $db = new MemoryDB($role); $bystander = $db->users[19]; $db->seed('271828', 300, 0, 'other@example.test');
            $r = invoke($db, 'forgot', ['email' => '  MEMBER@EXAMPLE.TEST  ']);
            expectResponse($r, true, 200);
            check($db->trace === ['account', 'delete', 'insert', 'expiry'] && \count($db->mail) === 1 && \count($db->randomCalls) === 1, 'Request sequence or delivery incorrect');
            $code = $db->mail[0][1]; $rows = $db->eligibleRows('member@example.test', $code); $id = $rows[0]['id'];
            check($rows[0]['expires_at'] === $db->now + 300 && (int)$r['data']['expires_at_timestamp'] === $db->now + 300, 'Expiry must use fixture DB clock and five-minute SQL');
            check($r['data']['email'] === 'member@example.test' && $r['data']['expires_at'] === \gmdate('Y-m-d H:i:s', $db->now + 300), 'Request metadata incorrect');
            $before = $db->snapshot();
            foreach ([[], ['password' => '__VERIFY_ONLY__'], ['password' => ['ignored']]] as $extra) {
                $r = invoke($db, 'reset', ['email' => 'member@example.test', 'otp' => $code, '_step' => 'verify'] + $extra);
                expectResponse($r, true, 200); check($r['data'] === ['verified' => true], 'Verify response incorrect');
                noMutation($db, $before); check($db->trace === ['lookup'], 'Verify must only look up OTP');
            }
            $data = resetData(['otp' => $code]); if ($finalStep === 'omitted') unset($data['_step']);
            $r = invoke($db, 'reset', $data); expectResponse($r, true, 200);
            check($db->trace === ['lookup', 'redeem'] && \count($db->hashCalls) === 1 && $db->otps[$id]['used'] === 1, 'Final reset must perform exactly one guarded joined update');
            check(\password_verify($data['password'], $db->users[7]['password']) && $db->users[7]['updated_at'] === $db->now, 'Password not saved as bcrypt');
            check($db->users[19] === $bystander && $db->otps[100]['used'] === 0, 'Wrong account/token updated');
            $after = $db->snapshot();
            foreach (['verify', 'reset'] as $step) {
                $r = invoke($db, 'reset', resetData(['otp' => $code, '_step' => $step]));
                expectResponse($r, false, 400, 'invalid_otp'); noMutation($db, $after);
            }
        });
    }
}

foreach (['forgot', 'reset'] as $endpoint) {
    foreach (['GET', 'PUT', 'DELETE'] as $method) scenario($endpoint . ' rejects ' . $method, static function () use ($endpoint, $method): void {
        $db = new MemoryDB(); $db->seed(); $before = $db->snapshot();
        expectResponse(invoke($db, $endpoint, resetData(), $method), false, 405);
        noMutation($db, $before); check($db->dbCalls === 0 && $db->mail === [], 'Method rejection touched dependencies');
    });
}

$badEmails = [[], ['email' => ''], ['email' => '   '], ['email' => null], ['email' => []], ['email' => 123], ['email' => true], ['email' => 'not-an-email'], ['email' => "member@example.test\nInjected"]];
foreach ($badEmails as $index => $data) scenario('request malformed email ' . $index, static function () use ($data): void {
    $db = new MemoryDB(); $db->seed(); $before = $db->snapshot();
    expectResponse(invoke($db, 'forgot', $data), false, 400);
    noMutation($db, $before); check($db->dbCalls === 0 && $db->randomCalls === [] && $db->mail === [], 'Malformed request touched dependencies');
});

$badReset = [
    'missing-all' => [], 'missing-email' => ['otp' => '314159'], 'missing-otp' => ['email' => 'member@example.test'],
    'array-email' => resetData(['email' => []]), 'null-email' => resetData(['email' => null]), 'invalid-email' => resetData(['email' => 'bad']),
    'null-otp' => resetData(['otp' => null]), 'array-otp' => resetData(['otp' => []]), 'boolean-otp' => resetData(['otp' => true]), 'float-otp' => resetData(['otp' => 314159.0]),
    'empty-otp' => resetData(['otp' => '']), 'short-otp' => resetData(['otp' => '12345']), 'long-otp' => resetData(['otp' => '1234567']),
    'letter-otp' => resetData(['otp' => '12a456']), 'newline-otp' => resetData(['otp' => "123\n456"]), 'negative-otp' => resetData(['otp' => '-12345']),
    'unknown-step' => resetData(['_step' => 'bogus']),
    // An explicitly supplied null is malformed, not an omitted legacy step.
    'null-step' => resetData(['_step' => null]), 'array-step' => resetData(['_step' => []]), 'integer-step' => resetData(['_step' => 1]),
];
foreach ($badReset as $index => $data) scenario('reset malformed common input ' . $index, static function () use ($data): void {
    $db = new MemoryDB(); $db->seed(); $before = $db->snapshot();
    expectResponse(invoke($db, 'reset', $data), false, 400);
    noMutation($db, $before); check($db->dbCalls === 0, 'Malformed reset opened database');
});
foreach (['', 'short', \str_repeat('x', 73), \str_repeat('é', 37), "secret\0tail", '__VERIFY_ONLY__', null, 123456, true, []] as $index => $password) {
    foreach (['reset', 'omitted'] as $step) scenario('reset malformed password ' . $index . '/' . $step, static function () use ($password, $step): void {
        $db = new MemoryDB(); $db->seed(); $before = $db->snapshot(); $data = resetData(['password' => $password]);
        if ($step === 'omitted') unset($data['_step']);
        expectResponse(invoke($db, 'reset', $data), false, 400); noMutation($db, $before);
        check($db->dbCalls === 0, 'Malformed password reached lookup/hash');
    });
}
scenario('missing final password rejected', static function (): void {
    $db = new MemoryDB(); $db->seed(); $before = $db->snapshot(); $data = resetData(); unset($data['password']);
    expectResponse(invoke($db, 'reset', $data), false, 400); noMutation($db, $before); check($db->dbCalls === 0, 'Missing password opened database');
});
foreach (['verify', 'reset'] as $step) {
    foreach (['wrong-code', 'wrong-email', 'used', 'expired', 'zero-seconds', 'unverified', 'missing-user'] as $kind) {
        scenario('ineligible OTP ' . $kind . '/' . $step, static function () use ($kind, $step): void {
            $db = new MemoryDB(); $id = $db->seed(); $data = resetData(['_step' => $step]); $reason = 'invalid_otp';
            if ($kind === 'wrong-code') $data['otp'] = '000000';
            if ($kind === 'wrong-email') $data['email'] = 'other@example.test';
            if ($kind === 'used') $db->otps[$id]['used'] = 1;
            if ($kind === 'expired' || $kind === 'zero-seconds') { $db->otps[$id]['expires_at'] = $db->now - ($kind === 'expired' ? 1 : 0); $reason = 'expired_otp'; }
            if ($kind === 'unverified') $db->users[7]['email_verified'] = 0;
            if ($kind === 'missing-user') unset($db->users[7]);
            $before = $db->snapshot(); expectResponse(invoke($db, 'reset', $data), false, 400, $reason);
            noMutation($db, $before); check($db->trace === ['lookup'], 'Ineligible code reached update');
        });
    }
}
scenario('unknown and unverified request eligibility stays generic', static function (): void {
    $db = new MemoryDB(); $db->seed(); $before = $db->snapshot();
    $unknown = invoke($db, 'forgot', ['email' => 'unknown@example.test']); expectResponse($unknown, true, 200);
    noMutation($db, $before); check($db->trace === ['account'] && $db->randomCalls === [] && $db->mail === [], 'Unknown account received OTP');
    $db->users[7]['email_verified'] = 0; $before = $db->snapshot();
    $unverified = invoke($db, 'forgot', ['email' => 'member@example.test']); expectResponse($unverified, true, 200);
    check($unknown['message'] === $unverified['message'] && $unverified['data'] === ['email' => 'member@example.test'], 'Account enumeration response');
    noMutation($db, $before); check($db->trace === ['account'] && $db->randomCalls === [] && $db->mail === [], 'Unverified account received OTP');
});
foreach (['123456', \str_repeat('z', 72), \str_repeat('é', 36)] as $index => $password) scenario('valid password byte boundary ' . $index, static function () use ($password): void {
    $db = new MemoryDB(); $id = $db->seed(); expectResponse(invoke($db, 'reset', resetData(['password' => $password])), true, 200);
    check(\password_verify($password, $db->users[7]['password']) && $db->otps[$id]['used'] === 1, 'Valid password boundary failed');
});
foreach (['integer', 'trimmed', 'leading-zero'] as $kind) scenario('accepted OTP representation ' . $kind, static function () use ($kind): void {
    $db = new MemoryDB(); $code = $kind === 'leading-zero' ? '000007' : '314159'; $db->seed($code); $before = $db->snapshot();
    $otp = $kind === 'integer' ? 314159 : ($kind === 'trimmed' ? ' 314159 ' : $code);
    expectResponse(invoke($db, 'reset', resetData(['email' => ' MEMBER@EXAMPLE.TEST ', 'otp' => $otp, '_step' => 'verify'])), true, 200); noMutation($db, $before);
});
scenario('integer cannot recover leading zero code', static function (): void {
    $db = new MemoryDB(); $db->seed('000007'); $before = $db->snapshot();
    expectResponse(invoke($db, 'reset', resetData(['otp' => 7, '_step' => 'verify'])), false, 400); noMutation($db, $before); check($db->dbCalls === 0, 'Short integer code reached database');
});

$contenders = [
    'consumed' => static function ($db, $u, $o): void { $db->otps[$o]['used'] = 1; },
    'expired' => static function ($db, $u, $o): void { $db->otps[$o]['expires_at'] = $db->now - 1; },
    'exact-expiry' => static function ($db, $u, $o): void { $db->otps[$o]['expires_at'] = $db->now; },
    'clock-advanced' => static function ($db, $u, $o): void { $db->now = $db->otps[$o]['expires_at']; },
    'deleted' => static function ($db, $u, $o): void { unset($db->otps[$o]); },
    'resent-row' => static function ($db, $u, $o): void { $code = $db->otps[$o]['code']; unset($db->otps[$o]); $db->seed($code); },
    'account-unverified' => static function ($db, $u, $o): void { $db->users[$u]['email_verified'] = 0; },
    'account-deleted' => static function ($db, $u, $o): void { unset($db->users[$u]); },
    'join-changed' => static function ($db, $u, $o): void { $db->otps[$o]['email'] = 'other@example.test'; },
];
foreach ($contenders as $kind => $contender) scenario('guard rechecks contender ' . $kind, static function () use ($contender): void {
    $db = new MemoryDB(); $db->seed(); $db->beforeRedeem = $contender;
    expectResponse(invoke($db, 'reset', resetData()), false, 400, 'invalid_otp');
    check($db->afterContender !== null && $db->snapshot() === $db->afterContender, 'Invalidated OTP still changed account/token');
    check($db->trace === ['lookup', 'redeem'] && \count($db->hashCalls) === 1 && $db->logs === [], 'Contender should cause zero affected rows, not separate consumption or SQL failure');
});
foreach (['used', 'expired', 'wrong-code', 'unverified'] as $kind) scenario('final rechecks after successful verify ' . $kind, static function () use ($kind): void {
    $db = new MemoryDB(); $id = $db->seed(); expectResponse(invoke($db, 'reset', resetData(['_step' => 'verify'])), true, 200);
    $data = resetData(); $reason = 'invalid_otp';
    if ($kind === 'used') $db->otps[$id]['used'] = 1;
    if ($kind === 'expired') { $db->otps[$id]['expires_at'] = $db->now; $reason = 'expired_otp'; }
    if ($kind === 'wrong-code') $data['otp'] = '000000';
    if ($kind === 'unverified') $db->users[7]['email_verified'] = 0;
    $before = $db->snapshot(); expectResponse(invoke($db, 'reset', $data), false, 400, $reason); noMutation($db, $before);
});
scenario('lookup ordering chooses newest matching eligible OTP', static function (): void {
    $db = new MemoryDB(); $older = $db->seed(); $newer = $db->seed(); $used = $db->seed('314159', 300, 1);
    $other = $db->seed('314159', 300, 0, 'other@example.test'); $wrong = $db->seed('271828');
    $db->otps[$older]['created_at'] -= 10;
    expectResponse(invoke($db, 'reset', resetData()), true, 200);
    check($db->otps[$newer]['used'] === 1 && $db->otps[$older]['used'] === 0 && $db->otps[$other]['used'] === 0 && $db->otps[$wrong]['used'] === 0 && $db->otps[$used]['used'] === 1, 'Wrong token selected/consumed');
});
scenario('expired newest token does not fall back to older token', static function (): void {
    $db = new MemoryDB(); $db->seed(); $db->seed('314159', 0); $before = $db->snapshot();
    expectResponse(invoke($db, 'reset', resetData(['_step' => 'verify'])), false, 400, 'expired_otp'); noMutation($db, $before);
});
scenario('resend deletes all old email rows and rejects old code', static function (): void {
    $db = new MemoryDB(); $foreign = $db->seed('271828', 300, 0, 'other@example.test');
    expectResponse(invoke($db, 'forgot', ['email' => 'member@example.test']), true, 200);
    $oldCode = $db->mail[0][1]; $oldId = $db->eligibleRows('member@example.test', $oldCode)[0]['id'];
    $db->seed('123123', -1, 1);
    expectResponse(invoke($db, 'reset', resetData(['otp' => $oldCode, '_step' => 'verify'])), true, 200);
    // A real six-digit generator may repeat. Do not substitute a fake generator
    // or assert statistical uniqueness; retry only actual production resend calls.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        expectResponse(invoke($db, 'forgot', ['email' => 'member@example.test']), true, 200);
        $newCode = $db->mail[0][1];
        check(!isset($db->otps[$oldId]) && \count($db->otps) === 2 && isset($db->otps[$foreign]), 'Resend did not invalidate old rows or touched foreign row');
        if ($newCode !== $oldCode) break;
    }
    check($newCode !== $oldCode, 'Repeated real random collision prevents old-code assertion');
    $before = $db->snapshot();
    foreach (['verify', 'reset'] as $step) {
        expectResponse(invoke($db, 'reset', resetData(['otp' => $oldCode, '_step' => $step])), false, 400, 'invalid_otp'); noMutation($db, $before);
    }
    expectResponse(invoke($db, 'reset', resetData(['otp' => $newCode, '_step' => 'verify'])), true, 200); noMutation($db, $before);
    expectResponse(invoke($db, 'reset', resetData(['otp' => $newCode])), true, 200);
});

foreach (['account', 'delete', 'insert', 'expiry', 'lookup', 'redeem'] as $operation) {
    $modes = ['prepare', 'execute', 'throw'];
    if (\in_array($operation, ['account', 'expiry', 'lookup'], true)) $modes[] = 'result';
    if (\in_array($operation, ['insert', 'redeem'], true)) $modes[] = 'error';
    if ($operation === 'expiry') $modes[] = 'missing';
    foreach ($modes as $mode) scenario('SQL failure ' . $operation . '/' . $mode, static function () use ($operation, $mode): void {
        $db = new MemoryDB(); $db->seed(); $before = $db->snapshot();
        if ($operation === 'redeem') expectResponse(invoke($db, 'reset', resetData(['_step' => 'verify'])), true, 200);
        $db->failures[$operation] = $mode;
        $endpoint = \in_array($operation, ['lookup', 'redeem'], true) ? 'reset' : 'forgot';
        expectResponse(invoke($db, $endpoint, $endpoint === 'reset' ? resetData() : ['email' => 'member@example.test']), false, 500);
        check($db->mail === [] && \count($db->logs) === 1, 'SQL failure sent mail or omitted generic log');
        check($db->users === $before[0], 'SQL failure changed password');
        if ($endpoint === 'reset') {
            check($db->otps === $before[1], 'Reset failure prematurely consumed OTP');
            check($db->trace === ($operation === 'redeem' ? ['lookup', 'redeem'] : ['lookup']), 'Failure used extra SQL/transaction');
        }
        // Request cleanup/insert are deliberately not modeled as transactional:
        // later request failures may already delete old codes or persist a new one.
    });
}
foreach (['forgot', 'reset'] as $endpoint) scenario('connection failure ' . $endpoint, static function () use ($endpoint): void {
    $db = new MemoryDB(); $db->seed(); $before = $db->snapshot(); $db->failures['connection'] = 'throw';
    expectResponse(invoke($db, $endpoint, $endpoint === 'reset' ? resetData() : ['email' => 'member@example.test']), false, 500);
    noMutation($db, $before); check(\count($db->logs) === 1 && $db->mail === [], 'Connection failure response/log incorrect');
});
foreach (['random', 'hash'] as $operation) scenario('dependency failure ' . $operation, static function () use ($operation): void {
    $db = new MemoryDB(); $db->seed(); $before = $db->snapshot(); $db->failures[$operation] = 'throw';
    $endpoint = $operation === 'random' ? 'forgot' : 'reset';
    expectResponse(invoke($db, $endpoint, $endpoint === 'reset' ? resetData() : ['email' => 'member@example.test']), false, 500);
    noMutation($db, $before); check(\count($db->logs) === 1, 'Dependency failure omitted generic log');
});
foreach (['false', 'throw'] as $mode) scenario('mail failure ' . $mode, static function () use ($mode): void {
    $db = new MemoryDB(); $before = $db->users;
    $db->sendOk = $mode !== 'false'; $db->sendThrows = $mode === 'throw';
    expectResponse(invoke($db, 'forgot', ['email' => 'member@example.test']), false, $mode === 'false' ? 503 : 500);
    check(\count($db->mail) === 1 && \count($db->logs) === 1 && $db->users === $before && \count($db->otps) === 1, 'Mail failure handling incorrect');
});

\restore_error_handler();
\fwrite(STDOUT, Sandbox::$passed . ' passed, ' . Sandbox::$failed . ' failed' . PHP_EOL);
\fwrite(STDOUT, 'Offline source/SQL fixture only; not proof of MySQL/MyISAM atomicity, crash safety, delivery, or random uniqueness.' . PHP_EOL);
exit(Sandbox::$failed === 0 ? 0 : 1);
