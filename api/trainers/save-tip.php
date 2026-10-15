<?php
require_once '../config.php';
require_once '../session.php';
require_once __DIR__ . '/booking-access.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$booking_id = (int)($data['booking_id'] ?? 0);
$member_id = (int)($data['member_id'] ?? 0);
$tip_text = trim($data['tip_text'] ?? '');
$tip_id = (int)($data['tip_id'] ?? 0);
if ($member_id <= 0 || $tip_text === '') {
    sendResponse(false, 'Member ID and tip text are required.', null, 400);
}

$user = getCurrentUser();
$conn = getDBConnection();
$trainerId = requireTrainerProfileId($conn, (int)$user['id']);

// Keep editing a trainer's own existing tips available, including historical tips.
if ($tip_id > 0) {
    $check = $conn->prepare('SELECT id FROM trainer_tips WHERE id = ? AND trainer_id = ? AND member_id = ?');
    $check->bind_param('iii', $tip_id, $trainerId, $member_id);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        sendResponse(false, 'Tip not found or not authorised.', null, 403);
    }
    $stmt = $conn->prepare('UPDATE trainer_tips SET tip_text = ? WHERE id = ? AND trainer_id = ? AND member_id = ?');
    $stmt->bind_param('siii', $tip_text, $tip_id, $trainerId, $member_id);
    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to update tip.', null, 500);
    }
    // An unchanged tip is still a successful save.
    sendResponse(true, 'Tip updated');
}

$booking = requireWritableTrainerBooking($conn, $booking_id, $trainerId, $member_id);
if (!$booking['is_trainer_assisted']) {
    sendResponse(false, 'This member\'s package does not include personal trainer tips.', null, 403);
}

$stmt = $conn->prepare('INSERT INTO trainer_tips (trainer_id, member_id, tip_text) VALUES (?, ?, ?)');
$stmt->bind_param('iis', $trainerId, $member_id, $tip_text);
if (!$stmt->execute()) {
    sendResponse(false, 'Failed to save tip.', null, 500);
}
notifyTrainerMember($member_id, 'New Fitness Tip', 'Coach ' . $user['name'] . ' shared a new tip: ' . substr($tip_text, 0, 50) . '...', 'tip');
sendResponse(true, 'Tip saved');
