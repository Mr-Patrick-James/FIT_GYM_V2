<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$id = (int)($data['id'] ?? 0);

// Validation
if ($id <= 0) {
    sendResponse(false, 'Invalid package ID', null, 400);
}

$conn = getDBConnection();

// Check if package exists
$checkStmt = $conn->prepare("SELECT id, name FROM packages WHERE id = ?");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    $checkStmt->close();
    $conn->close();
    sendResponse(false, 'Package not found', null, 404);
}

$package = $checkResult->fetch_assoc();
$checkStmt->close();

// Soft delete - set is_active to false
$stmt = $conn->prepare("UPDATE packages SET is_active = FALSE, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    
    sendResponse(true, 'Package deleted successfully', [
        'id' => $id,
        'name' => $package['name']
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendResponse(false, 'Error deleting package: ' . $error, null, 500);
}
?>