<?php
/**
 * API to mark a booking as student-verified (no discount applied)
 * POST /api/bookings/verify-student.php
 */
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$bookingId    = $input['booking_id']    ?? null;
$studentIdUrl = $input['student_id_url'] ?? null;

if (!$bookingId) {
    sendResponse(false, 'Booking ID is required', null, 400);
    exit;
}

try {
    $conn = getDBConnection();

    // Just flag the booking as student — no amount change
    $stmt = $conn->prepare("UPDATE bookings SET is_student = 1, student_id_url = ? WHERE id = ?");
    $stmt->bind_param("si", $studentIdUrl, $bookingId);

    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to update booking', null, 500);
        exit;
    }

    sendResponse(true, 'Student ID verified — no discount applied', [
        'booking_id' => $bookingId
    ]);

} catch (Exception $e) {
    error_log("Error verifying student: " . $e->getMessage());
    sendResponse(false, 'Internal server error', null, 500);
}
?>
