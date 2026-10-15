<?php
require_once '../config.php';
require_once '../session.php';
require_once __DIR__.'/booking-access.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['booking_id']) || !isset($data['exercises'])) {
    sendResponse(false, 'Booking ID and exercises are required', null, 400);
}

$booking_id = (int)$data['booking_id'];
$exercises = $data['exercises'];
$user = getCurrentUser();

$conn = getDBConnection();
$trainerId = requireTrainerProfileId($conn, (int)$user['id']);
$booking = requireWritableTrainerBooking($conn, $booking_id, $trainerId);
if (!$booking['is_trainer_assisted']) {
    sendResponse(false, 'This package does not allow trainer-customized exercise plans.');
}
$conn->begin_transaction();

try {
    // Delete existing customized plan for this booking
    $conn->query("DELETE FROM member_exercise_plans WHERE booking_id = $booking_id");

    // Insert new customized plan
    $stmt = $conn->prepare("INSERT INTO member_exercise_plans (booking_id, exercise_id, sets, reps, notes) VALUES (?, ?, ?, ?, ?)");
    foreach ($exercises as $ex) {
        $ex_id = (int)$ex['id'];
        $sets = (int)($ex['sets'] ?? 3);
        $reps = $ex['reps'] ?? '10-12';
        $notes = $ex['notes'] ?? '';
        
        $stmt->bind_param("iiiss", $booking_id, $ex_id, $sets, $reps, $notes);
        if (!$stmt->execute()) {
            throw new Exception('Failed to save exercise plan: ' . $stmt->error);
        }
    }

    $conn->commit();
    $conn->close();
    sendResponse(true, 'Exercise plan customized successfully');

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, $e->getMessage());
}
?>
