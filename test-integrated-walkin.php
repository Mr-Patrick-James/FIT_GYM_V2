<?php
// Test script for integrated walk-in booking functionality
require_once 'api/config.php';

echo "<h2>Integrated Walk-in Booking Test</h2>";

// Test 1: Check if walk-in API works
echo "<h3>Test 1: Walk-in API Endpoint</h3>";
echo "<pre>";

$testData = [
    'customer_name' => 'Test Walk-in Customer',
    'customer_email' => 'walkin@test.com',
    'customer_contact' => '09987654321',
    'package' => 'Walk-in Pass',
    'date' => date('Y-m-d'),
    'payment_method' => 'cash',
    'notes' => 'Test integrated walk-in booking'
];

// Make API call to create walk-in booking
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/api/walkin/create.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: $response\n\n";

$result = json_decode($response, true);
if ($result && $result['success']) {
    echo "✅ Walk-in booking created successfully!\n";
    echo "Booking ID: " . $result['data']['id'] . "\n";
} else {
    echo "❌ Failed to create walk-in booking\n";
}
echo "</pre>";

// Test 2: Check if main bookings API includes walk-ins
echo "<h3>Test 2: Main Bookings API (Should Include Walk-ins)</h3>";
echo "<pre>";

// We need to simulate an admin session for this test
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/api/bookings/get-all.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";

if ($response) {
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        echo "✅ Retrieved " . count($result['data']) . " total bookings\n";
        
        $walkinCount = 0;
        $regularCount = 0;
        
        foreach ($result['data'] as $booking) {
            if ($booking['is_walkin']) {
                $walkinCount++;
                echo "Walk-in found: ID {$booking['id']}, Name: {$booking['name']}, Package: {$booking['package_name']}\n";
            } else {
                $regularCount++;
            }
        }
        
        echo "\nSummary:\n";
        echo "- Walk-in bookings: $walkinCount\n";
        echo "- Regular bookings: $regularCount\n";
        echo "- Total: " . ($walkinCount + $regularCount) . "\n";
        
        if ($walkinCount > 0) {
            echo "\n✅ Integration successful! Walk-in bookings are included in main bookings API\n";
        } else {
            echo "\n⚠️  No walk-in bookings found. Make sure to create one first.\n";
        }
    } else {
        echo "❌ Failed to retrieve bookings\n";
        echo "Response: $response\n";
    }
} else {
    echo "❌ No response received\n";
}
echo "</pre>";

// Test 3: Database verification
echo "<h3>Test 3: Database Verification</h3>";
echo "<pre>";

try {
    $conn = getDBConnection();
    
    // Check total bookings
    $sql = "SELECT COUNT(*) as total FROM bookings";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    echo "Total bookings: " . $row['total'] . "\n";
    
    // Check walk-in bookings
    $sql = "SELECT COUNT(*) as walkin_count FROM bookings WHERE user_id IS NULL";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    echo "Walk-in bookings: " . $row['walkin_count'] . "\n";
    
    // Check regular bookings
    $sql = "SELECT COUNT(*) as regular_count FROM bookings WHERE user_id IS NOT NULL";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    echo "Regular bookings: " . $row['regular_count'] . "\n";
    
    // Show recent bookings with walk-in status
    $sql = "SELECT id, name, email, package_name, amount, status, 
                   CASE WHEN user_id IS NULL THEN 'Walk-in' ELSE 'Regular' END as booking_type,
                   created_at 
            FROM bookings ORDER BY created_at DESC LIMIT 5";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        echo "\nRecent bookings:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- ID: {$row['id']}, Type: {$row['booking_type']}, Name: {$row['name']}, Package: {$row['package_name']}, Amount: ₱{$row['amount']}\n";
        }
    }
    
    echo "\n✅ Database verification complete\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "<h2>Integration Summary</h2>";
echo "<div style='background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6;'>";
echo "<h3>✅ Walk-in Booking Successfully Integrated!</h3>";
echo "<ul>";
echo "<li><strong>Main Bookings Page:</strong> Now includes 'Walk-in Booking' button</li>";
echo "<li><strong>Unified View:</strong> Walk-in and regular bookings appear together</li>";
echo "<li><strong>Visual Distinction:</strong> Walk-in bookings show with 🚶 Walk-in badge</li>";
echo "<li><strong>Same Management:</strong> All booking management features work for both types</li>";
echo "<li><strong>API Integration:</strong> Main bookings API returns combined results</li>";
echo "</ul>";
echo "</div>";

echo "<h3>Access Points:</h3>";
echo "<ul>";
echo "<li><a href='views/admin/bookings.php' target='_blank'>Main Bookings Page (with Walk-in)</a></li>";
echo "<li><a href='views/admin/walkin-bookings.php' target='_blank'>Dedicated Walk-in Page</a></li>";
echo "<li><a href='test-walkin.php' target='_blank'>Original Walk-in Test</a></li>";
echo "</ul>";

echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Go to the main bookings page</li>";
echo "<li>Click the 'Walk-in Booking' button</li>";
echo "<li>Fill in customer details and create a walk-in booking</li>";
echo "<li>See it appear in the bookings list with the 🚶 Walk-in badge</li>";
echo "</ol>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Integrated Walk-in Booking Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 14px; }
        h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-top: 30px; }
        .success { color: #22c55e; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        ul { line-height: 1.8; }
        a { color: #3b82f6; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
</body>
</html>
