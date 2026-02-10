<?php
/**
 * Script to check for bookings expiring soon and notify users via email.
 * This can be set up as a daily cron job.
 * 
 * To run from CLI: C:\wamp64\bin\php\php8.2.18\php.exe api\bookings\check-expiry.php
 */

// Check for mysqli extension
if (!extension_loaded('mysqli')) {
    die("Error: mysqli extension is not loaded. If running from CLI, please use the WAMP PHP executable or enable mysqli in your php.ini.\n");
}

// Use absolute path for safety if run from CLI
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../email.php';

echo "Checking for expiring bookings...\n";
$count = processExpiringBookings();

if ($count === false) {
    echo "An error occurred while checking for expiring bookings.\n";
} else {
    echo "Successfully sent $count expiry notification(s).\n";
}
?>
