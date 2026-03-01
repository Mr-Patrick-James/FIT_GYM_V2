<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : 'Full Body';
$equipment_id = (isset($_POST['equipment_id']) && $_POST['equipment_id'] !== '') ? (int)$_POST['equipment_id'] : null;
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$instructions = isset($_POST['instructions']) ? trim($_POST['instructions']) : '';
$image_url = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';

if (empty($name)) {
    sendResponse(false, 'Exercise name is required', null, 400);
}

$conn = getDBConnection();

$stmt = $conn->prepare("INSERT INTO exercises (name, category, equipment_id, description, instructions, image_url) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssisss", $name, $category, $equipment_id, $description, $instructions, $image_url);

if ($stmt->execute()) {
    sendResponse(true, 'Exercise created successfully', ['id' => $stmt->insert_id]);
} else {
    sendResponse(false, 'Failed to create exercise: ' . $conn->error);
}

$stmt->close();
$conn->close();
?>
