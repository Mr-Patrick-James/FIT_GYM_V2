<?php
require_once '../config.php';
require_once '../session.php';
requireTrainer();

$data = getRequestData();
$tip_id = (int)($data['tip_id'] ?? 0);
if (!$tip_id) sendResponse(false, 'Tip ID required');

$user = getCurrentUser();
$conn = getDBConnection();

// Get trainer ID
$trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
$trainerStmt->bind_param("i", $user['id']);
$trainerStmt->execute();
$trainer = $trainerStmt->get_result()->fetch_assoc();
if (!$trainer) sendResponse(false, 'Trainer record not found');

// Only allow deleting own tips
$stmt = $conn->prepare("DELETE FROM trainer_tips WHERE id = ? AND trainer_id = ?");
$stmt->bind_param("ii", $tip_id, $trainer['id']);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    sendResponse(true, 'Tip deleted');
} else {
    sendResponse(false, 'Tip not found or not authorised to delete');
}
?>
