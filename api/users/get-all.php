<?php
require_once '../config.php';
require_once '../session.php';

// Allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    $conn = getDBConnection();
    // Get filter parameters
    $role = $_GET['role'] ?? 'all';
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT id, name, email, role, contact, address, created_at FROM users WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($role !== 'all') {
        $sql .= " AND role = ?";
        $params[] = $role;
        $types .= "s";
    }
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR email LIKE ? OR contact LIKE ?)";
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
?>
