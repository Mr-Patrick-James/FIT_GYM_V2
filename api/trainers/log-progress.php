<?php
require_once '../config.php';
require_once '../session.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['booking_id']) || empty($data['logged_at'])) {
    sendResponse(false, 'Booking ID and date are required', null, 400);
}

$booking_id = (int)$data['booking_id'];
    $weight = isset($data['weight']) ? (float)$data['weight'] : null;
    $remarks = $data['remarks'] ?? '';
    $logged_at = $data['logged_at'];
$user = getCurrentUser();

$conn = getDBConnection();
$conn->begin_transaction();

try {
    // Verify that the booking is assigned to this trainer
    $trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $trainerStmt->bind_param("i", $user['id']);
    $trainerStmt->execute();
    $trainer = $trainerStmt->get_result()->fetch_assoc();
    $trainerId = $trainer['id'];

    $checkStmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND trainer_id = ?");
    $checkStmt->bind_param("ii", $booking_id, $trainerId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows === 0) {
        throw new Exception('Unauthorized or booking not found');
    }

    // Insert new progress log
    $stmt = $conn->prepare("INSERT INTO member_progress (booking_id, trainer_id, weight, remarks, logged_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iidss", $booking_id, $trainerId, $weight, $remarks, $logged_at);
    
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
