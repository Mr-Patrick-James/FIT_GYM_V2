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
    $user_id = $_SESSION['user_id'];
    
    // Get user's current active booking
    $currentBookingQuery = "SELECT b.*, p.price as current_price, p.duration as current_duration 
                           FROM bookings b 
                           LEFT JOIN packages p ON b.package_id = p.id 
                           WHERE b.user_id = ? AND b.status IN ('verified', 'pending') 
                           ORDER BY b.created_at DESC LIMIT 1";
    $currentStmt = $conn->prepare($currentBookingQuery);
    $currentStmt->bind_param("i", $user_id);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentBooking = $currentResult->fetch_assoc();
    
    $response = [
        'can_book' => true,
        'reason' => null,
        'current_booking' => null,
        'available_upgrades' => []
    ];
    
    if ($currentBooking) {
        $response['current_booking'] = [
            'id' => $currentBooking['id'],
            'package_name' => $currentBooking['package_name'],
            'status' => $currentBooking['status'],
            'expires_at' => $currentBooking['expires_at'],
            'amount' => $currentBooking['amount'],
            'current_price' => $currentBooking['current_price']
        ];
        
        // If user has a pending booking
        if ($currentBooking['status'] === 'pending') {
            $response['can_book'] = false;
            $response['reason'] = 'pending_booking';
        }
        
        // If user has a verified booking that hasn't expired
        if ($currentBooking['status'] === 'verified' && $currentBooking['expires_at']) {
            $currentTime = date('Y-m-d H:i:s');
            if ($currentBooking['expires_at'] > $currentTime) {
                $response['can_book'] = false;
                $response['reason'] = 'active_booking';
                
                // Get available higher-tier packages for upgrade
                $upgradeQuery = "SELECT p.* FROM packages p 
                                WHERE p.is_active = 1 AND p.price > ? 
                                ORDER BY p.price ASC";
                $upgradeStmt = $conn->prepare($upgradeQuery);
                $upgradeStmt->bind_param("d", $currentBooking['current_price']);
                $upgradeStmt->execute();
                $upgradeResult = $upgradeStmt->get_result();
                $upgrades = $upgradeResult->fetch_all(MYSQLI_ASSOC);
                
                // Format upgrade options
                foreach ($upgrades as &$upgrade) {
                    $upgrade['price_formatted'] = '₱' . number_format($upgrade['price'], 2);
                    $upgrade['upgrade_price'] = $upgrade['price'] - $currentBooking['current_price'];
                    $upgrade['upgrade_price_formatted'] = '₱' . number_format($upgrade['upgrade_price'], 2);
                }
                
                $response['available_upgrades'] = $upgrades;
            }
        }
    }
    
    sendResponse(true, 'Booking status checked successfully', $response);

} catch (Exception $e) {
    error_log("Error checking booking status: " . $e->getMessage());
    sendResponse(false, 'Error checking booking status: ' . $e->getMessage(), null, 500);
}
?>
