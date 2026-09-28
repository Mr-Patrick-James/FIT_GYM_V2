<?php
// Ensure no output before headers
ob_start();
require_once '../config.php';
require_once '../session.php';
requireTrainer();

// Clean any accidental output from config/session
if (ob_get_length()) ob_clean();

$user = getCurrentUser();
error_log("DEBUG: Current User Data: " . print_r($user, true));
$conn = getDBConnection();

// Get the trainer ID for the logged-in user
error_log("DEBUG: Searching trainers table for user_id: " . $user['id']);
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainerResult = $trainerStmt->get_result();
$trainer = $trainerResult->fetch_assoc();

if (!$trainer) {
    error_log("DEBUG: Trainer record not found for user_id: " . $user['id']);
    // Check if the trainers table even has this user
    $checkRes = $conn->query("SELECT * FROM trainers WHERE user_id = " . (int)$user['id']);
    if ($checkRes) {
        error_log("DEBUG: Direct check for user_id " . $user['id'] . " found " . $checkRes->num_rows . " rows");
    } else {
        error_log("DEBUG: Direct check query failed: " . $conn->error);
    }
    sendResponse(false, 'Trainer record not found. Please ensure your account is assigned as a trainer.', null, 404);
}

$trainerId = $trainer['id'];
error_log("DEBUG: Fetching clients for trainer_id: $trainerId");

// Robustly handle user_questionnaire table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `user_questionnaire` (
    `id` int NOT NULL AUTO_INCREMENT,
    `user_id` int NOT NULL,
    `age` int DEFAULT NULL,
    `sex` enum('Male', 'Female', 'Other') DEFAULT NULL,
    `height` decimal(5,2) DEFAULT NULL,
    `weight` decimal(5,2) DEFAULT NULL,
    `medical_conditions` text,
    `exercise_history` enum('Beginner', 'Intermediate', 'Advanced') DEFAULT 'Beginner',
    `primary_goal` enum('Lose weight', 'Gain muscle', 'Improve endurance', 'Stay fit / general health') DEFAULT 'Stay fit / general health',
    `goal_pace` enum('Slowly', 'Moderately', 'Intensively') DEFAULT 'Moderately',
    `workout_days_per_week` enum('1-2 days', '3-4 days', '5+ days') DEFAULT '1-2 days',
    `preferred_workout_time` enum('Morning', 'Afternoon', 'Evening') DEFAULT 'Morning',
    `injuries_limitations` text,
    `focus_areas` varchar(255) DEFAULT 'Full body',
    `workout_type` enum('Cardio', 'Strength training', 'Mixed') DEFAULT 'Mixed',
    `trainer_guidance` enum('With trainer guidance', 'Independent workout') DEFAULT 'Independent workout',
    `equipment_confidence` enum('Not confident', 'Somewhat confident', 'Very') DEFAULT 'Not confident',
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");

// Get all verified bookings assigned to this trainer (including walk-ins)
$sql = "SELECT b.id as booking_id, 
               COALESCE(u.id, 0) as user_id, 
               COALESCE(u.name, b.name) as name, 
               COALESCE(u.email, b.email) as email, 
               COALESCE(u.contact, b.contact) as contact, 
               COALESCE(u.address, 'Walk-in Customer') as address, 
               COALESCE(u.created_at, b.created_at) as created_at,
               p.name as package_name, p.duration, p.is_trainer_assisted, b.expires_at, b.status, b.verified_at,
               uq.primary_goal, uq.exercise_history, uq.workout_days_per_week,
               CASE WHEN b.user_id IS NULL THEN 1 ELSE 0 END as is_walkin
        FROM bookings b 
        LEFT JOIN users u ON b.user_id = u.id 
        JOIN packages p ON b.package_id = p.id 
        LEFT JOIN user_questionnaire uq ON u.id = uq.user_id
        WHERE b.trainer_id = ? AND b.status = 'verified'
        ORDER BY COALESCE(b.verified_at, b.created_at) DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("DEBUG: SQL Prepare failed: " . $conn->error);
    sendResponse(false, 'Database query preparation failed', null, 500);
}

$stmt->bind_param("i", $trainerId);
if (!$stmt->execute()) {
    error_log("DEBUG: SQL Execute failed: " . $stmt->error);
    sendResponse(false, 'Database query execution failed', null, 500);
}

$result = $stmt->get_result();
error_log("DEBUG: Found " . $result->num_rows . " clients");

$clients = [];
while ($row = $result->fetch_assoc()) {
    $isExpired = $row['expires_at'] ? strtotime($row['expires_at']) < time() : false;
    $row['is_expired'] = $isExpired;
    $row['is_walkin'] = (bool)$row['is_walkin'];
    
    // Add warning messages for trainer
    if ($isExpired) {
        $row['status_warning'] = 'Membership expired - plans can still be added but member may not have access';
    } else if ($row['is_walkin']) {
        $row['status_warning'] = 'Walk-in customer - has limited access to online features';
    }
    
    $clients[] = $row;
}

$conn->close();
error_log("DEBUG: Returning " . count($clients) . " clients");
sendResponse(true, 'Clients retrieved successfully', $clients);
?>
