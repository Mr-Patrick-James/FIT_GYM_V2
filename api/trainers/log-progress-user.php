<?php
require_once '../config.php';
require_once '../session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['booking_id']) || empty($data['logged_at'])) {
    sendResponse(false, 'Booking ID and date are required', null, 400);
}

$user = getCurrentUser();
$booking_id = (int)$data['booking_id'];
$weight     = isset($data['weight'])    ? (float)$data['weight']   : null;
$height     = isset($data['height'])    ? (float)$data['height']   : null;
$remarks    = $data['remarks']    ?? '';
$photo_url  = $data['photo_url']  ?? null;
$logged_at  = $data['logged_at'];

$conn = getDBConnection();

// Verify booking belongs to this user
$checkStmt = $conn->prepare("SELECT id, trainer_id FROM bookings WHERE id = ? AND user_id = ? AND status = 'verified'");
$checkStmt->bind_param("ii", $booking_id, $user['id']);
$checkStmt->execute();
$booking = $checkStmt->get_result()->fetch_assoc();

if (!$booking) {
    sendResponse(false, 'Booking not found or not verified', null, 403);
}

$trainer_id = $booking['trainer_id'];

$stmt = $conn->prepare("INSERT INTO member_progress (booking_id, trainer_id, weight, height, remarks, photo_url, logged_by, logged_at) VALUES (?, ?, ?, ?, ?, ?, 'user', ?)");
$stmt->bind_param("iiddsss", $booking_id, $trainer_id, $weight, $height, $remarks, $photo_url, $logged_at);

if (!$stmt->execute()) {
    sendResponse(false, 'Failed to log progress: ' . $stmt->error, null, 500);
}

$conn->close();
sendResponse(true, 'Progress logged successfully');
?>
