<?php
require_once '../config.php';
require_once '../session.php';

// Only admins can reset settings
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    
    // Clear the gym_settings table
    $conn->query("TRUNCATE TABLE gym_settings");
    
    // The get.php API automatically re-seeds defaults if the table is empty
    // So we don't need to do anything else here. The next loadSettings() call 
    // to get.php will trigger the re-seeding.
    
    sendResponse(true, 'Settings have been reset to default values');

} catch (Exception $e) {
    sendResponse(false, 'Error resetting settings: ' . $e->getMessage(), null, 500);
}
?>