<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
    $trainerResult = $conn->query("SELECT user_id FROM trainers WHERE id = $id");
    if (!$trainerResult || $trainerResult->num_rows === 0) {
        throw new Exception("Trainer not found");
    }
    $trainer = $trainerResult->fetch_assoc();
    $userId = $trainer['user_id'];

    $fields = [];
    if (isset($data['name']))           $fields[] = "name = '"           . $conn->real_escape_string($data['name']) . "'";
    if (isset($data['specialization'])) $fields[] = "specialization = '" . $conn->real_escape_string($data['specialization']) . "'";
    if (isset($data['contact']))        $fields[] = "contact = '"        . $conn->real_escape_string($data['contact']) . "'";
    if (isset($data['email']))          $fields[] = "email = '"          . $conn->real_escape_string($data['email']) . "'";
    if (isset($data['bio']))            $fields[] = "bio = '"            . $conn->real_escape_string($data['bio']) . "'";
    if (isset($data['photo_url']))      $fields[] = "photo_url = '"      . $conn->real_escape_string($data['photo_url']) . "'";
    if (isset($data['is_active']))      $fields[] = "is_active = "       . (int)$data['is_active'];
    if (isset($data['availability']))   $fields[] = "availability = '"   . $conn->real_escape_string($data['availability']) . "'";
    if (isset($data['certifications'])) $fields[] = "certifications = '" . $conn->real_escape_string($data['certifications']) . "'";
    if (isset($data['max_clients']))    $fields[] = "max_clients = "     . (int)$data['max_clients'];

    if (!empty($fields)) {
        $queryTrainer = "UPDATE trainers SET " . implode(', ', $fields) . " WHERE id = $id";
        if (!$conn->query($queryTrainer)) {
            throw new Exception("Failed to update trainer: " . $conn->error);
        }
    }

    if ($userId) {
        $userFields = [];
        if (isset($data['name']))    $userFields[] = "name = '"    . $conn->real_escape_string($data['name']) . "'";
        if (isset($data['email']))   $userFields[] = "email = '"   . $conn->real_escape_string($data['email']) . "'";
        if (isset($data['contact'])) $userFields[] = "contact = '" . $conn->real_escape_string($data['contact']) . "'";
        if (!empty($data['password'])) {
            $userFields[] = "password = '" . password_hash($data['password'], PASSWORD_DEFAULT) . "'";
        }
        if (!empty($userFields)) {
            $queryUser = "UPDATE users SET " . implode(', ', $userFields) . " WHERE id = $userId";
            if (!$conn->query($queryUser)) {
                throw new Exception("Failed to update linked user account: " . $conn->error);
            }
        }
    }

    $conn->commit();
    $conn->close();
    sendResponse(true, 'Trainer updated successfully');

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, $e->getMessage());
}
?>
