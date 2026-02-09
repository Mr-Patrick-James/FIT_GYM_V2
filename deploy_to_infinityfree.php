<?php
/**
 * Deployment Helper Script for InfinityFree
 * This script checks if the application is ready for deployment to InfinityFree
 */

echo "<h2>FitPay Dashboard - InfinityFree Deployment Checker</h2>";

// Check PHP version (InfinityFree typically supports PHP 7.4+)
$phpVersion = phpversion();
echo "<p>PHP Version: $phpVersion " . (version_compare($phpVersion, '7.4', '>=') ? '✓' : '⚠ Needs 7.4+') . "</p>";

// Check required extensions
$extensions = ['mysqli', 'pdo', 'json', 'curl', 'gd'];
echo "<p>Required Extensions:</p><ul>";
foreach ($extensions as $ext) {
    $status = extension_loaded($ext) ? '✓' : '✗';
    echo "<li>$ext: $status</li>";
}
echo "</ul>";

// Check file permissions
$uploadDir = __DIR__ . '/uploads';
$hasUploadDir = is_dir($uploadDir);
$canWriteUpload = $hasUploadDir ? is_writable($uploadDir) : false;
echo "<p>Uploads Directory: " . ($hasUploadDir ? 'Exists' : 'Missing') . " | Writable: " . ($canWriteUpload ? 'Yes' : 'No') . "</p>";

// Check if .env file exists
$envFile = __DIR__ . '/.env';
$hasEnvFile = file_exists($envFile);
echo "<p>.env File: " . ($hasEnvFile ? 'Exists' : 'Missing') . "</p>";

// Check session directory
$sessionDir = sys_get_temp_dir();
$canWriteSession = is_writable($sessionDir);
echo "<p>Session Storage: " . ($canWriteSession ? 'Writable' : 'Not Writable') . "</p>";

// Display important configuration values
echo "<h3>Current Configuration:</h3>";
if ($hasEnvFile) {
    $envContent = file_get_contents($envFile);
    preg_match('/DB_HOST=(.*)/', $envContent, $dbHostMatches);
    preg_match('/DB_NAME=(.*)/', $envContent, $dbNameMatches);
    
    echo "<p>Current DB Host: " . ($dbHostMatches[1] ?? 'Not found') . "</p>";
    echo "<p>Current DB Name: " . ($dbNameMatches[1] ?? 'Not found') . "</p>";
}

// Suggest next steps
echo "<h3>Deployment Steps:</h3>";
echo "<ol>";
echo "<li>Export your database using phpMyAdmin</li>";
echo "<li>Create an account at InfinityFree (https://infinityfree.net/)</li>";
echo "<li>Set up a new website and note your database credentials</li>";
echo "<li>Import your database to InfinityFree's phpMyAdmin</li>";
echo "<li>Update your .env file with InfinityFree database credentials</li>";
echo "<li>Upload all files to your InfinityFree account</li>";
echo "</ol>";

echo "<h3>Important Notes:</h3>";
echo "<ul>";
echo "<li>Default admin login: admin@martinezfitness.com / admin123</li>";
echo "<li>Default user login: user@martinezfitness.com / user123</li>";
echo "<li>Remember to update email settings for SMTP to work properly</li>";
echo "<li>Check file permissions after upload (uploads folder needs write access)</li>";
echo "</ul>";

echo "<p><strong>Note:</strong> Free hosting services like InfinityFree may have limitations on email sending, file uploads, and processing power. Consider upgrading for production use.</p>";
?>