<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id'])) {
    sendResponse(false, 'ID is required');
}

$id = (int)$data['id'];
$conn = getDBConnection();
$conn->begin_transaction();

try {
    // 1. Get user_id before deleting trainer
    $trainerResult = $conn->query("SELECT user_id FROM trainers WHERE id = $id");
    if (!$trainerResult || $trainerResult->num_rows === 0) {
        throw new Exception("Trainer not found");
    }
    $trainer = $trainerResult->fetch_assoc();
    $userId = $trainer['user_id'];

    // 2. Delete trainer record
    $queryTrainer = "DELETE FROM trainers WHERE id = $id";
    if (!$conn->query($queryTrainer)) {
        throw new Exception("Failed to delete trainer: " . $conn->error);
    }

    // 3. Delete user account if linked
    if ($userId) {
        $queryUser = "DELETE FROM users WHERE id = $userId";
        if (!$conn->query($queryUser)) {
            // If user deletion fails (e.g. due to other FK constraints like bookings),
            // maybe we just keep it or change role back to 'user'
            $conn->query("UPDATE users SET role = 'user' WHERE id = $userId");
        }
    }

    $conn->commit();
    $conn->close();
    sendResponse(true, 'Trainer deleted successfully');

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, $e->getMessage());
}
?>
