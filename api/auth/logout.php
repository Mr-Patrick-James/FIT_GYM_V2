<?php
/**
 * Logout endpoint
 * Clears PHP session and returns success
 */
require_once '../config.php';
require_once '../session.php';

// Clear user session
clearUserSession();

// Also clear session cookie explicitly
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Return success response or redirect if called via GET
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    sendResponse(true, 'Logged out successfully', null, 200);
} else {
    // Regular GET request, redirect to home/login
    header('Location: ../../index.php');
    exit;
}

?>
