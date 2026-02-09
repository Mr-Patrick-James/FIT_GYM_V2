<?php
require_once '../config.php';
require_once '../session.php';

// Allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    $conn = getDBConnection();
    // Get package statistics from the database
    // Total packages
    $totalPackagesStmt = $conn->prepare("SELECT COUNT(*) as count FROM packages WHERE is_active = 1");
    $totalPackagesStmt->execute();
    $totalPackagesResult = $totalPackagesStmt->get_result();
    $totalPackages = $totalPackagesResult->fetch_assoc()['count'];
    
    // Get booking statistics by joining with bookings table
    $statsQuery = "
        SELECT 
            p.name as package_name,
            COUNT(b.id) as total_bookings,
            SUM(CASE WHEN b.status = 'verified' THEN b.amount ELSE 0 END) as total_revenue
        FROM packages p
        LEFT JOIN bookings b ON p.id = b.package_id
        WHERE p.is_active = 1
        GROUP BY p.id, p.name
        ORDER BY total_bookings DESC
    ";
    
    $statsStmt = $conn->prepare($statsQuery);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $packageStats = $statsResult->fetch_all(MYSQLI_ASSOC);
    
    // Calculate overall totals
    $totalBookings = 0;
    $totalRevenue = 0;
    $packageBookings = [];
    $packageRevenue = [];
    $popularPackage = '-';
    
    foreach ($packageStats as $stat) {
        $packageBookings[$stat['package_name']] = (int)$stat['total_bookings'];
        $packageRevenue[$stat['package_name']] = (float)$stat['total_revenue'];
        $totalBookings += (int)$stat['total_bookings'];
        $totalRevenue += (float)$stat['total_revenue'];
    }
    
    // Find most popular package
    $maxBookings = 0;
    foreach ($packageBookings as $pkgName => $bookings) {
        if ($bookings > $maxBookings) {
            $maxBookings = $bookings;
            $popularPackage = $pkgName;
        }
    }
    
    // Get pending bookings count for badge
    $pendingStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'pending'");
    $pendingStmt->execute();
    $pendingResult = $pendingStmt->get_result();
    $pendingBookings = $pendingResult->fetch_assoc()['count'];

    $stats = [
        'totalPackages' => $totalPackages,
        'totalBookings' => $totalBookings,
        'totalRevenue' => $totalRevenue,
        'packageBookings' => $packageBookings,
        'packageRevenue' => $packageRevenue,
        'popularPackage' => $popularPackage,
        'pendingBookings' => $pendingBookings
    ];
    
    sendResponse(true, 'Package statistics retrieved successfully', $stats);

} catch (Exception $e) {
    error_log("Error getting package stats: " . $e->getMessage());
    sendResponse(false, 'Error retrieving package statistics: ' . $e->getMessage(), null, 500);
}
?>