<?php
// Ensure no output before headers
ob_start();
require_once '../config.php';
require_once '../session.php';

// Clean any accidental output from config/session
if (ob_get_length()) ob_clean();

// Allow Admin, Manager, or Trainer
if (!isAdmin() && !isManager() && !isTrainer()) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, name, category, description, image_url FROM equipment ORDER BY name ASC");
$stmt->execute();
$result = $stmt->get_result();

$equipment = [];
while ($row = $result->fetch_assoc()) {
    $equipment[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'category' => $row['category'],
        'description' => $row['description'],
        'image_url' => $row['image_url']
    ];
}

$stmt->close();
$conn->close();

sendResponse(true, 'Equipment retrieved successfully', $equipment);
?>
