<?php
require_once '../config.php';
require_once '../session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$notificationId = isset($data['id']) ? (int)$data['id'] : 0;
$user = getCurrentUser();
$userId = $user['id'];

$conn = getDBConnection();

if ($notificationId > 0) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $userId);
} else {
    // Mark all as read
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
}

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    sendResponse(true, 'Notifications updated');
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendResponse(false, 'Failed to update notifications: ' . $error);
}
?>
