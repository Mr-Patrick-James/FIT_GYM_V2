<?php
require_once '../config.php';
require_once '../session.php';
requireTrainer();

$user = getCurrentUser();
$conn = getDBConnection();

// Get the trainer ID for the logged-in user
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainerResult = $trainerStmt->get_result();
$trainer = $trainerResult->fetch_assoc();

if (!$trainer) {
    sendResponse(false, 'Trainer record not found', null, 404);
}

$trainerId = $trainer['id'];

// Get all verified bookings assigned to this trainer
$sql = "SELECT b.id as booking_id, u.id as user_id, u.name, u.email, u.contact, u.address, u.created_at, 
               p.name as package_name, p.duration, p.is_trainer_assisted, b.expires_at, b.status, b.verified_at
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN packages p ON b.package_id = p.id 
        WHERE b.trainer_id = ? AND b.status = 'verified'
        ORDER BY COALESCE(b.verified_at, b.created_at) DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $trainerId);
$stmt->execute();
$result = $stmt->get_result();

$clients = [];
while ($row = $result->fetch_assoc()) {
    $isExpired = $row['expires_at'] ? strtotime($row['expires_at']) < time() : false;
    $row['is_expired'] = $isExpired;
    $clients[] = $row;
}

$conn->close();
sendResponse(true, 'Clients retrieved successfully', $clients);
?>
