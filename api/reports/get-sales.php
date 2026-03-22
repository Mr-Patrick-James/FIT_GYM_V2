<?php
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    $conn = getDBConnection();

    $startDate = $_GET['start_date'] ?? null;
    $endDate   = $_GET['end_date']   ?? null;

    // Build date filter
    $dateFilter = '';
    $params = [];
    $types  = '';

    if ($startDate && $endDate) {
        $dateFilter = ' AND DATE(b.created_at) BETWEEN ? AND ?';
        $params[] = $startDate;
        $params[] = $endDate;
        $types   .= 'ss';
    } elseif ($startDate) {
        $dateFilter = ' AND DATE(b.created_at) >= ?';
        $params[] = $startDate;
        $types   .= 's';
    } elseif ($endDate) {
        $dateFilter = ' AND DATE(b.created_at) <= ?';
        $params[] = $endDate;
        $types   .= 's';
    }

    // --- Sales by date ---
    $salesByDateSQL = "
        SELECT DATE(b.created_at) as sale_date,
               COUNT(b.id) as total_sales,
               SUM(b.amount) as total_revenue,
               SUM(CASE WHEN b.status = 'verified' THEN b.amount ELSE 0 END) as verified_revenue,
               SUM(CASE WHEN b.user_id IS NULL THEN 1 ELSE 0 END) as walkin_count,
               SUM(CASE WHEN b.user_id IS NOT NULL THEN 1 ELSE 0 END) as member_count
        FROM bookings b
        WHERE 1=1 $dateFilter
        GROUP BY DATE(b.created_at)
        ORDER BY sale_date ASC
    ";

    $stmt = $conn->prepare($salesByDateSQL);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $salesByDate = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- Package sales ---
    $packageSalesSQL = "
        SELECT 
            COALESCE(p.name, b.package_name, 'Unknown') as package_name,
            p.price as package_price,
            p.duration as package_duration,
            p.tag as package_tag,
            COUNT(b.id) as total_availed,
            SUM(CASE WHEN b.status = 'verified' THEN 1 ELSE 0 END) as verified_count,
            SUM(CASE WHEN b.status = 'pending'  THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(b.amount) as total_revenue,
            SUM(CASE WHEN b.user_id IS NULL THEN 1 ELSE 0 END) as walkin_count,
            SUM(CASE WHEN b.user_id IS NOT NULL THEN 1 ELSE 0 END) as member_count,
            COUNT(DISTINCT COALESCE(b.user_id, b.email)) as unique_users
        FROM bookings b
        LEFT JOIN packages p ON b.package_id = p.id
        WHERE 1=1 $dateFilter
        GROUP BY COALESCE(p.name, b.package_name, 'Unknown'), p.price, p.duration, p.tag
        ORDER BY total_availed DESC
    ";

    $stmt2 = $conn->prepare($packageSalesSQL);
    if (!empty($params)) $stmt2->bind_param($types, ...$params);
    $stmt2->execute();
    $packageSales = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt2->close();

    // --- Summary totals ---
    $summarySQL = "
        SELECT 
            COUNT(b.id) as total_bookings,
            SUM(b.amount) as total_revenue,
            SUM(CASE WHEN b.status = 'verified' THEN b.amount ELSE 0 END) as verified_revenue,
            SUM(CASE WHEN b.status = 'verified' THEN 1 ELSE 0 END) as verified_bookings,
            SUM(CASE WHEN b.status = 'pending'  THEN 1 ELSE 0 END) as pending_bookings,
            SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) as rejected_bookings,
            SUM(CASE WHEN b.user_id IS NULL THEN 1 ELSE 0 END) as walkin_bookings,
            COUNT(DISTINCT COALESCE(b.user_id, b.email)) as unique_clients
        FROM bookings b
        WHERE 1=1 $dateFilter
    ";

    $stmt3 = $conn->prepare($summarySQL);
    if (!empty($params)) $stmt3->bind_param($types, ...$params);
    $stmt3->execute();
    $summary = $stmt3->get_result()->fetch_assoc();
    $stmt3->close();

    $conn->close();

    sendResponse(true, 'Sales report retrieved', [
        'summary'      => $summary,
        'sales_by_date' => $salesByDate,
        'package_sales' => $packageSales,
        'filters'      => [
            'start_date' => $startDate,
            'end_date'   => $endDate
        ]
    ]);

} catch (Exception $e) {
    error_log('Sales report error: ' . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
