<?php
// InfinityFree Specific Configuration Example
// Save this as config.infinityfree.php and modify as needed

// Detect if running on InfinityFree
$isInfinityFree = strpos($_SERVER['HTTP_HOST'] ?? '', 'infinityfreeapp.com') !== false;

// Database Configuration for InfinityFree
// Get these values from your InfinityFree control panel
define('DB_HOST', $_ENV['DB_HOST'] ?? 'sql113.infinityfree.com'); // Replace with your host
define('DB_USER', $_ENV['DB_USER'] ?? 'if0_35800000');           // Replace with your username  
define('DB_PASS', $_ENV['DB_PASS'] ?? 'your_db_password');       // Replace with your password
define('DB_NAME', $_ENV['DB_NAME'] ?? 'if0_35800000_fitpay');   // Replace with your database name

// Base URL Configuration - Auto-detect for InfinityFree
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Remove www. if present and handle InfinityFree subdomains properly
$cleanHost = str_replace('www.', '', $host);

// Build base URL
define('BASE_URL', $protocol . '://' . $cleanHost . dirname($_SERVER['SCRIPT_NAME']));

// Increase timeout for database connections (helpful on shared hosting)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('mysql.connect_timeout', 30);
ini_set('default_socket_timeout', 30);

// Error reporting - disable for production
if ($isInfinityFree) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

// Create database connection with error handling for shared hosting
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            throw new Exception("Database connection failed. Please contact administrator.");
        }
        
        $conn->set_charset("utf8mb4");
        
        // Set MySQL session timezone to match server
        $conn->query("SET time_zone = '+00:00'");
        
        return $conn;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw $e;
    }
}

// Additional security headers for shared hosting
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header("Content-Type: application/json");
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

echo "InfinityFree configuration loaded successfully.";
?>