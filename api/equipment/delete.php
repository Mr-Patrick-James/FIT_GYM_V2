<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$id = $_POST['id'] ?? null;

if (empty($id)) {
    sendResponse(false, 'Equipment ID is required');
}

$conn = getDBConnection();

// Check if current image URL exists to delete it
$stmt_check = $conn->prepare("SELECT image_url FROM equipment WHERE id = ?");
$stmt_check->bind_param("i", $id);
$stmt_check->execute();
$current_image = $stmt_check->get_result()->fetch_assoc()['image_url'] ?? '';

// Delete old image if it exists
if (!empty($current_image) && file_exists('../../' . $current_image)) {
    unlink('../../' . $current_image);
}

$stmt = $conn->prepare("DELETE FROM equipment WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    sendResponse(true, 'Equipment deleted successfully');
} else {
    sendResponse(false, 'Failed to delete equipment: ' . $conn->error);
}

$stmt->close();
$conn->close();
?>
