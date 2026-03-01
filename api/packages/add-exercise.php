<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
$exercise_id = isset($_POST['exercise_id']) ? (int)$_POST['exercise_id'] : 0;
$sets = isset($_POST['sets']) ? (int)$_POST['sets'] : 3;
$reps = isset($_POST['reps']) ? $_POST['reps'] : '10-12';
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

if ($package_id <= 0 || $exercise_id <= 0) {
    sendResponse(false, 'Package ID and Exercise ID are required', null, 400);
}

$conn = getDBConnection();

$stmt = $conn->prepare("INSERT INTO package_exercises (package_id, exercise_id, sets, reps, notes) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE sets=VALUES(sets), reps=VALUES(reps), notes=VALUES(notes)");
$stmt->bind_param("iiiss", $package_id, $exercise_id, $sets, $reps, $notes);

if ($stmt->execute()) {
    sendResponse(true, 'Exercise added to package successfully');
} else {
    sendResponse(false, 'Failed to add exercise to package: ' . $conn->error);
}

$stmt->close();
$conn->close();
?>
