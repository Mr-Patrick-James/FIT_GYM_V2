<?php
/**
 * Migration script to set up student discount support
 * Run this once to add the necessary database columns
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['allow_web'])) {
    die("This script should be run from the command line or accessed with ?allow_web=1 parameter");
}

require_once __DIR__ . '/api/config.php';

echo "Starting student discount database migration...\n\n";

try {
    $conn = getDBConnection();

    // 1. Add is_student column to bookings
    echo "1. Checking is_student column...";
    $result = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'is_student'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE `bookings` ADD COLUMN `is_student` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_upgrade`");
        echo " ✓ Added\n";
    } else {
        echo " ✓ Already exists\n";
    }

    // 2. Add student_id_url column to bookings
    echo "2. Checking student_id_url column...";
    $result = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'student_id_url'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE `bookings` ADD COLUMN `student_id_url` VARCHAR(255) DEFAULT NULL AFTER `is_student`");
        echo " ✓ Added\n";
    } else {
        echo " ✓ Already exists\n";
    }

    // 3. Add student_discount_applied column to bookings
    echo "3. Checking student_discount_applied column...";
    $result = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'student_discount_applied'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE `bookings` ADD COLUMN `student_discount_applied` DECIMAL(10,2) DEFAULT NULL AFTER `student_id_url`");
        echo " ✓ Added\n";
    } else {
        echo " ✓ Already exists\n";
    }

    // 4. Add student_discount column to packages
    echo "4. Checking student_discount column in packages...";
    $result = $conn->query("SHOW COLUMNS FROM `packages` LIKE 'student_discount'");
    if ($result->num_rows === 0) {
        $conn->query("ALTER TABLE `packages` ADD COLUMN `student_discount` DECIMAL(5,2) DEFAULT 10.00 AFTER `is_trainer_assisted`");
        echo " ✓ Added (default 10% off)\n";
    } else {
        echo " ✓ Already exists\n";
    }

    echo "\n✓ All migrations completed successfully!\n";
    echo "\nStudent Discount Features Enabled:\n";
    echo "- Students can now apply for discounts on bookings\n";
    echo "- Default discount: 10% off all packages\n";
    echo "- Customize discount per package in admin settings\n";
    echo "- Student ID verification required for discount\n";

} catch (Exception $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>
