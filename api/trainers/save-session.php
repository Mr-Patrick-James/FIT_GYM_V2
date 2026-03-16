<?php
require_once '../config.php';
require_once '../session.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$booking_id = (int)$data['booking_id'];
$member_id = (int)$data['member_id'];
$session_date = $data['session_date'];
$session_time = $data['session_time'];
$duration = (int)($data['duration'] ?? 60);
$notes = $data['notes'] ?? '';

$user = getCurrentUser();
$conn = getDBConnection();

// Get trainer ID
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainerId = $trainerStmt->get_result()->fetch_assoc()['id'];

$stmt = $conn->prepare("INSERT INTO trainer_sessions (trainer_id, member_id, booking_id, session_date, session_time, duration, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiissis", $trainerId, $member_id, $booking_id, $session_date, $session_time, $duration, $notes);

if ($stmt->execute()) {
    // Notify member
    createNotification($member_id, 'New Workout Session Scheduled', "Coach " . $user['name'] . " scheduled a session for you on $session_date at $session_time.", 'session');
    sendResponse(true, 'Session scheduled');
} else {
    sendResponse(false, 'Failed to schedule session: ' . $stmt->error);
}
?>
