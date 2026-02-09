<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Session Management Helper
// Configure session settings for better reliability

// Define session cookie path - this should match your project folder
// For WAMP: if project is at http://localhost/Fit/, use '/Fit/'
// For root: use '/'
// You can override this by defining SESSION_COOKIE_PATH before including this file
if (!defined('SESSION_COOKIE_PATH')) {
    // For shared hosting, it's safer to use '/' as the cookie path
    // This ensures sessions work even if the project is in a subdirectory
    // or if the server redirects between www. and non-www.
    define('SESSION_COOKIE_PATH', '/');
}

if (session_status() === PHP_SESSION_NONE) {
    // IMPORTANT: Use consistent cookie path for all requests
    // This ensures the session cookie is available across all pages
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => SESSION_COOKIE_PATH,
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Start session
    if (!session_start()) {
        error_log("ERROR: Failed to start session");
    }
    
    // Debug: Log session info (only log once per request to avoid spam)
    if (!defined('SESSION_LOGGED')) {
        define('SESSION_LOGGED', true);
        error_log("Session started - ID: " . session_id() . ", Cookie Path: " . SESSION_COOKIE_PATH . ", Script: " . basename($_SERVER['PHP_SELF']));
    }
}

// Check if user is logged in
function isLoggedIn() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $loggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    
    // Debug logging
    if (!$loggedIn) {
        error_log("isLoggedIn() returned false - Session ID: " . session_id() . ", User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
    }
    
    return $loggedIn;
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Require login - redirect to index if not logged in
function requireLogin() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if logged in - check both session and cookies
    $isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    
    // If session not found, check if session cookie exists (might be path issue)
    if (!$isLoggedIn && isset($_COOKIE[session_name()])) {
        error_log("Session cookie exists but session data not found - Cookie: " . $_COOKIE[session_name()] . ", Current Session ID: " . session_id());
        // Try to get session ID from cookie
        $cookieSessionId = $_COOKIE[session_name()];
        if ($cookieSessionId !== session_id()) {
            error_log("Session ID mismatch - Cookie has: $cookieSessionId, Current: " . session_id());
        }
    }
    
    if (!$isLoggedIn) {
        // Log for debugging
        error_log("requireLogin() FAILED - Script: " . $_SERVER['PHP_SELF']);
        error_log("Session ID: " . session_id());
        error_log("Session name: " . session_name());
        error_log("Session data exists: " . (empty($_SESSION) ? 'NO' : 'YES'));
        error_log("Cookies: " . (empty($_COOKIE) ? 'NONE' : print_r(array_keys($_COOKIE), true)));
        if (!empty($_SESSION)) {
            error_log("Session keys: " . print_r(array_keys($_SESSION), true));
        }
        
        // Determine the correct relative path based on current location
        $currentPath = $_SERVER['PHP_SELF'];
        
        if (strpos($currentPath, '/views/admin/') !== false) {
            $redirectUrl = '../../index.php';
        } elseif (strpos($currentPath, '/views/user/') !== false) {
            $redirectUrl = '../../index.php';
        } elseif (strpos($currentPath, '/api/') !== false) {
            $redirectUrl = '../index.php';
        } else {
            $redirectUrl = 'index.php';
        }
        
        header("Location: $redirectUrl");
        exit();
    }
    
    // Log successful login check (only once to avoid spam)
    if (!defined('LOGIN_CHECKED')) {
        define('LOGIN_CHECKED', true);
        error_log("requireLogin() PASSED - User ID: " . $_SESSION['user_id'] . ", Email: " . $_SESSION['user_email']);
    }
}

// Require admin - redirect to user dashboard if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        // Determine the correct relative path based on current location
        $currentPath = $_SERVER['PHP_SELF'];
        
        if (strpos($currentPath, '/views/admin/') !== false) {
            $redirectUrl = '../user/dashboard.php';
        } elseif (strpos($currentPath, '/views/user/') !== false) {
            $redirectUrl = 'dashboard.php'; // Already in user directory
        } elseif (strpos($currentPath, '/api/') !== false) {
            $redirectUrl = '../views/user/dashboard.php';
        } else {
            $redirectUrl = 'views/user/dashboard.php';
        }
        
        header("Location: $redirectUrl");
        exit();
    }
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'user',
        'contact' => $_SESSION['user_contact'] ?? '',
        'address' => $_SESSION['user_address'] ?? ''
    ];
}

// Set user session
function setUserSession($user) {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set all session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $_SESSION['user_contact'] = $user['contact'] ?? '';
    $_SESSION['user_address'] = $user['address'] ?? '';
    
    // Regenerate session ID for security after login
    // On some shared hosts (like InfinityFree), this can cause session loss
    // if the server doesn't update the cookie fast enough
    // @session_regenerate_id(true);
    
    // Log for debugging
    $cookieParams = session_get_cookie_params();
    error_log("Session set - User ID: {$user['id']}, Email: {$user['email']}, Role: {$user['role']}, Session ID: " . session_id() . ", Cookie Path: " . $cookieParams['path']);
    
    // Verify session was set
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $user['id']) {
        error_log("ERROR: Session variables not set correctly after setUserSession()");
        // Force set them again just in case
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
    } else {
        error_log("Session verified - User ID in session: " . $_SESSION['user_id']);
    }
    
    // Explicitly save the session to ensure it's written before any redirection
    session_write_close();
    
    // Re-open session so it can be modified later if needed (though usually not after login)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Clear user session
function clearUserSession() {
    session_unset();
    session_destroy();
}

?>
