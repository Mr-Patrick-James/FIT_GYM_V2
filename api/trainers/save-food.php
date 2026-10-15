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
$meal_type = $data['meal_type'] ?? '';
$food_items = trim($data['food_items'] ?? '');
$calories = (int)($data['calories'] ?? 0);
if ($member_id <= 0 || $food_items === '' || $calories < 0 || !in_array($meal_type, ['breakfast', 'lunch', 'dinner', 'snack', 'pre-workout', 'post-workout'], true)) {
    sendResponse(false, 'A member, valid meal type, food items and non-negative calories are required.', null, 400);
}

$user = getCurrentUser();
$conn = getDBConnection();
$trainerId = requireTrainerProfileId($conn, (int)$user['id']);
$booking = requireWritableTrainerBooking($conn, $booking_id, $trainerId, $member_id);
if (!$booking['is_trainer_assisted']) {
    sendResponse(false, 'This member\'s package does not include food recommendations.', null, 403);
}

$stmt = $conn->prepare('INSERT INTO food_recommendations (trainer_id, member_id, meal_type, food_items, calories) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('iissi', $trainerId, $member_id, $meal_type, $food_items, $calories);
if (!$stmt->execute()) {
    sendResponse(false, 'Failed to save food recommendation.', null, 500);
}
notifyTrainerMember($member_id, 'New Meal Plan Recommendation', 'Coach ' . $user['name'] . " updated your $meal_type recommendation.", 'food');
sendResponse(true, 'Food recommendation saved');
