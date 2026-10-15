<?php
/**
 * CLI-only, read-only manager authorization regression.
 * Run: C:/wamp64/bin/php/php8.3.14/php.exe test-manager-operations.php
 *
 * Only tokenized real helper definitions/constants and allowlisted authorization
 * preludes are evaluated. Application bootstrap files are NEVER included.
 * Fresh -n subprocesses use namespaced session/header/DB stubs; no session files,
 * database connections, endpoint mutations, network calls or temporary files.
 * Dashboard rendering uses an in-memory template copy and settings-only fake DB.
 * CSS/JavaScript checks are structural, not browser or end-to-end CRUD tests.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function tmTokens(string $source): array
{
    return array_values(array_filter(token_get_all($source), static function ($token): bool {
        return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true);
    }));
}

function tmText($token): string
{
    return is_array($token) ? $token[1] : $token;
}

function tmJoin(array $tokens): string
{
    return implode('', array_map('tmText', $tokens));
}

/** Extract declarations only, never session.php/access-control.php top-level code. */
function tmDefinitions(string $source, array $names, bool $constants = false): string
{
    $tokens = token_get_all($source);
    $found = [];
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i])) {
            continue;
        }
        $id = $tokens[$i][0];
        if ((!$constants && $id !== T_FUNCTION) || ($constants && ($id !== T_STRING || strtolower($tokens[$i][1]) !== 'define'))) {
            continue;
        }
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }
        if ($constants) {
            if (($tokens[$j] ?? null) !== '(') {
                continue;
            }
            $j++;
            while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $name = trim($tokens[$j][1], "\"'");
        } else {
            if (!is_array($tokens[$j] ?? null) || $tokens[$j][0] !== T_STRING) {
                continue;
            }
            $name = $tokens[$j][1];
        }
        if (!in_array($name, $names, true)) {
            continue;
        }
        $start = $i;
        if ($constants) {
            $depth = 0;
            for (; $i < $count; $i++) {
                if ($tokens[$i] === '(') {
                    $depth++;
                } elseif ($tokens[$i] === ')') {
                    $depth--;
                } elseif ($tokens[$i] === ';' && $depth === 0) {
                    break;
                }
            }
        } else {
            while ($i < $count && $tokens[$i] !== '{') {
                $i++;
            }
            $depth = 0;
            for (; $i < $count; $i++) {
                $token = $tokens[$i];
                if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($token === '}' && --$depth === 0) {
                    break;
                }
            }
        }
        if ($i >= $count || isset($found[$name])) {
            throw new RuntimeException("Missing terminator or duplicate declaration: $name");
        }
        $found[$name] = tmJoin(array_slice($tokens, $start, $i - $start + 1));
    }
    foreach ($names as $name) {
        if (!isset($found[$name])) {
            throw new RuntimeException("Actual declaration missing: $name");
        }
    }
    return implode("\n", $found) . "\n";
}

/** Read one complete initial PHP statement, preserving its real tokens. */
function tmStatement(array $tokens, int &$index): array
{
    $start = $index;
    $braces = $parens = $brackets = 0;
    for ($count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_CLOSE_TAG, T_INLINE_HTML], true)) {
            break;
        }
        if ($token === '(') $parens++;
        if ($token === ')') $parens--;
        if ($token === '[') $brackets++;
        if ($token === ']') $brackets--;
        if ($token === '{') $braces++;
        if ($token === '}') $braces--;
        if (($token === ';' || $token === '}') && $braces === 0 && $parens === 0 && $brackets === 0) {
            $index++;
            return array_slice($tokens, $start, $index - $start);
        }
    }
    throw new RuntimeException('Authorization must precede HTML or any unsupported statement');
}

function tmBootstrap(array $statement): bool
{
    if (!is_array($statement[0] ?? null) || !in_array($statement[0][0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
        return false;
    }
    $rest = tmJoin(array_slice($statement, 1));
    if (!preg_match('~^(?:__DIR__\.)?([\'"])([^\'"]+)\1;$~', $rest, $match)
        || !in_array(basename(str_replace('\\', '/', $match[2])), ['session.php', 'config.php', 'email.php', 'package-dates.php'], true)) {
        throw new RuntimeException('Refusing unknown bootstrap include: ' . $rest);
    }
    return true;
}

/**
 * Stop immediately after real authorization, before request parsing/validation/SQL.
 * Fail closed if a DB call or any unsupported code appears before the guard.
 */
function tmPrelude(string $source, string $guard): string
{
    $tokens = tmTokens($source);
    $index = 0;
    $prefix = '';
    $loginSeen = false;
    while ($index < count($tokens)) {
        $statement = tmStatement($tokens, $index);
        $text = tmJoin($statement);
        if (tmBootstrap($statement)) {
            continue; // Strip bootstrap only in this trusted, in-memory copy.
        }
        if (in_array($text, ['ob_start();', 'ob_end_clean();', 'ob_clean();'], true)) {
            $prefix .= $text . "\n";
            continue;
        }
        if ($guard !== 'package-policy' && $text === $guard . '();') {
            return $prefix . $text;
        }
        if ($guard === 'package-policy' && $text === 'requireLogin();') {
            $loginSeen = true;
            $prefix .= $text . "\n";
            continue;
        }
        if ($guard === 'package-policy' && $loginSeen && str_starts_with($text, 'if(')) {
            // Execute the actual inline policy, not a test-written replacement.
            foreach ($statement as $token) {
                if (is_array($token) && $token[0] === T_STRING && !in_array($token[1], ['isAdmin', 'isManager', 'sendResponse', 'false', 'null'], true)) {
                    throw new RuntimeException('Unsafe call in package authorization: ' . $token[1]);
                }
                if (is_array($token) && in_array($token[0], [T_VARIABLE, T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE, T_NEW, T_EVAL], true)) {
                    throw new RuntimeException('Unsupported package authorization token');
                }
            }
            if (str_contains($text, 'isAdmin()') && str_contains($text, 'isManager()') && str_contains($text, 'sendResponse(')) {
                return $prefix . $text;
            }
        }
        throw new RuntimeException("Expected $guard before DB/request/body code; encountered: " . substr($text, 0, 140));
    }
    throw new RuntimeException("Missing actual guard: $guard");
}

/** Strip only known bootstrap statements; never include live PHP during rendering. */
function tmTemplate(string $source): string
{
    $tokens = token_get_all($source);
    $output = '';
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
            $statement = [];
            do {
                $statement[] = $tokens[$i];
            } while ($tokens[$i++] !== ';' && $i < $count);
            $i--;
            tmBootstrap(tmTokens('<?php ' . tmJoin($statement)));
            continue;
        }
        $output .= tmText($token);
    }
    return $output;
}

/** Convert the small shared wrapper's one require to an in-memory renderer. */
function tmWrapper(string $source): string
{
    if (strlen($source) > 1200 || count(preg_split('/\R/', trim($source))) > 15) {
        throw new RuntimeException('Manager dashboard must be a small shared-template wrapper (<=15 lines, <=1200 bytes)');
    }
    $tokens = tmTokens($source);
    $index = 0;
    $output = '';
    $shared = 0;
    while ($index < count($tokens)) {
        if (is_array($tokens[$index]) && $tokens[$index][0] === T_CLOSE_TAG) {
            $index++;
            continue;
        }
        $statement = tmStatement($tokens, $index);
        $text = tmJoin($statement);
        if (preg_match('~^require(?:_once)?__DIR__\.([\'"])/dashboard\.php\1;$~', $text)) {
            $output .= 'renderSharedDashboard();';
            $shared++;
        } elseif (tmBootstrap($statement)) {
            continue;
        } elseif ($text === 'requireManagerAccess();') {
            $output .= $text;
        } else {
            throw new RuntimeException('Wrapper must delegate, not duplicate/redirect the dashboard: ' . substr($text, 0, 120));
        }
    }
    if ($shared !== 1) {
        throw new RuntimeException('Manager wrapper must require __DIR__ . \'/dashboard.php\' exactly once');
    }
    return $output;
}

/** Child executes controlled fixtures; shutdown captures the real guards' exit(). */
function tmChild(): never
{
    $payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $GLOBALS['tmState'] = ['status' => 200, 'headers' => [], 'passed' => false, 'db_calls' => 0, 'queries' => [], 'error' => null, 'result' => null];
    $GLOBALS['tmPayload'] = $payload;
    $_SESSION = $payload['role'] === 'anonymous' ? [] : [
        'user_id' => 9001,
        'user_email' => 'fixture@example.invalid',
        'user_name' => 'Fixture ' . ucfirst($payload['role']),
        'user_role' => $payload['role'],
    ];
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/FIT_GYM_V2/' . ($payload['path'] ?? 'api/fixture.php');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    register_shutdown_function(static function (): void {
        $state = $GLOBALS['tmState'];
        $state['body'] = ob_get_contents();
        ob_end_clean();
        $fatal = error_get_last();
        if ($fatal && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $state['error'] = $fatal['message'];
        }
        fwrite(STDOUT, json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    });
    set_error_handler(static function (int $level, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $level, $file, $line);
    });
    try {
        eval(<<<'PHP'
namespace ManagerOperationsFixture;
// These stubs shadow unqualified built-ins in the extracted real functions.
function session_status() { return PHP_SESSION_ACTIVE; }
function session_start() { throw new \RuntimeException('Real session start forbidden'); }
function session_write_close() { throw new \RuntimeException('Session writes forbidden'); }
function session_regenerate_id($delete = false) { throw new \RuntimeException('Session mutation forbidden'); }
function header($value, $replace = true, $code = 0) {
    $GLOBALS['tmState']['headers'][] = $value;
    if ($code) $GLOBALS['tmState']['status'] = $code;
    elseif (stripos($value, 'Location:') === 0) $GLOBALS['tmState']['status'] = 302;
}
function headers_sent() { return false; }
function http_response_code($code = null) {
    $previous = $GLOBALS['tmState']['status'];
    if ($code !== null) $GLOBALS['tmState']['status'] = $code;
    return $previous;
}
function error_log($message) { return true; }
function ob_start() { return true; }
function ob_end_clean() { return true; }
function ob_clean() { return true; }
function ob_get_length() { return 0; }
function sendResponse($success, $message, $data = null, $status = 200) {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}
function getDBConnection() {
    $GLOBALS['tmState']['db_calls']++;
    if ($GLOBALS['tmPayload']['mode'] !== 'render') {
        // Simulated DB boundary only: absolutely no endpoint body is evaluated.
        exit;
    }
    return new SettingsDatabase();
}
class SettingsDatabase {
    public function query($sql) {
        $GLOBALS['tmState']['queries'][] = $sql;
        if ($sql !== 'SELECT setting_key, setting_value FROM gym_settings') {
            throw new \RuntimeException('Only the fake settings SELECT is allowed: ' . $sql);
        }
        return new SettingsResult();
    }
}
class SettingsResult {
    private $rows = [['setting_key' => 'gym_name', 'setting_value' => 'Fixture Gym <memory>']];
    public function fetch_assoc() { return array_shift($this->rows); }
}
function renderSharedDashboard() {
    eval('namespace ManagerOperationsFixture; ?>' . $GLOBALS['tmPayload']['template']);
}
PHP
        );
        eval('namespace ManagerOperationsFixture; ' . $payload['definitions']);
        if ($payload['mode'] === 'permissions') {
            $result = ['levels' => constant('ROLE_LEVELS'), 'permissions' => constant('ROLE_PERMISSIONS'), 'checks' => [], 'hierarchy' => []];
            foreach ($payload['permissions'] as $permission) {
                $result['checks'][$permission] = [
                    \ManagerOperationsFixture\hasPermission($permission),
                    \ManagerOperationsFixture\hasPermission($permission, $payload['role'] === 'anonymous' ? null : $payload['role']),
                ];
            }
            foreach (['admin', 'manager', 'trainer', 'user'] as $level) {
                $result['hierarchy'][$level] = \ManagerOperationsFixture\hasRoleLevel($level);
            }
            $GLOBALS['tmState']['result'] = $result;
        } elseif ($payload['mode'] === 'render') {
            if (isset($payload['wrapper'])) {
                eval('namespace ManagerOperationsFixture; ' . $payload['wrapper']);
            } else {
                \ManagerOperationsFixture\renderSharedDashboard();
            }
        } else {
            eval('namespace ManagerOperationsFixture; ' . $payload['prelude']);
            $GLOBALS['tmState']['passed'] = true;
            if ($payload['mode'] === 'prelude') {
                \ManagerOperationsFixture\getDBConnection();
            }
        }
        $GLOBALS['tmState']['passed'] = true;
    } catch (Throwable $error) {
        $GLOBALS['tmState']['error'] = get_class($error) . ': ' . $error->getMessage();
    }
    exit;
}

if (($argv[1] ?? '') === '--fixture') {
    tmChild();
}

function tmRun(array $payload): array
{
    $process = proc_open([PHP_BINARY, '-n', __FILE__, '--fixture'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot launch isolated PHP fixture');
    }
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    $offset = 0;
    while ($offset < strlen($json)) {
        $written = fwrite($pipes[0], substr($json, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Cannot write fixture input');
        }
        $offset += $written;
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || trim($stderr) !== '') {
        throw new RuntimeException("Fixture subprocess failed ($exit): " . trim($stderr) . ' ' . substr($stdout, 0, 200));
    }
    return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
}

$checks = 0;
$failures = [];
function tmCheck(bool $condition, string $label, string $detail = ''): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) {
        $failures[] = $label . ($detail === '' ? '' : ': ' . $detail);
        fwrite(STDERR, 'FAIL ' . end($failures) . PHP_EOL);
    }
}
function tmEqual($actual, $expected, string $label): void
{
    tmCheck($actual === $expected, $label, 'expected ' . json_encode($expected) . ', got ' . json_encode($actual));
}
function tmCase(string $label, callable $test): void
{
    try {
        $test();
    } catch (Throwable $error) {
        tmCheck(false, $label, $error->getMessage());
    }
}

/** Check manager hiding rules in either real CSS or a JS-injected stylesheet. */
function tmStyles(string $source, string $label): void
{
    $source = preg_replace('~/\*[\s\S]*?\*/~', '', $source);
    preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $source, $rules, PREG_SET_ORDER);
    $bad = [];
    foreach ($rules as $rule) {
        if (!preg_match('/display\s*:\s*none\b|visibility\s*:\s*hidden\b|pointer-events\s*:\s*none\b|opacity\s*:\s*0(?:\D|$)/i', $rule[2])) {
            continue;
        }
        foreach (explode(',', $rule[1]) as $selector) {
            if (!str_contains($selector, '.role-manager')) {
                continue;
            }
            $target = str_replace('.role-manager', '', trim($selector));
            if (!preg_match('~Trainer|Admin|Manager|Database|settings-danger-zone|href\s*=\s*[\'"](?:trainers|settings)\.php|data-tab\s*=\s*[\'"]trainers~', $target)) {
                $bad[] = trim($selector);
            }
        }
    }
    tmEqual($bad, [], "$label no generic/operational manager edit/delete hiding");
}

$root = str_replace('\\', '/', __DIR__);
$sources = [];
function tmSource(string $relative): string
{
    global $root, $sources;
    if (!isset($sources[$relative])) {
        $source = file_get_contents($root . '/' . $relative);
        if ($source === false) {
            throw new RuntimeException('Cannot read ' . $root . '/' . $relative);
        }
        $sources[$relative] = $source;
    }
    return $sources[$relative];
}

try {
    $access = tmSource('api/access-control.php');
    $definitions = tmDefinitions($access, ['ROLE_LEVELS', 'ROLE_PERMISSIONS'], true)
        . tmDefinitions($access, ['hasPermission', 'hasRoleLevel'])
        . tmDefinitions(tmSource('api/session.php'), ['isLoggedIn', 'isAdmin', 'isManager', 'isTrainer', 'isApiRequest', 'getCurrentUser', 'requireLogin', 'requireAdminOnly', 'requireManagerAccess']);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL real authorization extraction: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

$roles = ['admin', 'manager', 'trainer', 'user', 'anonymous'];
$own = ['view_own_profile', 'view_own_bookings', 'view_own_payments'];
$userPermissions = array_merge($own, ['manage_own_progress', 'view_trainer_info']);
$expectedPermissions = [
    'user' => $userPermissions,
    'trainer' => array_merge($userPermissions, ['manage_assigned_clients', 'view_client_progress', 'create_training_plans', 'manage_client_sessions']),
    'manager' => array_merge($own, ['manage_members', 'manage_bookings', 'manage_payments', 'manage_inventory', 'manage_packages', 'manage_exercises', 'view_reports', 'export_data']),
    'admin' => ['all_permissions', 'manage_admins', 'system_config', 'delete_records', 'view_audit_logs', 'manage_packages', 'export_data'],
];
$levels = ['user' => 1, 'trainer' => 2, 'manager' => 3, 'admin' => 4];
$permissionLists = array_values($expectedPermissions);
$permissionLists[] = ['manage_trainers', 'unknown_permission'];
$permissions = array_values(array_unique(array_merge(...$permissionLists)));

foreach ($roles as $role) {
    tmCase("$role actual permission matrix", static function () use ($definitions, $role, $permissions, $expectedPermissions, $levels): void {
        $out = tmRun(['mode' => 'permissions', 'role' => $role, 'definitions' => $definitions, 'permissions' => $permissions]);
        tmEqual($out['error'], null, "$role permissions fixture clean");
        tmEqual($out['db_calls'], 0, "$role permissions do not touch DB");
        if ($role === 'admin') {
            tmEqual($out['result']['levels'], $levels, 'actual ROLE_LEVELS');
            tmEqual($out['result']['permissions'], $expectedPermissions, 'actual ROLE_PERMISSIONS (Walk-in uses manage_bookings; no invented permission)');
        }
        foreach ($permissions as $permission) {
            $allowed = $role === 'admin' || in_array($permission, $expectedPermissions[$role] ?? [], true);
            tmEqual($out['result']['checks'][$permission], [$allowed, $allowed], "$role hasPermission($permission), session and explicit role");
        }
        foreach ($levels as $level => $value) {
            tmEqual($out['result']['hierarchy'][$level], ($levels[$role] ?? 0) >= $value, "$role real hasRoleLevel($level)");
        }
    });
}

/** Assert actual denial status/body/redirect and whether the fake DB boundary ran. */
function tmOutcome(array $out, string $label, string $role, bool $allowed, bool $api, int $allowedDbCalls, string $guard): void
{
    tmEqual($out['error'], null, "$label fixture clean");
    tmEqual($out['passed'], $allowed, "$label guard outcome");
    tmEqual($out['db_calls'], $allowed ? $allowedDbCalls : 0, "$label authorization before fake DB boundary");
    $status = $allowed ? 200 : ($api ? ($role === 'anonymous' ? 401 : 403) : 302);
    tmEqual($out['status'], $status, "$label status");
    if (!$allowed && $api) {
        $body = json_decode($out['body'], true, 512, JSON_THROW_ON_ERROR);
        tmEqual($body['success'] ?? null, false, "$label JSON denial");
        if ($guard !== 'package-policy' || $role === 'anonymous') {
            tmEqual($body['error_code'] ?? null, $role === 'anonymous' ? 'UNAUTHORIZED' : 'FORBIDDEN', "$label denial code");
        }
    } elseif (!$allowed) {
        $target = $role === 'anonymous' ? '../../index.php'
            : ($role === 'manager' ? 'manager-dashboard.php' : '../' . ($role === 'trainer' ? 'trainer' : 'user') . '/dashboard.php');
        tmCheck(in_array('Location: ' . $target, $out['headers'], true), "$label redirect", json_encode($out['headers']));
        tmEqual($out['body'], '', "$label no unauthorized HTML");
    }
}

foreach (['requireManagerAccess', 'requireAdminOnly'] as $guard) {
    foreach ([true, false] as $api) {
        foreach ($roles as $role) {
            $path = $api ? 'api/fixture.php' : 'views/admin/fixture.php';
            $allowed = $role === 'admin' || ($guard === 'requireManagerAccess' && $role === 'manager');
            $label = "$guard $role " . ($api ? 'API' : 'page');
            tmCase($label, static function () use ($definitions, $role, $path, $guard, $allowed, $api, $label): void {
                $out = tmRun(['mode' => 'guard', 'role' => $role, 'path' => $path, 'definitions' => $definitions, 'prelude' => $guard . '();']);
                tmOutcome($out, $label, $role, $allowed, $api, 0, $guard);
            });
        }
    }
}

$operationPages = ['dashboard.php', 'walkin-bookings.php', 'bookings.php', 'payments.php', 'members.php', 'packages.php', 'equipment.php', 'exercises.php', 'report.php'];
$paths = [];
foreach ($operationPages as $page) $paths['views/admin/' . $page] = 'requireManagerAccess';
foreach (['trainers.php', 'settings.php'] as $page) $paths['views/admin/' . $page] = 'requireAdminOnly';
foreach (['get-all', 'create', 'update', 'delete', 'migrate'] as $api) $paths['api/trainers/' . $api . '.php'] = 'requireAdminOnly';
foreach (['api/walkin/create.php', 'api/walkin/get-all.php', 'api/trainers/get-for-assignment.php'] as $path) $paths[$path] = 'requireManagerAccess';
foreach (['create', 'update', 'delete'] as $api) $paths['api/packages/' . $api . '.php'] = 'package-policy';

foreach ($paths as $path => $guard) {
    tmCase("$path guard placement", static function () use ($path, $guard, $roles, $definitions): void {
        $prelude = tmPrelude(tmSource($path), $guard);
        tmCheck(true, "$path has real $guard before body/DB");
        $api = str_starts_with($path, 'api/');
        foreach ($roles as $role) {
            $allowed = $role === 'admin' || ($guard !== 'requireAdminOnly' && $role === 'manager');
            $label = "$path $role";
            tmCase($label, static function () use ($definitions, $path, $prelude, $role, $allowed, $api, $guard, $label): void {
                $out = tmRun(['mode' => 'prelude', 'role' => $role, 'path' => $path, 'definitions' => $definitions, 'prelude' => $prelude]);
                tmOutcome($out, $label, $role, $allowed, $api, 1, $guard);
            });
        }
    });
}

$template = null;
$wrapper = null;
tmCase('shared dashboard bootstrap isolation', static function () use (&$template): void {
    $template = tmTemplate(tmSource('views/admin/dashboard.php'));
    tmCheck(true, 'shared dashboard only known bootstrap requires stripped in memory');
});
tmCase('manager wrapper structural delegation', static function () use (&$wrapper): void {
    $wrapper = tmWrapper(tmSource('views/admin/manager-dashboard.php'));
    tmCheck(true, 'manager wrapper small and requires shared dashboard template');
});

foreach (['shared', 'wrapper'] as $entry) {
    foreach ($roles as $role) {
        tmCase("$entry dashboard render $role", static function () use ($entry, $role, $definitions, $template, $wrapper): void {
            if ($template === null || ($entry === 'wrapper' && $wrapper === null)) {
                throw new RuntimeException('Dashboard render unavailable because template/wrapper structural assertion failed');
            }
            $path = 'views/admin/' . ($entry === 'wrapper' ? 'manager-dashboard.php' : 'dashboard.php');
            $payload = ['mode' => 'render', 'role' => $role, 'path' => $path, 'definitions' => $definitions, 'template' => $template];
            if ($entry === 'wrapper') $payload['wrapper'] = $wrapper;
            $out = tmRun($payload);
            $label = "$entry dashboard $role";
            $allowed = in_array($role, ['admin', 'manager'], true);
            tmOutcome($out, $label, $role, $allowed, false, 1, 'requireManagerAccess');
            if (!$allowed) return;
            tmEqual($out['queries'], ['SELECT setting_key, setting_value FROM gym_settings'], "$label settings-only fake query");
            $html = $out['body'];
            tmCheck(str_contains($html, 'Fixture Gym &lt;memory&gt;'), "$label real template uses escaped fixture settings");
            tmCheck((bool)preg_match('~<body\b[^>]*class="role-' . $role . '"[^>]*data-can-manage-trainers="' . ($role === 'admin' ? 'true' : 'false') . '"~', $html), "$label body role and trainer-management flag accurate");
            preg_match('~<aside\b[^>]*class="sidebar"[^>]*>([\s\S]*?)</aside>~', $html, $sidebar);
            tmCheck(isset($sidebar[1]), "$label sidebar rendered");
            $nav = $sidebar[1] ?? '';
            $links = ['bookings.php' => 'Bookings', 'payments.php' => 'Payments', 'members.php' => 'Members', 'packages.php' => 'Packages', 'equipment.php' => 'Equipment', 'exercises.php' => 'Exercises', 'report.php' => 'Reports'];
            $links[$role === 'admin' ? 'dashboard.php' : 'manager-dashboard.php'] = 'Dashboard';
            foreach ($links as $href => $name) {
                tmCheck((bool)preg_match('~<a\b[^>]*href="' . preg_quote($href, '~') . '"[^>]*>[\s\S]*?<span>' . $name . '</span>[\s\S]*?</a>~', $nav), "$label operational nav $name");
            }
            tmCheck((bool)preg_match('~<a\b[^>]*href="(?:bookings\.php\?action=walkin|walkin-bookings\.php)"[^>]*>[\s\S]*?Walk-in[\s\S]*?</a>~i', $html), "$label Walk-in link available");
            foreach (['trainers.php' => 'Trainers', 'settings.php' => 'Settings'] as $href => $name) {
                tmEqual((bool)preg_match('~<a\b[^>]*href="' . preg_quote($href, '~') . '"~', $nav), $role === 'admin', "$label $name nav admin-only (not merely CSS-hidden)");
            }
            tmEqual((bool)preg_match('~<button\b[^>]*data-tab="trainers"~', $html), $role === 'admin', "$label Trainers tab admin-only");
            tmEqual((bool)preg_match('~<button\b[^>]*data-tab="settings"~', $html), false, "$label no Settings control tab (Settings is a dedicated admin nav page)");
            foreach (['overview', 'bookings', 'payments', 'members', 'inventory'] as $tab) {
                tmCheck((bool)preg_match('~<button\b[^>]*data-tab="' . $tab . '"~', $html), "$label operational tab $tab available");
            }
        });
    }
}

tmCase('CSS and role-restrictions operational actions', static function (): void {
    tmStyles(tmSource('assets/css/dashboard.css'), 'dashboard.css');
    $script = tmSource('assets/js/role-restrictions.js');
    tmStyles($script, 'role-restrictions.js injected CSS');
    preg_match('/const\s+BLOCKED_PATTERNS\s*=\s*\[([\s\S]*?)\];/', $script, $match);
    tmCheck(isset($match[1]), 'role-restrictions.js named blocklist exists');
    $bad = [];
    foreach (preg_split('/\R/', $match[1] ?? '') as $line) {
        $line = trim($line, " \t\r\n,");
        if ($line === '') continue;
        if (!preg_match('~^/\\\\b(?:editTrainer|deleteTrainer|confirmDeleteTrainer|openAddTrainerModal|deleteAdmin|removeAdmin|removeManager|exportDatabase)\\\\s\*\\\\\(/i$~', $line)) {
            $bad[] = $line;
        }
    }
    tmEqual($bad, [], 'role-restrictions.js no generic edit/delete/remove blocklist');
});
tmCase('assignment loader and dashboard trainer-data isolation', static function (): void {
    $packages = tmSource('assets/js/packages.js');
    tmCheck((bool)preg_match('~fetch\(\s*[\'"]\.\./\.\./api/trainers/get-for-assignment\.php[\'"]\s*\)~', $packages), 'packages.js loads safe trainer assignment API');
    tmCheck(!str_contains($packages, 'trainers/get-all.php'), 'packages.js does not request admin-only trainer management list');
    $dashboard = tmSource('assets/js/dashboard.js');
    tmCheck((bool)preg_match('~canManageTrainers\s*===\s*[\'"]true[\'"]~', $dashboard), 'dashboard.js reads server-rendered trainer flag');
    tmCheck((bool)preg_match('~showTrainerStats\s*\?\s*fetchStatsData\([\'"]trainers/get-all\.php[\'"]\)\s*:\s*Promise\.resolve~', $dashboard), 'dashboard.js trainer management request is conditional');
    tmCheck((bool)preg_match('~tab\s*===\s*[\'"]trainers[\'"]\s*&&\s*!canViewTrainerStats\(\)~', $dashboard), 'dashboard.js rejects manager trainer-tab activation');
});

fwrite(STDOUT, sprintf("%s: %d assertions, %d failures.\n", $failures ? 'FAIL' : 'PASS', $checks, count($failures)));
fwrite(STDOUT, "Coverage: real role/permission arrays and guards; 9 operational pages; Trainers/Settings denial; trainer/walk-in/package API authorization preludes; shared/wrapper dashboard rendering; structural CSS/JS isolation.\n");
fwrite(STDOUT, "Limits: namespaced session/HTTP fixtures, fake settings SELECT only, no live sessions/DB/endpoints/browser CRUD. HTTP codes/redirects capture real guard calls, not a web-server transport test.\n");
exit($failures ? 1 : 0);
