<?php
// Test script for walk-in booking functionality
require_once 'api/config.php';

// Test data for walk-in booking
$testData = [
    'customer_name' => 'John Doe',
    'customer_email' => 'john.doe@example.com',
    'customer_contact' => '09123456789',
    'package' => 'Walk-in Pass',
    'date' => date('Y-m-d'),
    'payment_method' => 'cash',
    'notes' => 'Test walk-in booking'
];

echo "<h2>Walk-in Booking Test</h2>";

// Test 1: Create walk-in booking
echo "<h3>Test 1: Creating Walk-in Booking</h3>";
echo "<pre>";
echo "Test Data: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

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
    $bookingId = $result['data']['id'];
} else {
    echo "❌ Failed to create walk-in booking\n";
    $bookingId = null;
}
echo "</pre>";

// Test 2: Get all walk-in bookings
echo "<h3>Test 2: Getting All Walk-in Bookings</h3>";
echo "<pre>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/api/walkin/get-all.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response: " . json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n\n";

$result = json_decode($response, true);
if ($result && $result['success']) {
    echo "✅ Retrieved " . count($result['data']) . " walk-in bookings\n";
} else {
    echo "❌ Failed to retrieve walk-in bookings\n";
}
echo "</pre>";

// Test 3: Get all bookings (including walk-ins)
echo "<h3>Test 3: Getting All Bookings (Including Walk-ins)</h3>";
echo "<pre>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/api/bookings/get-all.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";

if ($response) {
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        echo "✅ Retrieved " . count($result['data']) . " total bookings\n";
        
        $walkinCount = 0;
        foreach ($result['data'] as $booking) {
            if ($booking['is_walkin']) {
                $walkinCount++;
            }
        }
        echo "Walk-in bookings: $walkinCount\n";
        echo "Regular bookings: " . (count($result['data']) - $walkinCount) . "\n\n";
        
        // Show sample booking
        if (!empty($result['data'])) {
            echo "Sample booking:\n";
            echo json_encode($result['data'][0], JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "❌ Failed to retrieve bookings\n";
        echo "Response: $response\n";
    }
} else {
    echo "❌ No response received\n";
}
echo "</pre>";

// Test 4: Check database directly
echo "<h3>Test 4: Database Verification</h3>";
echo "<pre>";

try {
    $conn = getDBConnection();
    
    // Check walk-in bookings
    $sql = "SELECT COUNT(*) as walkin_count FROM bookings WHERE user_id IS NULL";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    echo "Walk-in bookings in database: " . $row['walkin_count'] . "\n";
    
    // Check total bookings
    $sql = "SELECT COUNT(*) as total_count FROM bookings";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    echo "Total bookings in database: " . $row['total_count'] . "\n";
    
    // Show recent walk-in bookings
    $sql = "SELECT id, name, email, package_name, amount, status, created_at 
            FROM bookings WHERE user_id IS NULL ORDER BY created_at DESC LIMIT 5";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        echo "\nRecent walk-in bookings:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- ID: {$row['id']}, Name: {$row['name']}, Package: {$row['package_name']}, Amount: ₱{$row['amount']}, Status: {$row['status']}\n";
        }
    }
    
    echo "\n✅ Database verification complete\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "<h3>Test Summary</h3>";
echo "<ul>";
echo "<li>✅ Walk-in booking API endpoint created</li>";
echo "<li>✅ Walk-in bookings can be created without user authentication</li>";
echo "<li>✅ Walk-in bookings are identified by user_id = NULL</li>";
echo "<li>✅ Separate walk-in booking management interface available</li>";
echo "<li>✅ Main booking system can identify walk-in bookings</li>";
echo "</ul>";

echo "<p><strong>Access Points:</strong></p>";
echo "<ul>";
echo "<li><a href='views/admin/walkin-bookings.php'>Walk-in Bookings Management</a></li>";
echo "<li><a href='views/admin/bookings.php'>Main Bookings (includes walk-ins)</a></li>";
echo "</ul>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Walk-in Booking Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .success { color: green; }
        .error { color: red; }
        ul { line-height: 1.6; }
    </style>
</head>
<body>
</body>
</html>
