<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    sendResponse(false, 'Valid ID is required', null, 400);
}

$conn = getDBConnection();

$stmt = $conn->prepare("DELETE FROM exercises WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    sendResponse(true, 'Exercise deleted successfully');
} else {
    sendResponse(false, 'Failed to delete exercise: ' . $conn->error);
}

$stmt->close();
$conn->close();
?>
