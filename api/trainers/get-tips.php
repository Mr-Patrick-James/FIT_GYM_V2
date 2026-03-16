<?php
require_once '../config.php';
require_once '../session.php';
requireLogin();

$member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
if ($member_id <= 0) sendResponse(false, 'Member ID required');

$conn = getDBConnection();
$query = "SELECT * FROM trainer_tips WHERE member_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

$tips = [];
while ($row = $result->fetch_assoc()) {
    $tips[] = $row;
}

sendResponse(true, 'Tips retrieved', $tips);
?>
