<?php
require_once '../config.php';

try {
    $conn = getDBConnection();

    // Add is_student column
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS is_student TINYINT(1) NOT NULL DEFAULT 0 AFTER notes");

    // Add student_id_url column
    $conn->query("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS student_id_url VARCHAR(500) DEFAULT NULL AFTER is_student");

    echo json_encode(['success' => true, 'message' => 'Migration complete: is_student and student_id_url columns added to bookings table.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
