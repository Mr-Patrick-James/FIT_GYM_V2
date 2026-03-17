<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

$trainer_id = isset($_GET['trainer_id']) ? (int)$_GET['trainer_id'] : 0;

if ($trainer_id <= 0) {
    sendResponse(false, 'Trainer ID is required', null, 400);
}

$conn = getDBConnection();

// Get all verified bookings assigned to this trainer
$sql = "SELECT b.id as booking_id, u.id as user_id, u.name, u.email, u.contact, u.address, 
               p.name as package_name, p.duration, p.is_trainer_assisted, b.expires_at, b.status,
               (SELECT COUNT(*) FROM member_progress mp WHERE mp.booking_id = b.id) as log_count,
               (SELECT weight FROM member_progress mp WHERE mp.booking_id = b.id ORDER BY logged_at DESC, created_at DESC LIMIT 1) as latest_weight,
               (SELECT weight FROM member_progress mp WHERE mp.booking_id = b.id ORDER BY logged_at ASC, created_at ASC LIMIT 1) as starting_weight
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN packages p ON b.package_id = p.id 
        WHERE b.trainer_id = ? AND b.status = 'verified'
        ORDER BY b.expires_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $trainer_id);
$stmt->execute();
$result = $stmt->get_result();

$clients = [];
while ($row = $result->fetch_assoc()) {
    $isExpired = $row['expires_at'] ? strtotime($row['expires_at']) < time() : false;
    $row['is_expired'] = $isExpired;
    
    // Calculate progress percentage (simple heuristic: 10 logs = 100% for demonstration or just based on duration)
    // For now, let's just return the raw data and handle UI in JS
    $clients[] = $row;
}

$conn->close();
sendResponse(true, 'Trainer clients retrieved successfully', $clients);
?>