<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$email = trim(strtolower($data['email'] ?? ''));
$password = $data['password'] ?? '';

// Validation
if (empty($email) || empty($password)) {
    sendResponse(false, 'Email and password are required', null, 400);
}

try {
    $conn = getDBConnection();
    
    // Get user from database
    $stmt = $conn->prepare("SELECT id, name, email, password, role, contact, address, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Invalid email or password', null, 401);
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        $conn->close();
        sendResponse(false, 'Invalid email or password', null, 401);
    }
    
    // Check if email is verified (for regular users)
    if ($user['role'] === 'user' && !$user['email_verified']) {
        $conn->close();
        sendResponse(false, 'Please verify your email before logging in', null, 403);
    }
    
    $conn->close();
} catch (Exception $e) {
    error_log("Login Error: " . $e->getMessage());
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}

// Remove password from response
unset($user['password']);

// Set user session BEFORE sending response
// This ensures session cookie is set and sent with the response
require_once '../session.php';
setUserSession($user);

// Force session write to ensure cookie is sent
// PHP will automatically write session on script end, but we want to ensure it's done
if (function_exists('fastcgi_finish_request')) {
    // For FastCGI, finish request but keep session alive
    fastcgi_finish_request();
}

// Verify session was set correctly
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $user['id']) {
    error_log("WARNING: Session not set correctly after login for user: " . $user['email']);
    // Force set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $_SESSION['user_contact'] = $user['contact'] ?? '';
    $_SESSION['user_address'] = $user['address'] ?? '';
}

// Log successful login for debugging
error_log("Login successful - User: {$user['email']}, Role: {$user['role']}, Session ID: " . session_id());
error_log("Session cookie params: " . print_r(session_get_cookie_params(), true));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));

// Determine redirect URL (relative to project root)
$redirect = $user['role'] === 'admin' 
    ? 'views/admin/dashboard.php' 
    : 'views/user/dashboard.php';

sendResponse(true, 'Login successful', [
    'user' => $user,
    'redirect' => $redirect
]);

?>
