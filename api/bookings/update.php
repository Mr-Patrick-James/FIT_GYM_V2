<?php
require_once '../config.php';
require_once '../session.php';

// Allow POST or PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    $conn = getDBConnection();
    
    // Get the request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Get the booking ID from the URL or JSON body
    $bookingId = $_GET['id'] ?? ($input['id'] ?? null);
    
    if (!$input) {
        sendResponse(false, 'Invalid request data', null, 400);
    }
    
    $status = $input['status'] ?? null;
    $notes = $input['notes'] ?? null;
    
    if (!$status) {
        sendResponse(false, 'Status is required', null, 400);
    }
    
    // Validate status
    if (!in_array($status, ['pending', 'verified', 'rejected'])) {
        sendResponse(false, 'Invalid status', null, 400);
    }
    
    // Update the booking
    $sql = "UPDATE bookings SET status = ?, notes = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $notes, $bookingId);
    $result = $stmt->execute();
    
    if (!$result) {
        sendResponse(false, 'Failed to update booking', null, 500);
    }
    
    // If status is verified, also add to payments and set expiry date
    if ($status === 'verified') {
        // Get booking details to add to payments
        $sql = "SELECT b.*, p.duration FROM bookings b 
                LEFT JOIN packages p ON b.package_id = p.id 
                WHERE b.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $bookingResult = $stmt->get_result();
        $booking = $bookingResult->fetch_assoc();
        
        if ($booking) {
            // Subscription starts NOW (at verification)
            $startDate = date('Y-m-d H:i:s');
            $duration = $booking['duration'];
            
            // Basic duration parsing
            $days = 0;
            if (stripos($duration, 'Day') !== false) {
                $days = (int)$duration;
            } elseif (stripos($duration, 'Week') !== false) {
                $days = (int)$duration * 7;
            } elseif (stripos($duration, 'Month') !== false) {
                $days = (int)$duration * 30;
            } elseif (stripos($duration, 'Year') !== false) {
                $days = (int)$duration * 365;
            }
            
            if ($days > 0) {
                $expiresAt = date('Y-m-d H:i:s', strtotime($startDate . " + $days days"));
                // Update both booking_date (as start date) and expires_at in bookings table
                $expirySql = "UPDATE bookings SET booking_date = ?, expires_at = ? WHERE id = ?";
                $expiryStmt = $conn->prepare($expirySql);
                $expiryStmt->bind_param("ssi", $startDate, $expiresAt, $bookingId);
                $expiryStmt->execute();
            }

            // Add to payments table including the receipt_url
            // Use REPLACE INTO or INSERT ... ON DUPLICATE KEY UPDATE to avoid duplicates
            $paymentSql = "INSERT INTO payments (user_id, booking_id, amount, status, payment_method, transaction_id, receipt_url, created_at) 
                           VALUES (?, ?, ?, 'completed', 'Booking Payment', CONCAT('BK_', ?), ?, NOW())
                           ON DUPLICATE KEY UPDATE 
                           amount = VALUES(amount),
                           status = VALUES(status),
                           receipt_url = VALUES(receipt_url),
                           updated_at = NOW()";
            $paymentStmt = $conn->prepare($paymentSql);
            $paymentStmt->bind_param("iidss", 
                $booking['user_id'],
                $booking['id'],
                $booking['amount'],
                $booking['id'],
                $booking['receipt_url']
            );
            $paymentStmt->execute();
        }
    }
    
    sendResponse(true, 'Booking updated successfully');

} catch (Exception $e) {
    error_log("Error updating booking: " . $e->getMessage());
    sendResponse(false, 'Error updating booking: ' . $e->getMessage(), null, 500);
}
?>