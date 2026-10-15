<?php
require_once '../config.php';
require_once '../session.php';
require_once __DIR__.'/booking-access.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['booking_id']) || empty($data['logged_at'])) {
    sendResponse(false, 'Booking ID and date are required', null, 400);
}

if (!trainerValidCalendarDate($data['logged_at'])) {
    sendResponse(false, 'A valid progress date (YYYY-MM-DD) is required', null, 400);
}

$booking_id = (int)$data['booking_id'];
$weight = isset($data['weight']) ? (float)$data['weight'] : null;
$remarks = $data['remarks'] ?? '';
$logged_at = $data['logged_at'];
$user = getCurrentUser();

$conn = getDBConnection();
$trainerId = requireTrainerProfileId($conn, (int)$user['id']);
requireWritableTrainerBooking($conn, $booking_id, $trainerId);
$conn->begin_transaction();

try {
    // Insert new progress log
    $height = isset($data['height']) ? (float)$data['height'] : null;
    $stmt = $conn->prepare("INSERT INTO member_progress (booking_id, trainer_id, weight, height, remarks, logged_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiddss", $booking_id, $trainerId, $weight, $height, $remarks, $logged_at);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to log progress: ' . $stmt->error);
    }

    $conn->commit();
    $conn->close();
    sendResponse(true, 'Member progress logged successfully');

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, $e->getMessage());
}
?>
