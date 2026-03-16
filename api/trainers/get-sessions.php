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
    // Get exercise names if IDs exist
    $exercise_names = [];
    if (!empty($row['exercises'])) {
        $ex_ids = explode(',', $row['exercises']);
        $placeholders = implode(',', array_fill(0, count($ex_ids), '?'));
        $ex_stmt = $conn->prepare("SELECT name FROM exercises WHERE id IN ($placeholders)");
        $ex_stmt->bind_param(str_repeat('i', count($ex_ids)), ...$ex_ids);
        $ex_stmt->execute();
        $ex_res = $ex_stmt->get_result();
        while ($ex_row = $ex_res->fetch_assoc()) {
            $exercise_names[] = $ex_row['name'];
        }
        $ex_stmt->close();
    }

    $sessions[] = [
        'id' => $row['id'],
        'title' => $row['title'] ?: ($row['type'] === 'rest_day' ? 'Rest Day' : 'Workout Session'),
        'start' => $row['session_date'] . ($row['type'] === 'rest_day' ? '' : 'T' . $row['session_time']),
        'end' => $row['type'] === 'rest_day' ? null : date('Y-m-d\TH:i:s', strtotime($row['session_date'] . ' ' . $row['session_time'] . ' + ' . $row['duration'] . ' minutes')),
        'allDay' => $row['type'] === 'rest_day',
        'status' => $row['status'],
        'notes' => $row['notes'],
        'type' => $row['type'] ?? 'workout',
        'exercise_names' => $exercise_names,
        'color' => $row['type'] === 'rest_day' ? '#ef4444' : '#3b82f6'
    ];
}

header('Content-Type: application/json');
echo json_encode($sessions);
?>
