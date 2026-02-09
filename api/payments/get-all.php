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
    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'date-desc';
    
    $sql = "SELECT p.*, 
                   COALESCE(u.name, b.name) as user_name, 
                   COALESCE(u.email, b.email) as user_email,
                   COALESCE(u.contact, b.contact) as user_contact,
                   b.package_name, b.receipt_url as booking_receipt 
            FROM payments p 
            LEFT JOIN users u ON p.user_id = u.id 
            LEFT JOIN bookings b ON p.booking_id = b.id WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Add status filter
    if ($status !== 'all') {
        $sql .= " AND p.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // Add search filter
    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR b.name LIKE ? OR b.email LIKE ? OR b.package_name LIKE ? OR p.transaction_id LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
        $types .= "ssssss";
    }
    
    // Add sorting
    switch ($sort) {
        case 'date-asc':
            $sql .= " ORDER BY p.created_at ASC";
            break;
        case 'amount-desc':
            $sql .= " ORDER BY p.amount DESC";
            break;
        case 'amount-asc':
            $sql .= " ORDER BY p.amount ASC";
            break;
        case 'name-asc':
            $sql .= " ORDER BY u.name ASC";
            break;
        case 'name-desc':
            $sql .= " ORDER BY u.name DESC";
            break;
        case 'date-desc':
        default:
            $sql .= " ORDER BY p.created_at DESC";
            break;
    }
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
    
    // Format the payments data
    foreach ($payments as &$payment) {
        $payment['amount_formatted'] = '₱' . number_format($payment['amount'], 2);
        $payment['date_formatted'] = date('M j, Y', strtotime($payment['created_at']));
        
        // Use COALESCE'd user info
        $payment['name'] = $payment['user_name'];
        $payment['email'] = $payment['user_email'];
        $payment['contact'] = $payment['user_contact'];
        
        // If payment doesn't have a receipt_url, use the one from the booking
        if (empty($payment['receipt_url']) && !empty($payment['booking_receipt'])) {
            $payment['receipt_url'] = $payment['booking_receipt'];
        }

        // Add full URL for receipt
        if (!empty($payment['receipt_url'])) {
            $cleanPath = ltrim(str_replace(['Fit/', 'Fit\\'], '', $payment['receipt_url']), '/\\');
            $payment['receipt_full_url'] = BASE_URL . '/' . $cleanPath;
        } else {
            $payment['receipt_full_url'] = null;
        }
    }
    
    sendResponse(true, 'Payments retrieved successfully', $payments);

} catch (Exception $e) {
    error_log("Error getting payments: " . $e->getMessage());
    sendResponse(false, 'Error retrieving payments: ' . $e->getMessage(), null, 500);
}
?>