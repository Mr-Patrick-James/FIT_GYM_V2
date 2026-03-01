<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
$exercise_id = isset($_POST['exercise_id']) ? (int)$_POST['exercise_id'] : 0;

if ($package_id <= 0 || $exercise_id <= 0) {
    sendResponse(false, 'Package ID and Exercise ID are required', null, 400);
}

$conn = getDBConnection();

$stmt = $conn->prepare("DELETE FROM package_exercises WHERE package_id = ? AND exercise_id = ?");
$stmt->bind_param("ii", $package_id, $exercise_id);

if ($stmt->execute()) {
    sendResponse(true, 'Exercise removed from package successfully');
} else {
    sendResponse(false, 'Failed to remove exercise from package: ' . $conn->error);
}

$stmt->close();
$conn->close();
?>
