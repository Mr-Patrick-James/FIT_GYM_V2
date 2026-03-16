<?php
require_once '../config.php';
require_once '../session.php';
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
$conn->begin_transaction();

try {
    // Verify that the booking is assigned to this trainer
    $trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $trainerStmt->bind_param("i", $user['id']);
    $trainerStmt->execute();
    $trainer = $trainerStmt->get_result()->fetch_assoc();
    $trainerId = $trainer['id'];

    $checkStmt = $conn->prepare("
        SELECT b.id, p.is_trainer_assisted 
        FROM bookings b 
        JOIN packages p ON b.package_id = p.id 
        WHERE b.id = ? AND b.trainer_id = ?
    ");
    $checkStmt->bind_param("ii", $booking_id, $trainerId);
    $checkStmt->execute();
    $booking = $checkStmt->get_result()->fetch_assoc();
    
    if (!$booking) {
        throw new Exception('Unauthorized or booking not found');
    }
    
    if (!$booking['is_trainer_assisted']) {
        throw new Exception('This package does not allow trainer-customized exercise plans.');
    }

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
