<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['name']) || empty($data['specialization']) || empty($data['email'])) {
    sendResponse(false, 'Name, specialization and email are required');
}

$conn = getDBConnection();
$conn->begin_transaction();

try {
    $name = $conn->real_escape_string($data['name']);
    $specialization = $conn->real_escape_string($data['specialization']);
    $contact = $conn->real_escape_string($data['contact'] ?? '');
    $email = $conn->real_escape_string($data['email']);
    $bio = $conn->real_escape_string($data['bio'] ?? '');
    $photo_url = $conn->real_escape_string($data['photo_url'] ?? '');
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $password = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : password_hash('trainer123', PASSWORD_DEFAULT);

    // 1. Create User account first
    // Check if user already exists
    $checkUser = $conn->query("SELECT id FROM users WHERE email = '$email'");
    if ($checkUser && $checkUser->num_rows > 0) {
        $user = $checkUser->fetch_assoc();
        $userId = $user['id'];
        // Update existing user to trainer role
        $conn->query("UPDATE users SET role = 'trainer', name = '$name', contact = '$contact' WHERE id = $userId");
    } else {
        $sqlUser = "INSERT INTO users (name, email, password, role, contact, email_verified) 
                    VALUES ('$name', '$email', '$password', 'trainer', '$contact', TRUE)";
        if (!$conn->query($sqlUser)) {
            throw new Exception("Failed to create user account: " . $conn->error);
        }
        $userId = $conn->insert_id;
    }

    // 2. Create Trainer record
    $sqlTrainer = "INSERT INTO trainers (user_id, name, specialization, contact, email, bio, photo_url, is_active) 
                   VALUES ($userId, '$name', '$specialization', '$contact', '$email', '$bio', '$photo_url', $is_active)";
    
    if (!$conn->query($sqlTrainer)) {
        throw new Exception("Failed to create trainer record: " . $conn->error);
    }
    
    $trainerId = $conn->insert_id;
    
    $conn->commit();
    $conn->close();
    sendResponse(true, 'Trainer added successfully', ['id' => $trainerId, 'user_id' => $userId]);

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, $e->getMessage());
}
?>
