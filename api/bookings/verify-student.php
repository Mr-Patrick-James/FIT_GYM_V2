<?php
/**
 * API to verify student and apply discount
 * POST /api/bookings/verify-student.php
 */
require_once '../../api/config.php';
require_once '../../api/session.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
    exit;
}

// Get booking ID
$bookingId = $input['booking_id'] ?? null;
$studentIdUrl = $input['student_id_url'] ?? null;

if (!$bookingId || !$studentIdUrl) {
    sendResponse(false, 'Booking ID and student ID URL are required', null, 400);
    exit;
}

try {
    $conn = getDBConnection();

    // Get booking details
    $bookingStmt = $conn->prepare("SELECT b.*, p.student_discount FROM bookings b 
                                  LEFT JOIN packages p ON b.package_id = p.id 
                                  WHERE b.id = ?");
    $bookingStmt->bind_param("i", $bookingId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();

    if ($bookingResult->num_rows === 0) {
        sendResponse(false, 'Booking not found', null, 404);
        exit;
    }

    $booking = $bookingResult->fetch_assoc();
    $studentDiscount = $booking['student_discount'] ?? 10;

    // Calculate discount
    $discountAmount = ($booking['amount'] * $studentDiscount) / 100;
    $newAmount = $booking['amount'] - $discountAmount;

    // Update booking with student verification
    $updateStmt = $conn->prepare("UPDATE bookings 
                                 SET is_student = 1, 
                                     student_id_url = ?, 
                                     student_discount_applied = ?,
                                     amount = ?
                                 WHERE id = ?");
    $updateStmt->bind_param("sddi", $studentIdUrl, $discountAmount, $newAmount, $bookingId);
    
    if (!$updateStmt->execute()) {
        sendResponse(false, 'Failed to update booking', null, 500);
        exit;
    }

    sendResponse(true, 'Student discount applied successfully', [
        'booking_id' => $bookingId,
        'original_amount' => floatval($booking['amount']),
        'discount_percentage' => floatval($studentDiscount),
        'discount_amount' => floatval($discountAmount),
        'new_amount' => floatval($newAmount),
        'student_id_url' => $studentIdUrl
    ]);

} catch (Exception $e) {
    error_log("Error verifying student: " . $e->getMessage());
    sendResponse(false, 'Internal server error', null, 500);
}
?>
