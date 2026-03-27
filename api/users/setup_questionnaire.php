<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

// Check if admin or logged in user (skip for CLI)
if (php_sapi_name() !== 'cli' && !isLoggedIn()) {
    sendResponse(false, 'Unauthorized access');
}

$conn = getDBConnection();

$sql = "CREATE TABLE IF NOT EXISTS `user_questionnaire` (
    `id` int NOT NULL AUTO_INCREMENT,
    `user_id` int NOT NULL,
    
    -- Basic Profile
    `age` int DEFAULT NULL,
    `sex` enum('Male', 'Female', 'Other') DEFAULT NULL,
    `height` decimal(5,2) DEFAULT NULL,
    `weight` decimal(5,2) DEFAULT NULL,
    `medical_conditions` text,
    `exercise_history` enum('Beginner', 'Intermediate', 'Advanced') DEFAULT 'Beginner',
    
    -- Fitness Goals
    `primary_goal` enum('Lose weight', 'Gain muscle', 'Improve endurance', 'Stay fit / general health') DEFAULT 'Stay fit / general health',
    `goal_pace` enum('Slowly', 'Moderately', 'Intensively') DEFAULT 'Moderately',
    
    -- Availability & Commitment
    `workout_days_per_week` enum('1-2 days', '3-4 days', '5+ days') DEFAULT '1-2 days',
    `preferred_workout_time` enum('Morning', 'Afternoon', 'Evening') DEFAULT 'Morning',
    
    -- Physical Condition & Limitations
    `injuries_limitations` text,
    `focus_areas` varchar(255) DEFAULT 'Full body',
    
    -- Workout Preference
    `workout_type` enum('Cardio', 'Strength training', 'Mixed') DEFAULT 'Mixed',
    `trainer_guidance` enum('With trainer guidance', 'Independent workout') DEFAULT 'Independent workout',
    
    -- Experience & Confidence
    `equipment_confidence` enum('Not confident', 'Somewhat confident', 'Very') DEFAULT 'Not confident',
    
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user` (`user_id`),
    CONSTRAINT `fk_questionnaire_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

if ($conn->query($sql)) {
    sendResponse(true, 'user_questionnaire table created successfully or already exists');
} else {
    sendResponse(false, 'Error creating table: ' . $conn->error);
}

$conn->close();
?>
