<?php
// Create Manager Account
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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request data'
    ]);
    exit;
}

$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$contact = trim($data['contact'] ?? '');
$address = trim($data['address'] ?? '');

// Validation
if (empty($name) || empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Name, email, and password are required'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 6 characters long'
    ]);
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

try {
    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Email already exists'
        ]);
        exit;
    }
    $checkEmail->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert manager account
    $insertUser = $conn->prepare("INSERT INTO users (name, email, password, role, contact, address, email_verified, created_at) VALUES (?, ?, ?, 'manager', ?, ?, 1, NOW())");
    $insertUser->bind_param("sssss", $name, $email, $hashedPassword, $contact, $address);
    
    if (!$insertUser->execute()) {
        throw new Exception('Failed to create manager account: ' . $insertUser->error);
    }
    
    $managerId = $insertUser->insert_id;
    $insertUser->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Manager account created successfully',
        'manager' => [
            'id' => $managerId,
            'name' => $name,
            'email' => $email,
            'role' => 'manager',
            'contact' => $contact,
            'address' => $address
        ]
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
