<?php
// One-time migration: add height and ensure weight columns exist in member_progress
require_once '../config.php';
$conn = getDBConnection();
$messages = [];

// Add height column if missing
$r = $conn->query("SHOW COLUMNS FROM member_progress LIKE 'height'");
if ($r->num_rows === 0) {
    $conn->query("ALTER TABLE member_progress ADD COLUMN height DECIMAL(5,1) NULL AFTER weight");
    $messages[] = "height column added.";
} else {
    $messages[] = "height column already exists.";
}

// Ensure weight allows NULL (in case it was NOT NULL before)
$conn->query("ALTER TABLE member_progress MODIFY COLUMN weight DECIMAL(5,1) NULL");
$messages[] = "weight column verified.";

$conn->close();
echo implode('<br>', $messages);
?>
