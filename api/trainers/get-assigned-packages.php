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

// Get all packages linked to this trainer
$query = "
    SELECT p.* 
    FROM packages p
    JOIN package_trainers pt ON p.id = pt.package_id
    WHERE pt.trainer_id = ? AND p.is_active = TRUE
    ORDER BY p.name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $trainerId);
$stmt->execute();
$result = $stmt->get_result();

$packages = [];
while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['price'] = (float)$row['price'];
    $row['is_trainer_assisted'] = (bool)$row['is_trainer_assisted'];
    $packages[] = $row;
}

$stmt->close();
$conn->close();

sendResponse(true, 'Assigned packages retrieved successfully', $packages);
?>
