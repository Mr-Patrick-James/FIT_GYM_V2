<?php
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

$data = getRequestData();
$userId = $_SESSION['user_id'];
$name = trim($data['name'] ?? '');
$contact = trim($data['contact'] ?? '');
$address = trim($data['address'] ?? '');

if (empty($name)) {
    sendResponse(false, 'Name is required', null, 400);
}

try {
    $conn = getDBConnection();
    
    // Update user info
    $stmt = $conn->prepare("UPDATE users SET name = ?, contact = ?, address = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $contact, $address, $userId);
    
    if ($stmt->execute()) {
        // Update session variables
        $_SESSION['user_name'] = $name;
        $_SESSION['user_contact'] = $contact;
        $_SESSION['user_address'] = $address;
        
        // Return updated user data
        $updatedUser = [
            'id' => $userId,
            'name' => $name,
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'contact' => $contact,
            'address' => $address
        ];
        
        sendResponse(true, 'Profile updated successfully', $updatedUser);
    } else {
        sendResponse(false, 'Failed to update profile');
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Profile Update Error: " . $e->getMessage());
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
