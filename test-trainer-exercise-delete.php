<?php
declare(strict_types=1);

final class ResponseHalt extends RuntimeException
{
    public function __construct(
        public readonly bool $success,
        string $message,
        public readonly mixed $data,
        public readonly ?int $status
    ) {
        parent::__construct($message);
    }
}

final class HarnessRuntime
{
    public static string $role = 'anonymous';
    public static ?MemoryConnection $connection = null;
    public static int $dbCalls = 0;

    public static function isTrainerOrHigher(): bool
    {
        return in_array(self::$role, ['trainer', 'manager', 'admin'], true);
    }
}

final class MemoryStatement
{
    public int $bindCalls = 0;
    public int $executeCalls = 0;
    public ?string $boundTypes = null;
    public array $boundValues = [];
    public array $valuesAtExecute = [];

    public function __construct(
        public readonly string $sql,
        private readonly bool $executeResult
    ) {
    }

    public function bind_param(string $types, mixed &...$values): bool
    {
        $this->bindCalls++;
        $this->boundTypes = $types;
        $this->boundValues = $values;
        return true;
    }

    public function execute(): bool
    {
        $this->executeCalls++;
        $this->valuesAtExecute = $this->boundValues;
        return $this->executeResult;
    }

    public function close(): void
    {
    }
}

final class MemoryConnection
{
    public int $prepareCalls = 0;
    public array $preparedSql = [];
    public ?MemoryStatement $statement = null;
    public string $error = 'simulated execute error';

    public function __construct(private readonly bool $executeResult)
    {
    }

    public function prepare(string $sql): MemoryStatement
    {
        $this->prepareCalls++;
        $this->preparedSql[] = $sql;
        $this->statement = new MemoryStatement($sql, $this->executeResult);
        return $this->statement;
    }

    public function close(): void
    {
    }
}

final class TestReport
{
    private int $passed = 0;
    private array $failures = [];
    private int $scenarios = 0;

    public function scenario(): void
    {
        $this->scenarios++;
    }

    public function check(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            return;
        }

        $this->failures[] = $message;
    }

    public function finish(): never
    {
        $failed = count($this->failures);
        $total = $this->passed + $failed;
        printf("RESULT: %d passed, %d failed, %d checks across %d behavior scenarios\n", $this->passed, $failed, $total, $this->scenarios);
        foreach ($this->failures as $failure) {
            echo "FAIL: {$failure}\n";
        }
        echo "LIMITATIONS: offline source execution with in-memory doubles only; no real DB delete, app bootstrap, session, filesystem mutation, or HTTP request was tested.\n";
        exit($failed === 0 ? 0 : 1);
    }
}

function readSource(string $path, TestReport $report): string
{
    $source = file_get_contents($path);
    $report->check($source !== false, "source is readable: {$path}");
    return $source === false ? '' : $source;
}

function executableText(string $source): string
{
    $result = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_OPEN_TAG, T_CLOSE_TAG], true)) {
                continue;
            }
            $result .= $token[1];
        } else {
            $result .= $token;
        }
    }
    return $result;
}

function stripExactBootstraps(string $source, TestReport $report): string
{
    foreach (["require_once '../config.php';", "require_once '../session.php';"] as $include) {
        $count = substr_count($source, $include);
        $report->check($count === 1, "delete source contains exactly one exact bootstrap: {$include}");
        if ($count === 1) {
            $source = str_replace($include, '', $source);
        }
    }
    return $source;
}

function runDeleteScenario(
    string $source,
    string $role,
    string $method,
    array $post,
    bool $executeResult = true
): array {
    static $sequence = 0;
    $sequence++;
    $namespace = 'DeleteRegressionCase' . $sequence;

    HarnessRuntime::$role = $role;
    HarnessRuntime::$dbCalls = 0;
    HarnessRuntime::$connection = new MemoryConnection($executeResult);
    $_SERVER = ['REQUEST_METHOD' => $method];
    $_POST = $post;
    $_FILES = [];

    $stubs = <<<PHP
namespace {$namespace};
function isTrainer(): bool { return \\HarnessRuntime::isTrainerOrHigher(); }
function sendResponse(bool \$success, string \$message, mixed \$data = null, ?int \$status = null): never
{
    throw new \\ResponseHalt(\$success, \$message, \$data, \$status);
}
function getDBConnection(): \\MemoryConnection
{
    \\HarnessRuntime::\$dbCalls++;
    return \\HarnessRuntime::\$connection;
}
?>
PHP;

    $response = null;
    $unexpected = null;
    ob_start();
    try {
        eval($stubs . $source);
        $unexpected = new RuntimeException('endpoint returned without sendResponse');
    } catch (ResponseHalt $halt) {
        $response = $halt;
    } catch (Throwable $throwable) {
        $unexpected = $throwable;
    } finally {
        ob_end_clean();
    }

    return [
        'response' => $response,
        'unexpected' => $unexpected,
        'dbCalls' => HarnessRuntime::$dbCalls,
        'connection' => HarnessRuntime::$connection,
    ];
}

function checkNoMutation(array $result, TestReport $report, string $label): void
{
    $connection = $result['connection'];
    $report->check($result['dbCalls'] === 0, "{$label}: denied before DB connection");
    $report->check($connection->prepareCalls === 0, "{$label}: no SQL prepared");
    $report->check($connection->statement === null, "{$label}: no mutation attempted");
}

function checkErrorResponse(array $result, TestReport $report, string $label, int $status): void
{
    $response = $result['response'];
    $report->check($result['unexpected'] === null, "{$label}: source executes without unexpected throwable");
    $report->check($response instanceof ResponseHalt, "{$label}: response emitted");
    if ($response instanceof ResponseHalt) {
        $report->check($response->success === false, "{$label}: response is failure");
        $report->check($response->status === $status, "{$label}: response status is {$status}");
    }
}

$report = new TestReport();
$root = __DIR__;
$deleteOriginal = readSource($root . '/api/exercises/delete.php', $report);
$createOriginal = readSource($root . '/api/exercises/create.php', $report);
$updateOriginal = readSource($root . '/api/exercises/update.php', $report);
$sessionOriginal = readSource($root . '/api/session.php', $report);
$deleteExecutable = executableText($deleteOriginal);
$createExecutable = executableText($createOriginal);
$updateExecutable = executableText($updateOriginal);
$sessionExecutable = executableText($sessionOriginal);

// Fail closed if the endpoint authorization, ordering, or mutation statement drifts.
$deleteGuard = 'if(!isTrainer()){' ;
$exerciseGuard = 'if(!isAdmin()&&!isManager()&&!isTrainer()){' ;
$report->check(substr_count($deleteExecutable, $deleteGuard) === 1, 'delete has exactly the trainer-or-higher authorization guard');
$report->check(substr_count($createExecutable, $exerciseGuard) === 1, 'create has exactly the trainer/manager/admin exercise guard');
$report->check(substr_count($updateExecutable, $exerciseGuard) === 1, 'update has exactly the trainer/manager/admin exercise guard');
$report->check(
    strpos($deleteExecutable, $deleteGuard) < strpos($deleteExecutable, "if(\$_SERVER['REQUEST_METHOD']!=='POST'){")
    && strpos($deleteExecutable, "if(\$_SERVER['REQUEST_METHOD']!=='POST'){") < strpos($deleteExecutable, '$conn=getDBConnection();'),
    'delete checks authorization and method before DB access'
);
$report->check(substr_count($deleteExecutable, '"DELETE FROM exercises WHERE id = ?"') === 1, 'delete source contains exactly the expected SQL literal');
$report->check(substr_count(strtoupper($deleteExecutable), 'DELETE FROM') === 1, 'delete source contains no alternate DELETE statement');

// The shared hierarchy must remain narrow so this endpoint change cannot grant trainer manager/admin powers globally.
$report->check(str_contains($sessionExecutable, "functionisAdmin(){returnhasRoleLevel('admin');}"), 'isAdmin remains admin-level');
$report->check(str_contains($sessionExecutable, "functionisManager(){returnhasRoleLevel('manager');}"), 'isManager remains manager-level');
$report->check(str_contains($sessionExecutable, "functionisTrainer(){returnhasRoleLevel('trainer');}"), 'isTrainer remains trainer-level');
$report->check(str_contains($sessionExecutable, 'functionrequireAdminOnly(){requireLogin();if(!isAdmin()){'), 'admin-only guard still excludes trainers');
$report->check(str_contains($sessionExecutable, 'functionrequireManagerAccess(){requireLogin();if(!isAdmin()&&!isManager()){'), 'manager-access guard still excludes trainers');

$deleteSource = stripExactBootstraps($deleteOriginal, $report);

$authorizedCases = [
    ['trainer', '007', 7],
    ['manager', 42, 42],
    ['admin', 900, 900],
];
foreach ($authorizedCases as [$role, $submitted, $expectedId]) {
    $report->scenario();
    $label = "{$role} valid POST";
    $result = runDeleteScenario($deleteSource, $role, 'POST', ['id' => $submitted]);
    $response = $result['response'];
    $connection = $result['connection'];
    $statement = $connection->statement;

    $report->check($result['unexpected'] === null, "{$label}: source executes without unexpected throwable");
    $report->check($response instanceof ResponseHalt && $response->success === true, "{$label}: returns success");
    $report->check($result['dbCalls'] === 1 && $connection->prepareCalls === 1, "{$label}: opens DB and prepares exactly once");
    $report->check($connection->preparedSql === ['DELETE FROM exercises WHERE id = ?'], "{$label}: prepares exact delete SQL");
    $report->check($statement instanceof MemoryStatement && $statement->bindCalls === 1, "{$label}: binds exactly once");
    $report->check($statement instanceof MemoryStatement && $statement->boundTypes === 'i', "{$label}: uses integer bind type");
    $boundId = $statement instanceof MemoryStatement ? ($statement->valuesAtExecute[0] ?? null) : null;
    $report->check(is_int($boundId) && $boundId === $expectedId, "{$label}: binds submitted ID as expected integer");
    $report->check($statement instanceof MemoryStatement && $statement->executeCalls === 1, "{$label}: executes exactly once");
}

foreach (['user', 'anonymous'] as $role) {
    $report->scenario();
    $label = "{$role} POST";
    $result = runDeleteScenario($deleteSource, $role, 'POST', ['id' => 21]);
    checkErrorResponse($result, $report, $label, 403);
    checkNoMutation($result, $report, $label);
}

$invalidCases = [
    'missing ID' => [],
    'empty ID' => ['id' => ''],
    'alphabetic ID' => ['id' => 'abc'],
    'zero ID' => ['id' => 0],
    'negative ID' => ['id' => -4],
    'positive-prefix junk ID' => ['id' => '7junk'],
    'fractional ID' => ['id' => '1.5'],
    'array ID' => ['id' => ['7']],
];
foreach ($invalidCases as $name => $post) {
    $report->scenario();
    $result = runDeleteScenario($deleteSource, 'trainer', 'POST', $post);
    checkErrorResponse($result, $report, $name, 400);
    checkNoMutation($result, $report, $name);
}

foreach (['GET', 'PUT', 'DELETE'] as $method) {
    $report->scenario();
    $label = "{$method} method";
    $result = runDeleteScenario($deleteSource, 'trainer', $method, ['id' => 33]);
    checkErrorResponse($result, $report, $label, 405);
    checkNoMutation($result, $report, $label);
}

$report->scenario();
$failureResult = runDeleteScenario($deleteSource, 'trainer', 'POST', ['id' => 55], false);
$failureResponse = $failureResult['response'];
$failureStatement = $failureResult['connection']->statement;
$report->check($failureResult['unexpected'] === null, 'execute failure: source executes without unexpected throwable');
$report->check($failureResponse instanceof ResponseHalt && $failureResponse->success === false, 'execute failure: returns failure');
$report->check($failureResponse instanceof ResponseHalt && str_contains($failureResponse->getMessage(), 'simulated execute error'), 'execute failure: reports connection error');
$report->check($failureResult['connection']->preparedSql === ['DELETE FROM exercises WHERE id = ?'], 'execute failure: uses exact delete SQL');
$report->check($failureStatement instanceof MemoryStatement && $failureStatement->executeCalls === 1, 'execute failure: executes exactly once');

$report->finish();
