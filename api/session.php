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

// Include enhanced access control
require_once 'access-control.php';

// Check if user is logged in
function isLoggedIn() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $loggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    return $loggedIn;
}

// Check if user is admin (using enhanced access control)
function isAdmin() {
    return hasRoleLevel('admin');
}

// Check if user is manager or higher
function isManager() {
    return hasRoleLevel('manager');
}

// Check if user is trainer or higher
function isTrainer() {
    return hasRoleLevel('trainer');
}

function isApiRequest() {
    $script = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
    $normalized = str_replace('\\', '/', $script);
    return strpos($normalized, '/api/') !== false;
}

// Enhanced session validation
function validateSession() {
    if (!isLoggedIn()) {
        if (isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'AUTH_REQUIRED'
            ]);
            exit;
        } else {
            // Redirect to login for web requests
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            while ($basePath != '/' && strpos($basePath, '/api') !== false) {
                $basePath = dirname($basePath);
            }
            header('Location: ' . $basePath . '/views/login.php');
            exit;
        }
    }
}

// Get current user info safely
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'contact' => $_SESSION['user_contact'] ?? null,
        'address' => $_SESSION['user_address'] ?? null
    ];
}

// Check if current user can access specific resource
function canAccessResource($resourceType, $resourceId = null) {
    $userRole = $_SESSION['user_role'] ?? null;
    
    switch ($resourceType) {
        case 'admin_panel':
            return isAdmin() || isManager(); // Managers can access admin panel
            
        case 'manager_panel':
            return isManager() || isAdmin();
            
        case 'trainer_panel':
            return isTrainer() || isManager() || isAdmin();
            
        case 'user_data':
            if (isAdmin() || isManager()) {
                return true; // Can access all user data
            }
            // Users can only access their own data
            return $resourceId == $_SESSION['user_id'];
            
        case 'booking_management':
            return isAdmin() || isManager(); // Both can manage bookings
            
        case 'payment_management':
            return isAdmin() || isManager(); // Both can manage payments
            
        case 'user_management':
            return isAdmin() || isManager(); // Both can manage users
            
        case 'trainer_management':
            return isAdmin() || isManager(); // Both can manage trainers
            
        case 'reports':
            return isAdmin() || isManager(); // Both can view reports
            
        case 'settings':
            return isAdmin() || isManager(); // Both can access settings
            
        case 'system_config':
            return isAdmin() || isManager(); // Both can configure system
            
        default:
            return isAdmin() || isManager(); // Default to admin/manager access
    }
}

// Require login - redirect to index if not logged in
function requireLogin() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if logged in - check both session and cookies
    $isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    
    if (!$isLoggedIn) {
        // Log for debugging
        error_log("requireLogin() FAILED - Script: " . ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF']));
        
        // Handle API requests separately
        if (isApiRequest()) {
            if (!headers_sent()) {
                header("Content-Type: application/json");
            }
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized access. Please log in.',
                'error_code' => 'UNAUTHORIZED'
            ]);
            exit();
        }
        
        // Determine the correct relative path based on current location
        $currentPath = $_SERVER['PHP_SELF'];
        
        if (strpos($currentPath, '/views/admin/') !== false || strpos($currentPath, '/views/trainer/') !== false) {
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

// Require admin or manager - redirect to appropriate dashboard if not admin or manager
// Managers have full access to all admin functions (bookings, payments, members)
function requireAdmin() {
    requireLogin();
    if (!isAdmin() && !isManager()) {
        // Handle API requests separately
        if (isApiRequest()) {
            if (!headers_sent()) {
                header("Content-Type: application/json");
            }
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Admin or Manager privileges required.',
                'error_code' => 'FORBIDDEN'
            ]);
            exit();
        }

        // Determine the correct relative path based on current location
        $currentPath = $_SERVER['PHP_SELF'];
        
        if (isTrainer()) {
            $redirectUrl = (strpos($currentPath, '/views/admin/') !== false) ? '../trainer/dashboard.php' : 'trainer/dashboard.php';
        } else {
            $redirectUrl = (strpos($currentPath, '/views/admin/') !== false) ? '../user/dashboard.php' : 'user/dashboard.php';
        }
        
        header("Location: $redirectUrl");
        exit();
    }
}

// Require trainer - redirect to user dashboard if not trainer, manager, or admin
function requireTrainer() {
    requireLogin();
    if (!isTrainer() && !isAdmin() && !isManager()) {
        // Handle API requests separately
        if (isApiRequest()) {
            if (!headers_sent()) {
                header("Content-Type: application/json");
            }
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Trainer privileges required.',
                'error_code' => 'FORBIDDEN'
            ]);
            exit();
        }

        // Determine the correct relative path based on current location
        $currentPath = $_SERVER['PHP_SELF'];
        
        if (strpos($currentPath, '/views/trainer/') !== false) {
            $redirectUrl = '../user/dashboard.php';
        } else {
            $redirectUrl = 'views/user/dashboard.php';
        }
        
        header("Location: $redirectUrl");
        exit();
    }
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
