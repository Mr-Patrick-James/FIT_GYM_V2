<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$name = $_POST['name'] ?? '';
$category = $_POST['category'] ?? 'General';
$description = $_POST['description'] ?? '';
$status = $_POST['status'] ?? 'active';

if (empty($name)) {
    sendResponse(false, 'Equipment name is required');
}

$conn = getDBConnection();

// Handle image upload if provided
$image_url = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $upload_dir = '../../uploads/equipment/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . $file_ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
        $image_url = 'uploads/equipment/' . $file_name;
    }
}

$stmt = $conn->prepare("INSERT INTO equipment (name, category, description, status, image_url) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $name, $category, $description, $status, $image_url);

if ($stmt->execute()) {
    sendResponse(true, 'Equipment added successfully', ['id' => $stmt->insert_id]);
} else {
    sendResponse(false, 'Failed to add equipment: ' . $conn->error);
}

$stmt->close();
$conn->close();
?>
