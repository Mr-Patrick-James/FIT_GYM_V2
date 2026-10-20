<?php
require_once '../config.php';
require_once '../session.php';

if (!isLoggedIn()) {
    sendResponse(false, 'Unauthorized', null, 401);
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT plan_json, generated_at FROM generated_workout_plans WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    sendResponse(true, 'Plan found', [
        'plan' => json_decode($row['plan_json'], true),
        'generated_at' => $row['generated_at']
    ]);
}

sendResponse(false, 'No plan generated yet', null, 404);
