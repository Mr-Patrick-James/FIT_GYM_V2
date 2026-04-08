<?php
require_once '../config.php';
require_once '../session.php';

// Ensure user is an admin or manager
if (!isAdmin() && !isManager()) {
    sendResponse(false, 'Unauthorized access', null, 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();

    if ($method === 'GET') {
        // Fetch all admins (excluding the current one)
        $currentAdminId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE role = 'admin' AND id != ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $currentAdminId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }
        
        $stmt->close();
        sendResponse(true, 'Admins fetched successfully', $admins);

    } elseif ($method === 'POST') {
        // Add new admin
        $data = getRequestData();
        $name = trim($data['name'] ?? '');
        $email = trim(strtolower($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        
        if (empty($name) || empty($email) || empty($password)) {
            sendResponse(false, 'All fields are required', null, 400);
        }

        if (strlen($password) < 6) {
            sendResponse(false, 'Password must be at least 6 characters', null, 400);
        }

        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            sendResponse(false, 'Email already registered', null, 400);
        }
        $stmt->close();

        // Create new admin
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'admin';
        $verified = 1; // Admins created by main admin are pre-verified

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, email_verified) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $email, $hashedPassword, $role, $verified);
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            sendResponse(true, 'Admin account created successfully', ['id' => $newId]);
        } else {
            $stmt->close();
            sendResponse(false, 'Failed to create admin account');
        }

    } elseif ($method === 'DELETE') {
        // Delete sub-admin
        $data = getRequestData();
        $adminId = intval($data['id'] ?? 0);
        
        if ($adminId <= 0) {
            sendResponse(false, 'Invalid admin ID', null, 400);
        }

        // Ensure we don't delete ourselves
        if ($adminId == $_SESSION['user_id']) {
            sendResponse(false, 'Cannot delete your own account', null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
        $stmt->bind_param("i", $adminId);
        
        if ($stmt->execute()) {
            $stmt->close();
            sendResponse(true, 'Admin account removed successfully');
        } else {
            $stmt->close();
            sendResponse(false, 'Failed to remove admin account');
        }
    }

    $conn->close();
} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
?>
