<?php
require_once '../config.php';
require_once '../session.php';
require_once __DIR__ . '/booking-access.php';

// Set error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Custom error handler to return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    sendResponse(false, "PHP Error: [$errno] $errstr in $errfile on line $errline", null, 500);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        sendResponse(false, "PHP Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}", null, 500);
    }
});

requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $data = getRequestData();
    $booking_id = (int)($data['booking_id'] ?? 0);
    $member_id = (int)($data['member_id'] ?? 0);
    $session_date = $data['session_date'] ?? '';
    $session_time = !empty($data['session_time']) ? $data['session_time'] : '08:00:00';
    
    if (!trainerValidCalendarDate($session_date)) {
        sendResponse(false, 'A valid session date is required.', null, 400);
    }

    // Ensure HH:MM:SS format
    if (strlen($session_time) === 5) {
        $session_time .= ':00';
    }
    
    $duration = (int)($data['duration'] ?? 60);
    $type = $data['type'] ?? 'workout';
    $title = !empty($data['title']) ? $data['title'] : ($type === 'rest_day' ? 'Rest Day' : 'Workout Session');
    $notes = $data['notes'] ?? '';
    $exercises = isset($data['exercises']) ? (is_array($data['exercises']) ? implode(',', $data['exercises']) : $data['exercises']) : '';

    $user = getCurrentUser();
    $conn = getDBConnection();

    $trainerId = requireTrainerProfileId($conn, (int)$user['id']);
    $booking = requireWritableTrainerBooking($conn, $booking_id, $trainerId, $member_id);
    if (!$booking['is_trainer_assisted']) {
        sendResponse(false, 'This package does not include trainer-managed sessions.', null, 403);
    }
    $gymNow = trainerGymNow();
    $expired = $booking['status'] === 'expired' || ($booking['expires_at'] !== null && $booking['expires_at'] < $gymNow->format('Y-m-d H:i:s'));
    if ($expired && $session_date !== $gymNow->format('Y-m-d')) {
        sendResponse(false, 'An expired same-day booking can only schedule an event on its gym date.', null, 403);
    }

    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'trainer_sessions'");
    if ($tableCheck->num_rows == 0) {
        sendResponse(false, 'Database table "trainer_sessions" is missing. Please run migration.');
    }

    // Try to insert with exercises first
    $stmt = $conn->prepare("INSERT INTO trainer_sessions (trainer_id, member_id, booking_id, session_date, session_time, duration, type, title, notes, exercises) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        // Fallback if exercises column is missing
        $stmt = $conn->prepare("INSERT INTO trainer_sessions (trainer_id, member_id, booking_id, session_date, session_time, duration, type, title, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            sendResponse(false, 'Failed to prepare statement: ' . $conn->error);
        }
        $stmt->bind_param("iiississs", $trainerId, $member_id, $booking_id, $session_date, $session_time, $duration, $type, $title, $notes);
    } else {
        $stmt->bind_param("iiississss", $trainerId, $member_id, $booking_id, $session_date, $session_time, $duration, $type, $title, $notes, $exercises);
    }

    if ($stmt->execute()) {
        // Notify member
        try {
            $notifTitle = $type === 'rest_day' ? 'Rest Day Scheduled' : 'New Workout Session Scheduled';
            $notifMsg = $type === 'rest_day' ? "Coach " . $user['name'] . " scheduled a rest day for you on $session_date." : "Coach " . $user['name'] . " scheduled a session for you on $session_date at $session_time.";
            createNotification($member_id, $notifTitle, $notifMsg, 'session');
        } catch (Exception $e) {
            // Log but don't fail the session creation
            error_log("Notification failed: " . $e->getMessage());
        }
        
        sendResponse(true, 'Session scheduled successfully');
    } else {
        sendResponse(false, 'Failed to execute statement: ' . $stmt->error);
    }
} catch (Exception $e) {
    sendResponse(false, 'Server Exception: ' . $e->getMessage(), null, 500);
}
?>
