<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$id = $_POST['id'] ?? null;
$name = $_POST['name'] ?? '';
$category = $_POST['category'] ?? 'General';
$description = $_POST['description'] ?? '';
$status = $_POST['status'] ?? 'active';

if (empty($id) || empty($name)) {
    sendResponse(false, 'Equipment ID and name are required');
}

$conn = getDBConnection();

// Check if current image URL exists
$stmt_check = $conn->prepare("SELECT image_url FROM equipment WHERE id = ?");
$stmt_check->bind_param("i", $id);
$stmt_check->execute();
$current_image = $stmt_check->get_result()->fetch_assoc()['image_url'] ?? '';

// Handle image upload if provided
$image_url = $current_image;
if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $upload_dir = '../../uploads/equipment/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    // Delete old image if it exists
    if (!empty($current_image) && file_exists('../../' . $current_image)) {
        unlink('../../' . $current_image);
    }
    
    $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . $file_ext;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
        $image_url = 'uploads/equipment/' . $file_name;
    }
}

$stmt = $conn->prepare("UPDATE equipment SET name = ?, category = ?, description = ?, status = ?, image_url = ? WHERE id = ?");
$stmt->bind_param("sssssi", $name, $category, $description, $status, $image_url, $id);

if ($stmt->execute()) {
    sendResponse(true, 'Equipment updated successfully');
} else {
    sendResponse(false, 'Failed to update equipment: ' . $conn->error);
}

$stmt->close();
$conn->close();
?>
