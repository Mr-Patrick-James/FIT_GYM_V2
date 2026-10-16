<?php
require_once '../config.php';
require_once '../session.php';

// Exercise management is shared by Trainer, Manager, and Admin.
// isTrainer() also includes the higher Manager/Admin role levels.
if (!isTrainer()) {
    sendResponse(false, 'Unauthorized access. Trainer privileges required.', null, 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$rawId = $_POST['id'] ?? null;
$idText = is_int($rawId) ? (string)$rawId : (is_string($rawId) ? $rawId : '');
$normalizedId = ltrim($idText, '0');
$maxId = (string)PHP_INT_MAX;

if (
    !preg_match('/^\d+$/D', $idText)
    || $normalizedId === ''
    || strlen($normalizedId) > strlen($maxId)
    || (strlen($normalizedId) === strlen($maxId) && strcmp($normalizedId, $maxId) > 0)
) {
    sendResponse(false, 'Valid ID is required', null, 400);
}

$id = (int)$normalizedId;
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
