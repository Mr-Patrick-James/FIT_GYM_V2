<?php
/**
 * Debug Script to check PHP environment and Database connection
 */

// Display all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Info</h1>";

// Check PHP version
echo "<p>PHP Version: " . phpversion() . "</p>";

// Check extensions
$extensions = ['mysqli', 'session', 'json', 'filter', 'openssl'];
echo "<h2>Extensions Check:</h2><ul>";
foreach ($extensions as $ext) {
    echo "<li>$ext: " . (extension_loaded($ext) ? "<span style='color:green'>LOADED</span>" : "<span style='color:red'>MISSING</span>") . "</li>";
}
echo "</ul>";

// Check Database Connection
echo "<h2>Database Connection Test:</h2>";
try {
    require_once 'api/config.php';
    
    echo "<p>.env Loaded: " . ($envLoaded ? "<span style='color:green'>YES</span>" : "<span style='color:orange'>NO (Using defaults)</span>") . "</p>";
    echo "<p>Testing connection to <strong>" . DB_HOST . "</strong> as <strong>" . DB_USER . "</strong>...</p>";
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        echo "<p style='color:red'>Connection Failed: (" . $conn->connect_errno . ") " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color:green'>Connection Successful!</p>";
        
        // Check tables
        $tables = ['users', 'otps', 'packages', 'bookings', 'payments', 'email_configs'];
        echo "<h3>Tables Check:</h3><ul>";
        foreach ($tables as $table) {
            $res = $conn->query("SHOW TABLES LIKE '$table'");
            if ($res->num_rows > 0) {
                echo "<li>$table: <span style='color:green'>EXISTS</span></li>";
            } else {
                echo "<li>$table: <span style='color:red'>MISSING</span></li>";
            }
        }
        echo "</ul>";
        $conn->close();
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

// Check Session
echo "<h2>Session Check:</h2>";
try {
    $save_path = session_save_path();
    echo "<p>Session Save Path: <strong>$save_path</strong></p>";
    if (is_writable($save_path)) {
        echo "<p>Save Path Writable: <span style='color:green'>YES</span></p>";
    } else {
        echo "<p>Save Path Writable: <span style='color:red'>NO</span></p>";
        // Suggest fix for InfinityFree
        echo "<p style='color:orange'>Tip: If not writable on InfinityFree, you may need to use a custom directory.</p>";
    }

    if (session_start()) {
        echo "<p style='color:green'>Session started successfully.</p>";
        echo "<p>Session ID: " . session_id() . "</p>";
        $_SESSION['debug_test'] = time();
        echo "<p>Session Data (test): " . $_SESSION['debug_test'] . "</p>";
        
        // Check cookie settings
        $params = session_get_cookie_params();
        echo "<h3>Session Cookie Params:</h3><ul>";
        foreach ($params as $key => $val) {
            echo "<li>$key: " . (is_bool($val) ? ($val ? 'true' : 'false') : $val) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Failed to start session.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Session Error: " . $e->getMessage() . "</p>";
}

// Check if mail() is enabled
echo "<h2>Mail Check:</h2>";
if (function_exists('mail')) {
    echo "<p style='color:green'>mail() function is ENABLED.</p>";
} else {
    echo "<p style='color:red'>mail() function is DISABLED.</p>";
}

echo "<hr>";
echo "<p>Please share the results above if issues persist.</p>";
?>