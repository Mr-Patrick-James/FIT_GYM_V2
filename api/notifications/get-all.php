<?php
require_once '../config.php';
require_once '../session.php';
requireLogin();

$user = getCurrentUser();
$userId = $user['id'];

$conn = getDBConnection();

$query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['is_read'] = (bool)$row['is_read'];
    $notifications[] = $row;
}

$stmt->close();
$conn->close();

sendResponse(true, 'Notifications retrieved successfully', $notifications);
?>
