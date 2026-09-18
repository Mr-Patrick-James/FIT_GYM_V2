<?php
require_once '../config.php';
require_once '../session.php';

// Only admins can manage trainer managers
if (!isAdmin()) {
    sendResponse(false, 'Unauthorized access. Admin privileges required.', null, 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();

    if ($method === 'GET') {
        // Fetch all managers
        $stmt = $conn->prepare(
            "SELECT id, name, email, role, contact, address, created_at 
             FROM users 
             WHERE role = 'manager' 
             ORDER BY created_at DESC"
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $managers = [];
        while ($row = $result->fetch_assoc()) {
            $managers[] = $row;
        }

        $stmt->close();
        sendResponse(true, 'Managers fetched successfully', $managers);

    } elseif ($method === 'POST') {
        // Create a new manager account
        $data = getRequestData();
        $name     = trim($data['name']     ?? '');
        $email    = trim(strtolower($data['email']    ?? ''));
        $password = $data['password'] ?? '';
        $contact  = trim($data['contact']  ?? '');
        $address  = trim($data['address']  ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            sendResponse(false, 'Name, email, and password are required.', null, 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, 'Invalid email format.', null, 400);
        }

        if (strlen($password) < 6) {
            sendResponse(false, 'Password must be at least 6 characters.', null, 400);
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            sendResponse(false, 'Email is already registered.', null, 400);
        }
        $stmt->close();

        // Insert the manager
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'manager';
        $verified = 1;

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password, role, contact, address, email_verified, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("ssssssi", $name, $email, $hashedPassword, $role, $contact, $address, $verified);

        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            sendResponse(true, 'Manager account created successfully.', [
                'id'      => $newId,
                'name'    => $name,
                'email'   => $email,
                'role'    => 'manager',
                'contact' => $contact,
                'address' => $address
            ]);
        } else {
            $stmt->close();
            sendResponse(false, 'Failed to create manager account.', null, 500);
        }

    } elseif ($method === 'DELETE') {
        // Remove a manager account
        $data = getRequestData();
        $managerId = intval($data['id'] ?? 0);

        if ($managerId <= 0) {
            sendResponse(false, 'Invalid manager ID.', null, 400);
        }

        // Prevent deleting yourself
        if ($managerId == ($_SESSION['user_id'] ?? 0)) {
            sendResponse(false, 'You cannot delete your own account.', null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'manager'");
        $stmt->bind_param("i", $managerId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            sendResponse(true, 'Manager account removed successfully.');
        } else {
            $stmt->close();
            sendResponse(false, 'Manager not found or could not be removed.', null, 400);
        }

    } else {
        sendResponse(false, 'Method not allowed.', null, 405);
    }

    $conn->close();
} catch (Exception $e) {
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
?>
