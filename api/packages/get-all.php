<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$conn = getDBConnection();

// Get all active packages
$stmt = $conn->prepare("SELECT id, name, duration, price, tag, description, is_trainer_assisted, is_active, created_at, updated_at FROM packages WHERE is_active = TRUE ORDER BY name ASC");
$stmt->execute();
$result = $stmt->get_result();

$packages = [];
while ($row = $result->fetch_assoc()) {
    $packageId = (int)$row['id'];
    
    // Fetch linked trainers for this package
    $trainerIds = [];
    $trainerStmt = $conn->prepare("SELECT trainer_id FROM package_trainers WHERE package_id = ?");
    $trainerStmt->bind_param("i", $packageId);
    $trainerStmt->execute();
    $trainerResult = $trainerStmt->get_result();
    while ($tRow = $trainerResult->fetch_assoc()) {
        $trainerIds[] = (int)$tRow['trainer_id'];
    }
    $trainerStmt->close();

    $packages[] = [
        'id' => $packageId,
        'name' => $row['name'],
        'duration' => $row['duration'],
        'price' => (float)$row['price'],
        'tag' => $row['tag'],
        'description' => $row['description'],
        'is_trainer_assisted' => (bool)$row['is_trainer_assisted'],
        'trainer_ids' => $trainerIds,
        'is_active' => (bool)$row['is_active'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

$stmt->close();
$conn->close();

sendResponse(true, 'Packages retrieved successfully', $packages);
?>