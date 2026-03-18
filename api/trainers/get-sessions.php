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
    $res = $trainerStmt->get_result()->fetch_assoc();
    $trainerId = $res ? $res['id'] : null;
}

$query = "SELECT ts.*, u.name as member_name 
          FROM trainer_sessions ts
          JOIN bookings b ON ts.booking_id = b.id
          JOIN users u ON b.user_id = u.id
          WHERE 1=1";
if ($booking_id > 0) $query .= " AND ts.booking_id = " . (int)$booking_id;
if ($trainerId) $query .= " AND ts.trainer_id = " . (int)$trainerId;
if ($upcoming) $query .= " AND ts.session_date >= CURDATE() AND ts.status = 'scheduled'";
$query .= " ORDER BY ts.session_date ASC, ts.session_time ASC";

$result = $conn->query($query);
$sessions = [];
while ($row = $result->fetch_assoc()) {
    // Get detailed exercise info from member_exercise_plans
    $exercise_details = [];
    if (!empty($row['exercises'])) {
        $ex_ids = explode(',', $row['exercises']);
        $placeholders = implode(',', array_fill(0, count($ex_ids), '?'));
        // Join with exercises to get names and member_exercise_plans to get sets/reps for this specific booking
        $ex_stmt = $conn->prepare("
            SELECT e.name, e.image_url, e.description, mp.sets, mp.reps 
            FROM exercises e
            LEFT JOIN member_exercise_plans mp ON e.id = mp.exercise_id AND mp.booking_id = ?
            WHERE e.id IN ($placeholders)
        ");
        
        $params = array_merge([(int)$row['booking_id']], array_map('intval', $ex_ids));
        $types = 'i' . str_repeat('i', count($ex_ids));
        $ex_stmt->bind_param($types, ...$params);
        $ex_stmt->execute();
        $ex_res = $ex_stmt->get_result();
        while ($ex_row = $ex_res->fetch_assoc()) {
            $exercise_details[] = [
                'name' => $ex_row['name'],
                'image_url' => $ex_row['image_url'],
                'description' => $ex_row['description'],
                'sets' => $ex_row['sets'] ?? 3,
                'reps' => $ex_row['reps'] ?? '10-12'
            ];
        }
        $ex_stmt->close();
    }

    $sessions[] = [
        'id' => $row['id'],
        'member_name' => $row['member_name'],
        'title' => $row['title'] ?: ($row['type'] === 'rest_day' ? 'Rest Day' : 'Workout Session'),
        'start' => $row['session_date'] . ($row['type'] === 'rest_day' ? '' : 'T' . $row['session_time']),
        'end' => $row['type'] === 'rest_day' ? null : date('Y-m-d\TH:i:s', strtotime($row['session_date'] . ' ' . $row['session_time'] . ' + ' . $row['duration'] . ' minutes')),
        'allDay' => $row['type'] === 'rest_day',
        'status' => $row['status'],
        'notes' => $row['notes'],
        'type' => $row['type'] ?? 'workout',
        'exercise_details' => $exercise_details,
        'color' => $row['type'] === 'rest_day' ? '#ef4444' : '#3b82f6'
    ];
}

header('Content-Type: application/json');
echo json_encode($sessions);
?>
