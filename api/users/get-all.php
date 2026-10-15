<?php
// Ensure no output before headers
ob_start();
require_once '../config.php';
require_once '../session.php';

// Clean any accidental output from config/session
if (ob_get_length()) ob_clean();

// Allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in and has proper permissions
requireLogin();

// Only admin and manager can view all users
if (!isAdmin() && !isManager()) {
    sendResponse(false, 'Access denied. Admin or Manager privileges required.', null, 403);
}

try {
    $conn = getDBConnection();
    // Get filter parameters
    $role = $_GET['role'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    // Prefer the saved profile number; older accounts may only have booking contacts.
    $contactSQL = "COALESCE(NULLIF(TRIM(u.contact), ''),
        (SELECT NULLIF(TRIM(b.contact), '') FROM bookings b
         WHERE b.user_id = u.id AND NULLIF(TRIM(b.contact), '') IS NOT NULL
         ORDER BY b.created_at DESC, b.id DESC LIMIT 1))";
    $sql = "SELECT u.id, u.name, u.email, u.role, $contactSQL AS contact,
                   u.address, u.created_at FROM users u WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($role !== 'all') {
        $sql .= " AND role = ?";
        $params[] = $role;
        $types .= "s";
    }
    
    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR $contactSQL LIKE ?)";
        $searchParam = '%' . $search . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        $types .= "sss";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    
    sendResponse(true, 'Users retrieved successfully', $users);

} catch (Exception $e) {
    error_log("Error getting users: " . $e->getMessage());
    sendResponse(false, 'Error retrieving users: ' . $e->getMessage(), null, 500);
}
