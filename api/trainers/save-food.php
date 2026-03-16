<?php
require_once '../config.php';
require_once '../session.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$member_id = (int)$data['member_id'];
$meal_type = $data['meal_type'];
$food_items = $data['food_items'];
$calories = (int)$data['calories'];

$user = getCurrentUser();
$conn = getDBConnection();

// Get trainer ID
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainerId = $trainerStmt->get_result()->fetch_assoc()['id'];

// Check if trainer is assigned to this member's active booking AND if package allows food recommendations
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
    sendResponse(false, 'This member\'s package does not include food recommendations.');
}

$stmt = $conn->prepare("INSERT INTO food_recommendations (trainer_id, member_id, meal_type, food_items, calories) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iissi", $trainerId, $member_id, $meal_type, $food_items, $calories);

if ($stmt->execute()) {
    createNotification($member_id, 'New Meal Plan Recommendation', "Coach " . $user['name'] . " updated your $meal_type recommendation.", 'food');
    sendResponse(true, 'Food recommendation saved');
} else {
    sendResponse(false, 'Failed to save food recommendation: ' . $stmt->error);
}
?>
