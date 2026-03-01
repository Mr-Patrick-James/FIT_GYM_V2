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

// Handle Image Upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../assets/uploads/exercises/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $file_name = uniqid('ex_') . '.' . $file_extension;
    $target_file = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
        $image_url = '../../assets/uploads/exercises/' . $file_name;
    }
}

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
