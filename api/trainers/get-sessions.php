<?php
require_once '../config.php';
require_once '../session.php';
requireLogin();

$user = getCurrentUser();
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$upcoming = isset($_GET['upcoming']) ? (bool)$_GET['upcoming'] : false;

$conn = getDBConnection();

// Get trainer ID if trainer is logged in
$trainerId = null;
if ($_SESSION['user_role'] === 'trainer') {
    $trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $trainerStmt->bind_param("i", $user['id']);
    $trainerStmt->execute();
    $trainerId = $trainerStmt->get_result()->fetch_assoc()['id'];
}

$query = "SELECT * FROM trainer_sessions WHERE 1=1";
if ($booking_id > 0) $query .= " AND booking_id = $booking_id";
if ($upcoming) $query .= " AND session_date >= CURDATE() AND status = 'scheduled'";
$query .= " ORDER BY session_date ASC, session_time ASC";

$result = $conn->query($query);
$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = [
        'id' => $row['id'],
        'title' => $row['notes'] ?: 'Workout Session',
        'start' => $row['session_date'] . 'T' . $row['session_time'],
        'end' => date('Y-m-d\TH:i:s', strtotime($row['session_date'] . ' ' . $row['session_time'] . ' + ' . $row['duration'] . ' minutes')),
        'status' => $row['status'],
        'notes' => $row['notes']
    ];
}

header('Content-Type: application/json');
echo json_encode($sessions);
?>
