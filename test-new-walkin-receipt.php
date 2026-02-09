<?php
// Test creating a new walk-in booking and receipt
require_once 'api/config.php';

echo "<h2>🧪 Test New Walk-in Booking & Receipt</h2>";

echo "<h3>Current Configuration:</h3>";
echo "<pre>";
echo "BASE_URL: " . BASE_URL . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";

// Test 1: Create a new walk-in booking
echo "<h3>Test 1: Creating New Walk-in Booking</h3>";
echo "<pre>";

$walkinData = [
    'customer_name' => 'Receipt URL Test Customer',
    'customer_email' => 'receipt.test@example.com',
    'customer_contact' => '09123456789',
    'package' => 'Walk-in Pass',
    'date' => date('Y-m-d'),
    'payment_method' => 'cash',
    'notes' => 'Testing receipt URL generation'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, BASE_URL . '/api/walkin/create.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($walkinData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);
if ($result && $result['success']) {
    echo "✅ Walk-in booking created: ID {$result['data']['id']}\n";
    $testBookingId = $result['data']['id'];
} else {
    echo "❌ Failed to create walk-in booking\n";
    echo "Response: " . $response . "\n";
    $testBookingId = null;
}

echo "</pre>";

// Test 2: Generate receipt
echo "<h3>Test 2: Generating Walk-in Receipt</h3>";
echo "<pre>";

if ($testBookingId) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, BASE_URL . '/api/receipt/generate-walkin.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['booking_id' => $testBookingId]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Receipt API Status: $httpCode\n";
    
    $result = json_decode($response, true);
    if ($result && $result['success']) {
        echo "✅ Walk-in receipt generated successfully!\n";
        echo "Booking ID: {$result['data']['booking_id']}\n";
        echo "Receipt URL: {$result['data']['receipt_url']}\n";
        echo "Receipt Filename: {$result['data']['receipt_filename']}\n";
        echo "HTML Length: " . strlen($result['data']['receipt_html']) . " characters\n";
        
        // Check if file exists
        $receiptPath = __DIR__ . '/uploads/receipts/' . $result['data']['receipt_filename'];
        if (file_exists($receiptPath)) {
            echo "✅ Receipt file saved to disk\n";
            echo "File size: " . filesize($receiptPath) . " bytes\n";
            
            // Test web accessibility
            $webUrl = BASE_URL . '/' . $result['data']['receipt_url'];
            echo "Web URL: $webUrl\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            $webResponse = curl_exec($ch);
            $webHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "HTTP Status: $webHttpCode\n";
            if ($webHttpCode === 200) {
                echo "✅ Receipt is accessible via web\n";
                echo "🔗 <a href='$webUrl' target='_blank'>View Receipt</a>\n";
            } else {
                echo "❌ Receipt not accessible via web\n";
            }
        } else {
            echo "❌ Receipt file not found on disk\n";
            echo "Expected path: $receiptPath\n";
        }
        
    } else {
        echo "❌ Failed to generate receipt\n";
        if ($result) {
            echo "Error: " . $result['message'] . "\n";
        } else {
            echo "Response: " . $response . "\n";
        }
    }
} else {
    echo "⚠️ Skipping receipt test - no booking created\n";
}

echo "</pre>";

echo "<h3>📋 Troubleshooting Steps:</h3>";
echo "<ol>";
echo "<li>Check if BASE_URL is correct: " . BASE_URL . "</li>";
echo "<li>Verify uploads/receipts directory exists and is writable</li>";
echo "<li>Check Apache configuration for URL rewriting</li>";
echo "<li>Test with different booking IDs</li>";
echo "<li>Check file permissions on uploads directory</li>";
echo "</ol>";

echo "<h3>🔧 Quick Fix Options:</h3>";
echo "<ul>";
echo "<li><strong>Option 1:</strong> Access receipts directly via file path</li>";
echo "<li><strong>Option 2:</strong> Fix BASE_URL configuration</li>";
echo "<li><strong>Option 3:</strong> Use absolute URLs in database</li>";
echo "<li><strong>Option 4:</strong> Create .htaccess rule for receipts</li>";
echo "</ul>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Test New Walk-in Receipt</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 14px; }
        h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-top: 30px; }
        .success { color: #22c55e; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        a { color: #3b82f6; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
</body>
</html>
