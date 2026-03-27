<?php
// Ensure no output before headers
ob_start();
require_once '../config.php';
require_once '../session.php';

// Clean any accidental output from config/session
if (ob_get_length()) ob_clean();

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
    
    $sql = "SELECT b.*, u.name as user_name, p.name as package_name, p.duration, p.is_trainer_assisted, b.expires_at, t.name as trainer_name 
            FROM bookings b 
            LEFT JOIN users u ON b.user_id = u.id 
            LEFT JOIN packages p ON b.package_id = p.id 
            LEFT JOIN trainers t ON b.trainer_id = t.id
            WHERE 1=1";
    
    // Check if table exists (for self-healing)
    $conn->query("CREATE TABLE IF NOT EXISTS `bookings` (
        `id` int NOT NULL AUTO_INCREMENT,
        `user_id` int DEFAULT NULL,
        `trainer_id` int DEFAULT NULL,
        `package_id` int DEFAULT NULL,
        `name` varchar(255) DEFAULT NULL,
        `email` varchar(255) DEFAULT NULL,
        `contact` varchar(255) DEFAULT NULL,
        `amount` decimal(10,2) DEFAULT '0.00',
        `payment_method` varchar(50) DEFAULT 'GCash',
        `receipt_url` varchar(255) DEFAULT NULL,
        `status` enum('pending','verified','rejected','expired') DEFAULT 'pending',
        `notes` text,
        `verified_at` datetime DEFAULT NULL,
        `expires_at` datetime DEFAULT NULL,
        `booking_date` datetime DEFAULT CURRENT_TIMESTAMP,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // If not admin, only show user's own bookings
    if (!$isAdmin) {
        $sql .= " AND b.user_id = ?";
    }
    
    // Log final SQL for debugging
    error_log("DEBUG Bookings SQL: " . $sql);
    
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
        // Ensure numeric amount
        $amt = (float)($booking['amount'] ?? 0);
        $booking['amount_formatted'] = '₱' . number_format($amt, 2);
        $booking['date_formatted'] = date('M j, Y', strtotime($booking['booking_date'] ?? $booking['created_at']));
        
        // Identify walk-in bookings (user_id is NULL)
        $booking['is_walkin'] = is_null($booking['user_id']);
        
        // Fetch linked trainers for the package if applicable
        $booking['package_trainer_ids'] = [];
        if ($booking['is_trainer_assisted'] && $booking['package_id']) {
            $tStmt = $conn->prepare("SELECT trainer_id FROM package_trainers WHERE package_id = ?");
            $tStmt->bind_param("i", $booking['package_id']);
            $tStmt->execute();
            $tRes = $tStmt->get_result();
            while ($tRow = $tRes->fetch_assoc()) {
                $booking['package_trainer_ids'][] = (int)$tRow['trainer_id'];
            }
            $tStmt->close();
        }

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