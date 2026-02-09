<?php
require_once '../config.php';
require_once '../session.php';

// Allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    $conn = getDBConnection();
    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'date-desc';
    
    $isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    $userId = $_SESSION['user_id'];
    
    $sql = "SELECT b.*, u.name as user_name, p.name as package_name, p.duration, b.expires_at FROM bookings b 
            LEFT JOIN users u ON b.user_id = u.id 
            LEFT JOIN packages p ON b.package_id = p.id WHERE 1=1";
    
    // If not admin, only show user's own bookings
    if (!$isAdmin) {
        $sql .= " AND b.user_id = ?";
    }
    
    $params = [];
    $types = "";
    
    if (!$isAdmin) {
        $params[] = $userId;
        $types .= "i";
    }
    
    // Add status filter
    if ($status !== 'all') {
        $sql .= " AND b.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Add search filter
    if (!empty($search)) {
        $sql .= " AND (b.name LIKE ? OR b.email LIKE ? OR b.package_name LIKE ? OR b.contact LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        $types .= "ssss";
    }
    
    // Add sorting
    switch ($sort) {
        case 'date-asc':
            $sql .= " ORDER BY b.created_at ASC";
            break;
        case 'amount-desc':
            $sql .= " ORDER BY b.amount DESC";
            break;
        case 'amount-asc':
            $sql .= " ORDER BY b.amount ASC";
            break;
        case 'name-asc':
            $sql .= " ORDER BY b.name ASC";
            break;
        case 'name-desc':
            $sql .= " ORDER BY b.name DESC";
            break;
        case 'date-desc':
        default:
            $sql .= " ORDER BY b.created_at DESC";
            break;
    }
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result->fetch_all(MYSQLI_ASSOC);
    
    // Format the bookings data
    foreach ($bookings as &$booking) {
        $booking['amount_formatted'] = '₱' . number_format($booking['amount'], 2);
        $booking['date_formatted'] = date('M j, Y', strtotime($booking['booking_date'] ?? $booking['created_at']));
        
        // Identify walk-in bookings (user_id is NULL)
        $booking['is_walkin'] = is_null($booking['user_id']);
        
        // Use the package name from the booking record if available, otherwise from the packages table
        $booking['package_name'] = $booking['package_name'] ?: $booking['package_name'];
        
        // Add full URL for receipt
        if (!empty($booking['receipt_url'])) {
            $cleanPath = ltrim(str_replace(['Fit/', 'Fit\\'], '', $booking['receipt_url']), '/\\');
            $booking['receipt_full_url'] = BASE_URL . '/' . $cleanPath;
        } else {
            $booking['receipt_full_url'] = null;
        }
    }
    
    sendResponse(true, 'Bookings retrieved successfully', $bookings);

} catch (Exception $e) {
    error_log("Error getting bookings: " . $e->getMessage());
    sendResponse(false, 'Error retrieving bookings: ' . $e->getMessage(), null, 500);
}
?>