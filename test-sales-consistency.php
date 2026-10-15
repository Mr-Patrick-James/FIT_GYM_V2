<?php
// Run with PHP CLI. Executes SELECT queries inside a read-only transaction.
// No application sessions, API calls, schema changes, or data backfills.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require_once __DIR__ . '/api/config.php';

function checkValue($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

// Evaluate only the trusted SQL string assignments, not the endpoint itself.
function sqlAssignment($source, $name, $variables = []) {
    extract($variables, EXTR_SKIP);
    if (!preg_match('/\$' . preg_quote($name, '/') . '\s*=\s*(".*?")\s*;/s', $source, $match)) {
        throw new RuntimeException('Missing SQL assignment: ' . $name);
    }
    return eval('return ' . $match[1] . ';');
}

function reportQueries($source, $dateFilter = '') {
    $variables = ['dateFilter' => $dateFilter];
    foreach (['saleDateSQL', 'paidRevenueSQL', 'pendingRevenueSQL', 'clientIdentitySQL'] as $name) {
        $variables[$name] = sqlAssignment($source, $name);
    }
    $queries = [];
    foreach (['summarySQL', 'salesByDateSQL', 'packageSalesSQL'] as $name) {
        $queries[$name] = sqlAssignment($source, $name, $variables);
    }
    return $queries;
}

function assertReconciles($connection, $queries, $params = []) {
    $results = [];
    foreach ($queries as $name => $sql) {
        $statement = $connection->prepare($sql);
        if ($params) $statement->bind_param(str_repeat('s', count($params)), ...$params);
        $statement->execute();
        $results[$name] = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        $statement->close();
    }
    $summary = $results['summarySQL'][0];
    foreach (['salesByDateSQL' => 'total_sales', 'packageSalesSQL' => 'total_availed'] as $name => $countKey) {
        checkValue((int) array_sum(array_column($results[$name], $countKey)) === (int) $summary['total_bookings'], "$name booking count mismatch");
        checkValue(abs(array_sum(array_column($results[$name], 'total_revenue')) - (float) $summary['total_revenue']) < 0.001, "$name collected revenue mismatch");
        checkValue(abs(array_sum(array_column($results[$name], 'pending_revenue')) - (float) $summary['pending_revenue']) < 0.001, "$name pending revenue mismatch");
    }
    $statusTotal = 0;
    foreach (['verified', 'expired', 'upgraded', 'pending', 'rejected'] as $status) $statusTotal += (int) $summary[$status . '_bookings'];
    checkValue($statusTotal === (int) $summary['total_bookings'], 'Status breakdown does not cover all bookings');
    return $summary;
}

try {
    $connection = getDBConnection();
    $connection->query('START TRANSACTION READ ONLY');
    $reportSource = file_get_contents(__DIR__ . '/api/reports/get-sales.php');
    $queries = reportQueries($reportSource);
    $summary = assertReconciles($connection, $queries);
    $expected = $connection->query("SELECT COALESCE(SUM(amount),0) AS revenue FROM bookings WHERE status IN ('verified','expired','upgraded')")->fetch_assoc();
    checkValue((float) $summary['total_revenue'] === (float) $expected['revenue'], 'Collected total differs from stored paid amounts');

    $saleDate = sqlAssignment($reportSource, 'saleDateSQL');
    $monthStart = date('Y-m-01');
    $today = date('Y-m-d');
    assertReconciles($connection, reportQueries($reportSource, " AND $saleDate BETWEEN ? AND ?"), [$monthStart, $today]);
    $empty = assertReconciles($connection, reportQueries($reportSource, " AND $saleDate BETWEEN ? AND ?"), ['1900-01-01', '1900-01-02']);
    checkValue((int) $empty['total_bookings'] === 0 && (float) $empty['total_revenue'] === 0.0, 'Empty periods must return zero');

    // Read-only derived-table fixtures verify discounts, upgrades, date fallbacks,
    // pending/rejected separation, and member/walk-in identity collisions.
    $rows = [
        "SELECT 1 id, 1 user_id, NULL package_id, 'verified' status, 80.50 amount, '2026-10-15 10:00:00' verified_at, '2026-09-01 10:00:00' created_at, '2026-12-01' booking_date, 'Discounted' package_name, '1' email, '09111111111' contact, 'Fixture' name",
        "SELECT 2,NULL,NULL,'expired',20.00,NULL,'2026-10-15 10:00:00','2026-12-01','Discounted','1','09222222222','Fixture'",
        "SELECT 3,2,NULL,'pending',70.00,NULL,'2026-10-15 10:00:00','2026-12-01','Discounted','','','Fixture'",
        "SELECT 4,2,NULL,'rejected',90.00,NULL,'2026-10-15 10:00:00','2026-12-01','Discounted','','','Fixture'",
        "SELECT 5,2,NULL,'upgraded',30.25,'2026-10-15 11:00:00','2026-09-01 10:00:00','2026-12-01','Discounted','','','Fixture'"
    ];
    $derived = '(' . implode(' UNION ALL ', $rows) . ') b';
    $fixtureQueries = reportQueries($reportSource, " AND $saleDate BETWEEN ? AND ?");
    foreach ($fixtureQueries as &$sql) $sql = str_replace('FROM bookings b', 'FROM ' . $derived, $sql);
    unset($sql);
    $fixture = assertReconciles($connection, $fixtureQueries, ['2026-10-15', '2026-10-15']);
    checkValue((float) $fixture['total_revenue'] === 130.75, 'Discounted/expired/upgraded stored amounts must be retained');
    checkValue((float) $fixture['pending_revenue'] === 70.0, 'Rejected amounts must not become pending revenue');
    checkValue((int) $fixture['total_bookings'] === 5 && (int) $fixture['paid_bookings'] === 3, 'Fixture period/count mismatch');
    checkValue((int) $fixture['unique_clients'] === 3, 'User ids must not collide with walk-in emails');

    $userSource = file_get_contents(__DIR__ . '/api/users/get-all.php');
    $contactSQL = sqlAssignment($userSource, 'contactSQL');
    $contactCount = $connection->query("SELECT COUNT(*) members, SUM(($contactSQL) IS NOT NULL) contacts FROM users u WHERE role='user'")->fetch_assoc();
    $latestExpected = $connection->query("SELECT COUNT(DISTINCT u.id) contacts FROM users u LEFT JOIN bookings b ON b.user_id=u.id WHERE u.role='user' AND (NULLIF(TRIM(u.contact),'') IS NOT NULL OR NULLIF(TRIM(b.contact),'') IS NOT NULL)")->fetch_assoc();
    checkValue((int) $contactCount['contacts'] === (int) $latestExpected['contacts'], 'Booking contacts are not reflected for blank profiles');
    $contactFixture = str_replace('FROM bookings b', "FROM (SELECT 1 id, 1 user_id, 'old' contact, '2026-10-01' created_at UNION ALL SELECT 2,1,'latest','2026-10-15') b", $contactSQL);
    $profile = $connection->query("SELECT $contactFixture contact FROM (SELECT 1 id, 'profile' contact) u")->fetch_assoc();
    $fallback = $connection->query("SELECT $contactFixture contact FROM (SELECT 1 id, '' contact) u")->fetch_assoc();
    checkValue($profile['contact'] === 'profile' && $fallback['contact'] === 'latest', 'Profile preference/latest-booking fallback mismatch');

    $connection->rollback();
    $connection->close();
    print "PASS: live summary/daily/package reconciliation, empty/month periods, isolated sales fixtures, and contact fallback\n";
    print 'Local data: ' . $summary['total_bookings'] . ' bookings, PHP ' . number_format((float) $summary['total_revenue'], 2) . ' collected; contact available for ' . $contactCount['contacts'] . '/' . $contactCount['members'] . " registered members\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
