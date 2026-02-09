<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$conn = getDBConnection();

// Get all active packages
$stmt = $conn->prepare("SELECT id, name, duration, price, tag, description, is_active, created_at, updated_at FROM packages WHERE is_active = TRUE ORDER BY name ASC");
$stmt->execute();
$result = $stmt->get_result();

$packages = [];
while ($row = $result->fetch_assoc()) {
    $packages[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'duration' => $row['duration'],
        'price' => (float)$row['price'],
        'tag' => $row['tag'],
        'description' => $row['description'],
        'is_active' => (bool)$row['is_active'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

$stmt->close();
$conn->close();

sendResponse(true, 'Packages retrieved successfully', $packages);
?>