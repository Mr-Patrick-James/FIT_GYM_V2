<?php
// Get All Managers
require_once 'config.php';
require_once 'session.php';

// Check if user is admin or manager
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Admin or Manager privileges required.'
    ]);
    exit;
}

header('Content-Type: application/json');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

try {
    // Get all managers with their details
    $sql = "SELECT id, name, email, role, contact, address, created_at, email_verified 
            FROM users 
            WHERE role = 'manager' 
            ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $managers = [];
    while ($row = $result->fetch_assoc()) {
        $managers[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Managers retrieved successfully',
        'data' => $managers,
        'count' => count($managers)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
