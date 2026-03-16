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

// Authorization check: User must be either the assigned trainer OR the member who owns the booking
$isTrainer = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'trainer';
$isUser = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user';

if ($isTrainer) {
    $trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $trainerStmt->bind_param("i", $user['id']);
    $trainerStmt->execute();
    $trainer = $trainerStmt->get_result()->fetch_assoc();
    $trainerId = $trainer['id'];

    $checkStmt = $conn->prepare("
        SELECT b.id, b.package_id, p.name as package_name, p.is_trainer_assisted 
        FROM bookings b 
        JOIN packages p ON b.package_id = p.id 
        WHERE b.id = ? AND b.trainer_id = ?
    ");
    $checkStmt->bind_param("ii", $booking_id, $trainerId);
} else {
    $checkStmt = $conn->prepare("
        SELECT b.id, b.package_id, p.name as package_name, p.is_trainer_assisted 
        FROM bookings b 
        JOIN packages p ON b.package_id = p.id 
        WHERE b.id = ? AND b.user_id = ?
    ");
    $checkStmt->bind_param("ii", $booking_id, $user['id']);
}

$checkStmt->execute();
$booking = $checkStmt->get_result()->fetch_assoc();

if (!$booking) {
    sendResponse(false, 'Unauthorized or booking not found', null, 403);
}

// Check if package allows trainer management
if ($isTrainer && !$booking['is_trainer_assisted']) {
    // Optional: You could still allow it, or restrict it. 
    // The user said "depending the packages", so let's allow but flag it.
}

$package_id = $booking['package_id'];

// Check if member has a customized plan already
$planStmt = $conn->prepare("
    SELECT 
        e.id, 
        e.name, 
        e.category, 
        e.description, 
        e.image_url,
        mep.sets, 
        mep.reps, 
        mep.notes
    FROM member_exercise_plans mep
    JOIN exercises e ON mep.exercise_id = e.id
    WHERE mep.booking_id = ?
    ORDER BY mep.id ASC
");
$planStmt->bind_param("i", $booking_id);
$planStmt->execute();
$result = $planStmt->get_result();

$exercises = [];
$is_customized = false;

if ($result->num_rows > 0) {
    $is_customized = true;
    while ($row = $result->fetch_assoc()) {
        $exercises[] = $row;
    }
} else {
    // Fallback to package exercises as template
    $pkgStmt = $conn->prepare("
        SELECT 
            e.id, 
            e.name, 
            e.category, 
            e.description, 
            e.image_url,
            pe.sets, 
            pe.reps, 
            pe.notes
        FROM package_exercises pe
        JOIN exercises e ON pe.exercise_id = e.id
        WHERE pe.package_id = ?
        ORDER BY pe.id ASC
    ");
    $pkgStmt->bind_param("i", $package_id);
    $pkgStmt->execute();
    $pkgResult = $pkgStmt->get_result();
    while ($row = $pkgResult->fetch_assoc()) {
        $exercises[] = $row;
    }
    $pkgStmt->close();
}

$planStmt->close();
$conn->close();

sendResponse(true, 'Exercise plan retrieved successfully', [
    'booking_id' => $booking_id,
    'package_name' => $booking['package_name'],
    'is_trainer_assisted' => (bool)$booking['is_trainer_assisted'],
    'is_customized' => $is_customized,
    'exercises' => $exercises
]);
?>
