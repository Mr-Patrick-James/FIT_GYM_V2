<?php
require_once '../config.php';
require_once '../session.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$member_id = (int)$data['member_id'];
$tip_text = $data['tip_text'];

$user = getCurrentUser();
$conn = getDBConnection();

// Get trainer ID
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainerId = $trainerStmt->get_result()->fetch_assoc()['id'];

// Check if trainer is assigned to this member's active booking AND if package allows tips
$checkStmt = $conn->prepare("
    SELECT b.id, p.is_trainer_assisted 
    FROM bookings b 
    JOIN packages p ON b.package_id = p.id 
    WHERE b.user_id = ? AND b.trainer_id = ? AND b.status = 'verified' AND b.expires_at > NOW()
    ORDER BY b.verified_at DESC LIMIT 1
");
$checkStmt->bind_param("ii", $member_id, $trainerId);
$checkStmt->execute();
$booking = $checkStmt->get_result()->fetch_assoc();

if (!$booking) {
    sendResponse(false, 'You are not assigned to this member or they have no active booking.');
}

if (!$booking['is_trainer_assisted']) {
    sendResponse(false, 'This member\'s package does not include personal trainer tips.');
}

$stmt = $conn->prepare("INSERT INTO trainer_tips (trainer_id, member_id, tip_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $trainerId, $member_id, $tip_text);

if ($stmt->execute()) {
    createNotification($member_id, 'New Fitness Tip', "Coach " . $user['name'] . " shared a new tip: " . substr($tip_text, 0, 50) . "...", 'tip');
    sendResponse(true, 'Tip saved');
} else {
    sendResponse(false, 'Failed to save tip: ' . $stmt->error);
}
?>
