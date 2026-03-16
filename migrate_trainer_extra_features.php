<?php
require_once 'api/config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Migrating Database for Trainer Features</h2>";

$conn = getDBConnection();

// 1. Create notifications table
$sqlNotifications = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
)";
if ($conn->query($sqlNotifications) === TRUE) echo "<p>✅ notifications table created</p>";
else echo "<p>❌ Error creating notifications table: " . $conn->error . "</p>";

// 2. Create trainer_sessions table
$sqlSessions = "
CREATE TABLE IF NOT EXISTS trainer_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    booking_id INT NOT NULL,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    duration INT DEFAULT 60, -- minutes
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
)";
if ($conn->query($sqlSessions) === TRUE) echo "<p>✅ trainer_sessions table created</p>";
else echo "<p>❌ Error creating trainer_sessions table: " . $conn->error . "</p>";

// 3. Create trainer_tips table
$sqlTips = "
CREATE TABLE IF NOT EXISTS trainer_tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    tip_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sqlTips) === TRUE) echo "<p>✅ trainer_tips table created</p>";
else echo "<p>❌ Error creating trainer_tips table: " . $conn->error . "</p>";

// 4. Create food_recommendations table
$sqlFood = "
CREATE TABLE IF NOT EXISTS food_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack', 'pre-workout', 'post-workout') NOT NULL,
    food_items TEXT NOT NULL,
    calories INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sqlFood) === TRUE) echo "<p>✅ food_recommendations table created</p>";
else echo "<p>❌ Error creating food_recommendations table: " . $conn->error . "</p>";

$conn->close();
echo "<p>Migration complete!</p>";
?>
