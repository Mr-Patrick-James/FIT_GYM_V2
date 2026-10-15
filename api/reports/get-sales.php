<?php
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in and has proper permissions
requireLogin();

// Only admin and manager can view sales reports
if (!isAdmin() && !isManager()) {
    sendResponse(false, 'Access denied. Admin or Manager privileges required.', null, 403);
}

try {
    $startDate = $_GET['start_date'] ?? null;
    $endDate   = $_GET['end_date']   ?? null;

    foreach (['start_date' => $startDate, 'end_date' => $endDate] as $key => $date) {
        if ($date !== null && (!is_string($date) ||
            !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $date) ||
            !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
            sendResponse(false, "$key must be a valid YYYY-MM-DD date", null, 400);
            return;
        }
    }
    if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
        sendResponse(false, 'start_date must not be after end_date', null, 400);
        return;
    }

    $conn = getDBConnection();

    // Paid activity uses verification time; other bookings use creation time.
    $saleDateSQL = "DATE(CASE WHEN b.status IN ('verified','expired','upgraded')
        THEN COALESCE(b.verified_at,b.created_at,b.booking_date)
        ELSE COALESCE(b.created_at,b.booking_date) END)";
    $paidRevenueSQL = "COALESCE(SUM(CASE WHEN b.status IN ('verified','expired','upgraded') THEN b.amount ELSE 0 END), 0)";
    $pendingRevenueSQL = "COALESCE(SUM(CASE WHEN b.status = 'pending' THEN b.amount ELSE 0 END), 0)";
    $clientIdentitySQL = "CASE
        WHEN b.user_id IS NOT NULL THEN CONCAT('user:', b.user_id)
        WHEN NULLIF(TRIM(b.email), '') IS NOT NULL THEN CONCAT('walkin:email:', LOWER(TRIM(b.email)))
        WHEN NULLIF(TRIM(b.contact), '') IS NOT NULL THEN CONCAT('walkin:contact:', TRIM(b.contact))
        WHEN NULLIF(TRIM(b.name), '') IS NOT NULL THEN CONCAT('walkin:name:', LOWER(TRIM(b.name)))
        ELSE CONCAT('walkin:booking:', b.id) END";

    // Build parameterized date filter shared by all report queries.
    $dateFilter = '';
    $params = [];
    $types  = '';

    if ($startDate !== null && $endDate !== null) {
        $dateFilter = " AND $saleDateSQL BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types   .= 'ss';
    } elseif ($startDate !== null) {
        $dateFilter = " AND $saleDateSQL >= ?";
        $params[] = $startDate;
        $types   .= 's';
    } elseif ($endDate !== null) {
        $dateFilter = " AND $saleDateSQL <= ?";
        $params[] = $endDate;
        $types   .= 's';
    }

    // --- Sales by date ---
    $salesByDateSQL = "
        SELECT $saleDateSQL as sale_date,
               COUNT(b.id) as total_sales,
               $paidRevenueSQL as total_revenue,
               $paidRevenueSQL as verified_revenue,
               $pendingRevenueSQL as pending_revenue,
               SUM(CASE WHEN b.status = 'verified' THEN 1 ELSE 0 END) as verified_count,
               SUM(CASE WHEN b.status = 'expired' THEN 1 ELSE 0 END) as expired_count,
               SUM(CASE WHEN b.status = 'upgraded' THEN 1 ELSE 0 END) as upgraded_count,
               SUM(CASE WHEN b.status IN ('verified','expired','upgraded') THEN 1 ELSE 0 END) as paid_count,
               SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
               SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
               SUM(CASE WHEN b.user_id IS NULL THEN 1 ELSE 0 END) as walkin_count,
               SUM(CASE WHEN b.user_id IS NOT NULL THEN 1 ELSE 0 END) as member_count
        FROM bookings b
        WHERE 1=1 $dateFilter
        GROUP BY $saleDateSQL
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
            SUM(CASE WHEN b.status = 'expired' THEN 1 ELSE 0 END) as expired_count,
               SUM(CASE WHEN b.status = 'upgraded' THEN 1 ELSE 0 END) as upgraded_count,
            SUM(CASE WHEN b.status IN ('verified','expired','upgraded') THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN b.status = 'pending'  THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            $paidRevenueSQL as total_revenue,
            $paidRevenueSQL as verified_revenue,
            $pendingRevenueSQL as pending_revenue,
            SUM(CASE WHEN b.user_id IS NULL THEN 1 ELSE 0 END) as walkin_count,
            SUM(CASE WHEN b.user_id IS NOT NULL THEN 1 ELSE 0 END) as member_count,
            COUNT(DISTINCT $clientIdentitySQL) as unique_users
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
            $paidRevenueSQL as total_revenue,
            $paidRevenueSQL as verified_revenue,
            $pendingRevenueSQL as pending_revenue,
            COALESCE(SUM(CASE WHEN b.status = 'verified' THEN 1 ELSE 0 END), 0) as verified_bookings,
            COALESCE(SUM(CASE WHEN b.status = 'expired' THEN 1 ELSE 0 END), 0) as expired_bookings,
            COALESCE(SUM(CASE WHEN b.status = 'upgraded' THEN 1 ELSE 0 END), 0) as upgraded_bookings,
            COALESCE(SUM(CASE WHEN b.status IN ('verified','expired','upgraded') THEN 1 ELSE 0 END), 0) as paid_bookings,
            COALESCE(SUM(CASE WHEN b.status = 'pending'  THEN 1 ELSE 0 END), 0) as pending_bookings,
            COALESCE(SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_bookings,
            COALESCE(SUM(CASE WHEN b.user_id IS NULL THEN 1 ELSE 0 END), 0) as walkin_bookings,
            COUNT(DISTINCT $clientIdentitySQL) as unique_clients
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
