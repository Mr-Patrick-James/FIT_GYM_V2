<?php
require_once '../config.php';
require_once '../session.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$member_id = (int)$data['member_id'];
$tip_text  = trim($data['tip_text'] ?? '');
$tip_id    = (int)($data['tip_id'] ?? 0); // present on edit

$user = getCurrentUser();
$conn = getDBConnection();

// Get trainer ID
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainerId = $trainerStmt->get_result()->fetch_assoc()['id'];

// If tip_id is set → UPDATE existing tip
if ($tip_id > 0) {
    if (!$tip_text) sendResponse(false, 'Tip text required');
    $stmt = $conn->prepare("UPDATE trainer_tips SET tip_text = ? WHERE id = ? AND trainer_id = ?");
    $stmt->bind_param("sii", $tip_text, $tip_id, $trainerId);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        sendResponse(true, 'Tip updated');
    } else {
        sendResponse(false, 'Tip not found or not authorised');
    }
}

$user = getCurrentUser();
$conn = getDBConnection();

// Get trainer ID
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainerId = $trainerStmt->get_result()->fetch_assoc()['id'];

// Allow trainer to save for verified bookings OR expired bookings where booking_date is today (same-day daily pass)
$checkStmt = $conn->prepare("
    SELECT b.id, p.is_trainer_assisted 
    FROM bookings b 
    JOIN packages p ON b.package_id = p.id 
    WHERE b.user_id = ? AND b.trainer_id = ?
      AND (b.status = 'verified' OR (b.status = 'expired' AND DATE(b.booking_date) = CURDATE()))
    ORDER BY b.verified_at DESC LIMIT 1
");
$checkStmt->bind_param("ii", $member_id, $trainerId);
$checkStmt->execute();
$booking = $checkStmt->get_result()->fetch_assoc();

if (!$booking) {
    sendResponse(false, 'You are not assigned to this member or they have no active/same-day booking.');
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
