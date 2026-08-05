<?php
/**
 * Add student discount support to the database
 * This migration adds columns to support student discounts
 */
require_once '../../api/config.php';
require_once '../../api/session.php';

try {
    $conn = getDBConnection();

    // Add student-related columns to bookings table if they don't exist
    $checkIs_Student = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'is_student'");
    if ($checkIs_Student->num_rows === 0) {
        $conn->query("ALTER TABLE `bookings` ADD COLUMN `is_student` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_upgrade`");
        echo "✓ Added is_student column to bookings\n";
    } else {
        echo "• is_student column already exists\n";
    }

    $checkStudentIdUrl = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'student_id_url'");
    if ($checkStudentIdUrl->num_rows === 0) {
        $conn->query("ALTER TABLE `bookings` ADD COLUMN `student_id_url` VARCHAR(255) DEFAULT NULL AFTER `is_student`");
        echo "✓ Added student_id_url column to bookings\n";
    } else {
        echo "• student_id_url column already exists\n";
    }

    $checkStudentDiscount = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'student_discount_applied'");
    if ($checkStudentDiscount->num_rows === 0) {
        $conn->query("ALTER TABLE `bookings` ADD COLUMN `student_discount_applied` DECIMAL(10,2) DEFAULT NULL AFTER `student_id_url`");
        echo "✓ Added student_discount_applied column to bookings\n";
    } else {
        echo "• student_discount_applied column already exists\n";
    }

    // Add student_discount column to packages table if it doesn't exist
    $checkPackageDiscount = $conn->query("SHOW COLUMNS FROM `packages` LIKE 'student_discount'");
    if ($checkPackageDiscount->num_rows === 0) {
        $conn->query("ALTER TABLE `packages` ADD COLUMN `student_discount` DECIMAL(5,2) DEFAULT 10.00 AFTER `is_trainer_assisted`");
        echo "✓ Added student_discount column to packages (default 10% off)\n";
    } else {
        echo "• student_discount column already exists\n";
    }

    echo "\n✓ Student discount database setup completed successfully!\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    http_response_code(500);
}
?>
