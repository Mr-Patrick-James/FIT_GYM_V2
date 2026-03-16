<?php
require_once '../config.php';
require_once '../session.php';
requireLogin();

$user = getCurrentUser();
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    sendResponse(false, 'Booking ID is required', null, 400);
}

$conn = getDBConnection();

// Authorization check
$isTrainer = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'trainer';

if ($isTrainer) {
    $trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $trainerStmt->bind_param("i", $user['id']);
    $trainerStmt->execute();
    $trainer = $trainerStmt->get_result()->fetch_assoc();
    $trainerId = $trainer['id'];

    $checkStmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND trainer_id = ?");
    $checkStmt->bind_param("ii", $booking_id, $trainerId);
} else {
    $checkStmt = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $booking_id, $user['id']);
}

$checkStmt->execute();
$booking = $checkStmt->get_result()->fetch_assoc();

if (!$booking) {
    sendResponse(false, 'Unauthorized or booking not found', null, 403);
}

// Get progress history
$query = "
    SELECT p.id, p.weight, p.remarks, p.logged_at, p.created_at, t.name as trainer_name
    FROM member_progress p
    JOIN trainers t ON p.trainer_id = t.id
    WHERE p.booking_id = ?
    ORDER BY p.logged_at DESC, p.created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
$isCalendar = isset($_GET['calendar']) && $_GET['calendar'] == '1';

while ($row = $result->fetch_assoc()) {
    if ($isCalendar) {
        $history[] = [
            'id' => 'progress-' . $row['id'],
            'title' => '⚖️ ' . $row['weight'] . ' kg',
            'start' => $row['logged_at'],
            'allDay' => true,
            'extendedProps' => [
                'type' => 'progress',
                'remarks' => $row['remarks'],
                'trainer' => $row['trainer_name']
            ]
        ];
    } else {
        $history[] = $row;
    }
}

$stmt->close();
$conn->close();

if ($isCalendar) {
    echo json_encode($history);
    exit();
}

sendResponse(true, 'Progress history retrieved successfully', $history);
?>
