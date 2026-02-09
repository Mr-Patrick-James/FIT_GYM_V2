<?php
// Load environment variables from .env file
// On shared hosting, .env files might not work due to security restrictions
$envLoaded = false;
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') === false) continue; // Skip invalid lines
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        $envLoaded = true;
    }
}

// If .env wasn't loaded, try to use server environment variables or defaults
if (!$envLoaded) {
    error_log("Environment file not loaded, using server defaults");
}

// Helper to get environment variable
function getEnvVar($key, $default = '') {
    return $_ENV[$key] ?? $default;
}

// ============================================
// DATABASE CONFIGURATION
// ============================================

// Toggle between LOCAL and REMOTE here
$is_local = true; // Set to true for WAMP, false for InfinityFree

if ($is_local) {
    // LOCAL WAMP SETTINGS
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'fitpay_gym');
} else {
    // REMOTE INFINITYFREE SETTINGS
    define('DB_HOST', 'sql109.infinityfree.com');
    define('DB_USER', 'if0_40968761');
    define('DB_PASS', 'griGWq3JFaJgda2');
    define('DB_NAME', 'if0_40968761_fitpay_gym');
}

// ============================================
// BASE URL CONFIGURATION
// ============================================
$protocol = 'http';
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    $protocol = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// More robust base directory detection
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$phpSelf = $_SERVER['PHP_SELF'] ?? '';
$targetScript = !empty($scriptName) ? $scriptName : $phpSelf;

// The api/config.php is always in the /api folder
// We want to find the project root which is one level above /api
// If the script is /Fit/api/bookings/get-all.php, we need to find /Fit
$baseDir = '';
if (!empty($targetScript)) {
    // Standardize to forward slashes
    $normalizedScript = str_replace('\\', '/', $targetScript);
    // Find the position of '/api/' which marks our project structure
    $apiPos = strpos($normalizedScript, '/api/');
    if ($apiPos !== false) {
        $baseDir = substr($normalizedScript, 0, $apiPos);
    } else {
        // Fallback for root level scripts
        $baseDir = dirname($normalizedScript);
        if ($baseDir === '\\' || $baseDir === '/') $baseDir = '';
    }
}

// Clean the host to remove www if needed
$cleanHost = str_replace('www.', '', $host);
// Fix localhost trailing dot issue
if ($cleanHost === 'localhost.') {
    $cleanHost = 'localhost';
}

// Define BASE_URL - Fix for localhost WAMP setup
$fullBaseUrl = 'http://localhost/Fit';
// Remove trailing slash if exists
$fullBaseUrl = rtrim($fullBaseUrl, '/');

define('BASE_URL', $fullBaseUrl);

// Create database connection
function getDBConnection() {
    // Use @ to suppress raw errors and handle them gracefully
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        // On InfinityFree, we want to see the error during setup
        die("Connection failed: " . $conn->connect_error . " (Check your credentials in config.php)");
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Enable CORS (but don't set headers if they're already sent)
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json");
}

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle PUT and DELETE requests for JSON data
if (isset($_SERVER['REQUEST_METHOD']) && in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE'])) {
    // Parse raw input for PUT/DELETE requests
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $_PUT_DELETE_DATA = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $_PUT_DELETE_DATA = [];
        }
    } else {
        $_PUT_DELETE_DATA = [];
    }
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Helper function to get request data
function getRequestData() {
    // Handle PUT and DELETE requests
    if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE'])) {
        global $_PUT_DELETE_DATA;
        return $_PUT_DELETE_DATA ?? [];
    }
    
    // Handle POST requests
    $data = json_decode(file_get_contents('php://input'), true);
    return $data ? $data : [];
}

?>
