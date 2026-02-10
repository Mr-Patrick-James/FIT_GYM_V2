<?php
require_once '../config.php';
require_once '../session.php';

// Only admins can clear data
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    
    // Disable foreign key checks temporarily to clear all tables
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // List of tables to clear (excluding gym_settings and users with role 'admin')
    $tables = ['bookings', 'payments', 'notifications'];
    
    foreach ($tables as $table) {
        $conn->query("TRUNCATE TABLE $table");
    }
    
    // Delete non-admin users
    $conn->query("DELETE FROM users WHERE role != 'admin'");
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    sendResponse(true, 'All gym data has been cleared successfully');

} catch (Exception $e) {
    sendResponse(false, 'Error clearing data: ' . $e->getMessage(), null, 500);
}
?>