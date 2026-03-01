<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$equipment_id = (isset($_POST['equipment_id']) && $_POST['equipment_id'] !== '') ? (int)$_POST['equipment_id'] : null;
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$instructions = isset($_POST['instructions']) ? trim($_POST['instructions']) : '';
$image_url = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';

if ($id <= 0 || empty($name)) {
    sendResponse(false, 'ID and name are required', null, 400);
}

$conn = getDBConnection();

$stmt = $conn->prepare("UPDATE exercises SET name = ?, category = ?, equipment_id = ?, description = ?, instructions = ?, image_url = ? WHERE id = ?");
$stmt->bind_param("ssisssi", $name, $category, $equipment_id, $description, $instructions, $image_url, $id);

if ($stmt->execute()) {
    sendResponse(true, 'Exercise updated successfully');
} else {
    sendResponse(false, 'Failed to update exercise: ' . $conn->error);
}

$stmt->close();
$conn->close();
?>
