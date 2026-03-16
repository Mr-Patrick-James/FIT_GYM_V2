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
$session_time = $data['session_time'] ?? '08:00:00';
$duration = (int)($data['duration'] ?? 60);
$type = $data['type'] ?? 'workout';
$title = $data['title'] ?? ($type === 'rest_day' ? 'Rest Day' : 'Workout Session');
$notes = $data['notes'] ?? '';
$exercises = isset($data['exercises']) ? (is_array($data['exercises']) ? implode(',', $data['exercises']) : $data['exercises']) : '';

$user = getCurrentUser();
$conn = getDBConnection();

// Get trainer ID
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainerId = $trainerStmt->get_result()->fetch_assoc()['id'];

$stmt = $conn->prepare("INSERT INTO trainer_sessions (trainer_id, member_id, booking_id, session_date, session_time, duration, type, title, notes, exercises) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiississss", $trainerId, $member_id, $booking_id, $session_date, $session_time, $duration, $type, $title, $notes, $exercises);

if ($stmt->execute()) {
    // Notify member
    $notifTitle = $type === 'rest_day' ? 'Rest Day Scheduled' : 'New Workout Session Scheduled';
    $notifMsg = $type === 'rest_day' ? "Coach " . $user['name'] . " scheduled a rest day for you on $session_date." : "Coach " . $user['name'] . " scheduled a session for you on $session_date at $session_time.";
    createNotification($member_id, $notifTitle, $notifMsg, 'session');
    sendResponse(true, 'Session scheduled');
} else {
    sendResponse(false, 'Failed to schedule session: ' . $stmt->error);
}
?>
