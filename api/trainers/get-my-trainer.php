<?php
require_once '../config.php';
require_once '../session.php';
requireLogin();

$user = getCurrentUser();
$conn = getDBConnection();

// Get the user's latest active verified booking with a trainer
$stmt = $conn->prepare("
    SELECT b.id as booking_id, b.trainer_id, b.expires_at, b.verified_at,
           p.name as package_name, p.duration, p.is_trainer_assisted,
           t.name as trainer_name, t.specialization, t.bio, t.photo_url,
           t.contact as trainer_contact, t.email as trainer_email,
           t.availability, t.certifications,
           (SELECT COUNT(*) FROM bookings b2 WHERE b2.trainer_id = t.id AND b2.status = 'verified') as total_clients
    FROM bookings b
    JOIN packages p ON b.package_id = p.id
    LEFT JOIN trainers t ON b.trainer_id = t.id
    WHERE b.user_id = ? AND b.status = 'verified' AND b.trainer_id IS NOT NULL
    ORDER BY b.verified_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    sendResponse(false, 'No trainer assigned yet', null, 404);
}

// Get upcoming sessions for this booking
$sessStmt = $conn->prepare("
    SELECT ts.id, ts.title, ts.session_date, ts.session_time, ts.duration, ts.type, ts.status, ts.notes
    FROM trainer_sessions ts
    WHERE ts.booking_id = ? AND ts.session_date >= CURDATE() AND ts.status = 'scheduled'
    ORDER BY ts.session_date ASC, ts.session_time ASC
    LIMIT 5
");
$sessStmt->bind_param("i", $result['booking_id']);
$sessStmt->execute();
$sessResult = $sessStmt->get_result();
$sessions = [];
while ($row = $sessResult->fetch_assoc()) {
    $sessions[] = $row;
}

// Get latest tips
$tipsStmt = $conn->prepare("
    SELECT tip_text as tip, created_at FROM trainer_tips WHERE member_id = ? ORDER BY created_at DESC LIMIT 5
");
$tipsStmt->bind_param("i", $user['id']);
$tipsStmt->execute();
$tipsResult = $tipsStmt->get_result();
$tips = [];
while ($row = $tipsResult->fetch_assoc()) {
    $tips[] = $row;
}

// Get latest food recommendations
$foodStmt = $conn->prepare("
    SELECT CONCAT(meal_type, ': ', food_items, IF(calories > 0, CONCAT(' (', calories, ' cal)'), '')) as recommendation, created_at
    FROM food_recommendations WHERE member_id = ? ORDER BY created_at DESC LIMIT 5
");
$foodStmt->bind_param("i", $user['id']);
$foodStmt->execute();
$foodResult = $foodStmt->get_result();
$food = [];
while ($row = $foodResult->fetch_assoc()) {
    $food[] = $row;
}

// Get latest progress logs
$progressStmt = $conn->prepare("
    SELECT weight as weight_kg, height, remarks, photo_url, logged_by, logged_at
    FROM member_progress
    WHERE booking_id = ?
    ORDER BY logged_at DESC
    LIMIT 6
");
$progressStmt->bind_param("i", $result['booking_id']);
$progressStmt->execute();
$progressResult = $progressStmt->get_result();
$progress = [];
while ($row = $progressResult->fetch_assoc()) {
    $progress[] = $row;
}

$stmt->close();
$sessStmt->close();
$tipsStmt->close();
$foodStmt->close();
$progressStmt->close();
$conn->close();

sendResponse(true, 'Trainer data retrieved', [
    'booking_id'      => (int)$result['booking_id'],
    'trainer_id'      => (int)$result['trainer_id'],
    'trainer_name'    => $result['trainer_name'],
    'specialization'  => $result['specialization'],
    'bio'             => $result['bio'],
    'photo_url'       => $result['photo_url'],
    'trainer_contact' => $result['trainer_contact'],
    'trainer_email'   => $result['trainer_email'],
    'availability'    => $result['availability'],
    'certifications'  => $result['certifications'],
    'total_clients'   => (int)$result['total_clients'],
    'package_name'    => $result['package_name'],
    'expires_at'      => $result['expires_at'],
    'sessions'        => $sessions,
    'tips'            => $tips,
    'food'            => $food,
    'progress'        => $progress,
]);
?>
