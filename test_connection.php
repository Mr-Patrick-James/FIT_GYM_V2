<?php
/**
 * Database Connection Test for InfinityFree
 * Use this file to test your database connection before full deployment
 */

// Load configuration
require_once 'api/config.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Attempt to connect to the database
    $conn = getDBConnection();
    
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    // Test a simple query
    $result = $conn->query("SELECT VERSION() as version");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Database version: " . htmlspecialchars($row['version']) . "</p>";
    }
    
    // Check if our tables exist
    $tables = ['users', 'packages', 'bookings', 'payments', 'otps', 'email_configs'];
    echo "<h3>Table Status:</h3><ul>";
    foreach ($tables as $table) {
        $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
        $exists = $checkTable->num_rows > 0;
        $status = $exists ? '✓ Exists' : '✗ Missing';
        $color = $exists ? 'green' : 'red';
        echo "<li style='color: $color;'>$table: $status</li>";
    }
    echo "</ul>";
    
    // Close connection
    $conn->close();
    
    echo "<h3 style='color: green;'>Ready for InfinityFree deployment!</h3>";
    echo "<p>If all tables show as 'Exists', your database is properly configured.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your .env file database configuration.</p>";
    
    // Show current config values
    echo "<h3>Current Configuration:</h3>";
    echo "<p>DB_HOST: " . htmlspecialchars(DB_HOST) . "</p>";
    echo "<p>DB_USER: " . htmlspecialchars(DB_USER) . "</p>";
    echo "<p>DB_NAME: " . htmlspecialchars(DB_NAME) . "</p>";
    echo "<p>(DB_PASS is not displayed for security)</p>";
    
    echo "<br><h3>Troubleshooting Tips:</h3>";
    echo "<ul>";
    echo "<li>Verify your InfinityFree database credentials in .env file</li>";
    echo "<li>Ensure the database has been imported to InfinityFree</li>";
    echo "<li>Check that the database user has proper permissions</li>";
    echo "<li>Confirm the database server is accessible (sometimes requires whitelisting)</li>";
    echo "</ul>";
}

echo "<br><h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Update your .env file with InfinityFree database credentials</li>";
echo "<li>Upload all files to your InfinityFree account</li>";
echo "<li>Visit your website URL to access the application</li>";
echo "</ol>";
?>