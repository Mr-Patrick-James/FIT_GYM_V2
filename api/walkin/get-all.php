<?php
require_once '../config.php';

// Allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    
    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'date-desc';
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;
    
    // Query walk-in bookings specifically (user_id IS NULL)
    $sql = "SELECT b.*, p.name as package_name, p.duration FROM bookings b 
            LEFT JOIN packages p ON b.package_id = p.id 
            WHERE b.user_id IS NULL";
    
    $params = [];
    $types = "";
    
    // Add status filter
    if ($status !== 'all') {
        $sql .= " AND b.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Add date range filter
    if ($date_from) {
        $sql .= " AND DATE(b.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if ($date_to) {
        $sql .= " AND DATE(b.created_at) <= ?";
        $params[] = $date_to;
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
        $booking['time_formatted'] = date('h:i A', strtotime($booking['created_at']));
        $booking['is_walkin'] = true; // Flag to identify walk-in bookings
        
        // Use the package name from the booking record if available, otherwise from the packages table
        $booking['package_name'] = $booking['package_name'] ?: $booking['package_name'];
        
        // Add full URL for receipt
        if (!empty($booking['receipt_url'])) {
            $cleanPath = ltrim(str_replace(['Fit/', 'Fit\\'], '', $booking['receipt_url']), '/\\');
            $booking['receipt_full_url'] = BASE_URL . '/' . $cleanPath;
        } else {
            $booking['receipt_full_url'] = null;
        }
        
        // Get payment info for this booking
        $paymentSql = "SELECT status, payment_method, transaction_id, notes FROM payments WHERE booking_id = ?";
        $paymentStmt = $conn->prepare($paymentSql);
        $paymentStmt->bind_param("i", $booking['id']);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $payment = $paymentResult->fetch_assoc();
        
        $booking['payment_status'] = $payment['status'] ?? 'pending';
        $booking['payment_method'] = $payment['payment_method'] ?? 'cash';
        $booking['payment_notes'] = $payment['notes'] ?? null;
    }
    
    sendResponse(true, 'Walk-in bookings retrieved successfully', $bookings);

} catch (Exception $e) {
    error_log("Error getting walk-in bookings: " . $e->getMessage());
    sendResponse(false, 'Error retrieving walk-in bookings: ' . $e->getMessage(), null, 500);
}
?>
