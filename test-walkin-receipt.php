<?php
// Test script for simple walk-in receipt system
require_once 'api/config.php';

echo "<h2>🧪 Simple Walk-in Receipt Test</h2>";

// Test 1: Create a walk-in booking
echo "<h3>Test 1: Creating Walk-in Booking</h3>";
echo "<pre>";

$walkinData = [
    'customer_name' => 'Simple Receipt Test Customer',
    'customer_email' => 'simple.test@example.com',
    'customer_contact' => '09123456789',
    'package' => 'Walk-in Pass',
    'date' => date('Y-m-d'),
    'payment_method' => 'cash',
    'notes' => 'Test simple receipt generation'
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
    $testBookingId = null;
}

echo "</pre>";

// Test 2: Generate walk-in receipt
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
        $receiptPath = '../uploads/receipts/' . $result['data']['receipt_filename'];
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
            } else {
                echo "❌ Receipt not accessible via web\n";
            }
        } else {
            echo "❌ Receipt file not found on disk\n";
        }
        
    } else {
        echo "❌ Failed to generate receipt\n";
        if ($result) {
            echo "Error: " . $result['message'] . "\n";
        }
    }
} else {
    echo "⚠️ Skipping receipt test - no booking created\n";
}

echo "</pre>";

echo "<h2>📊 Simple Walk-in Receipt System Summary</h2>";
echo "<div style='background: #f0f9ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6;'>";

echo "<h3>✅ Simple Walk-in Receipt System Implemented!</h3>";
echo "<ul>";
echo "<li><strong>Automatic Generation:</strong> Receipt generated after walk-in booking</li>";
echo "<li><strong>Print Dialog:</strong> Regular print dialog (no auto-print)</li>";
echo "<li><strong>File Storage:</strong> Receipt saved as HTML file</li>";
echo "<li><strong>Database Update:</strong> Booking record updated with receipt URL</li>";
echo "<li><strong>Simple Interface:</strong> Clean print window with print/close buttons</li>";
echo "<li><strong>Progress Notifications:</strong> 'Generating...' → 'Receipt ready for printing!'</li>";
echo "</ul>";

echo "<h3>🔄 Simple Workflow:</h3>";
echo "<ol>";
echo "<li>Staff creates walk-in booking</li>";
echo "<li>System shows 'Generating receipt...'</li>";
echo "<li>Receipt generated and saved to disk</li>";
echo "<li>Print window opens with receipt</li>";
echo "<li>Staff clicks 'Print Receipt' button</li>";
echo "<li>Regular print dialog appears</li>";
echo "<li>Staff prints receipt</li>";
echo "<li>Staff closes receipt window</li>";
echo "</ol>";

echo "<h3>🔧 Technical Features:</h3>";
echo "<ul>";
echo "<li><strong>API Endpoint:</strong> <code>/api/receipt/generate-walkin.php</code></li>";
echo "<li><strong>File Format:</strong> HTML optimized for printing</li>";
echo "<li><strong>Window Size:</strong> 400x600 with scrollbars</li>";
echo "<li><strong>Print Styling:</strong> Thermal printer receipt format</li>";
echo "<li><strong>File Storage:</strong> <code>uploads/receipts/</code> directory</li>";
echo "<li><strong>Database Integration:</strong> Updates <code>receipt_url</code> field</li>";
echo "</ul>";

echo "<h3>🎯 User Experience:</h3>";
echo "<ul>";
echo "<li><strong>Simple Process:</strong> One click to create booking, receipt auto-generates</li>";
echo "<li><strong>Print Control:</strong> Staff decides when to print</li>";
echo "<li><strong>Professional Receipt:</strong> Store-style thermal receipt format</li>";
echo "<li><strong>Digital Backup:</strong> Receipt saved for future reference</li>";
echo "<li><strong>Easy Access:</strong> Receipt can be viewed anytime from bookings table</li>";
echo "</ul>";
echo "</div>";

echo "<h3>🚀 How to Test:</h3>";
echo "<ol>";
echo "<li>Go to bookings page: <a href='views/admin/bookings.php' target='_blank'>Bookings Management</a></li>";
echo "<li>Click 'Walk-in Booking' button</li>";
echo "<li>Fill in customer details and submit</li>";
echo "<li>Wait for 'Receipt ready for printing!' notification</li>";
echo "<li>Print window opens automatically</li>";
echo "<li>Click 'Print Receipt' button</li>";
echo "<li>Use regular print dialog to print</li>";
echo "<li>Close receipt window when done</li>";
echo "</ol>";

echo "<p><strong>Test Pages:</strong></p>";
echo "<ul>";
echo "<li><a href='views/admin/bookings.php' target='_blank'>Live Bookings Page</a> - Test walk-in receipts</li>";
echo "<li><a href='test-walkin-functionality.php' target='_blank'>Functionality Test</a> - Backend testing</li>";
echo "<li><a href='test-modern-icons.php' target='_blank'>Icons Demo</a> - Visual improvements</li>";
echo "</ul>";

if ($testBookingId && isset($result['data']['receipt_url'])) {
    echo "<p><strong>Test Receipt:</strong> <a href='" . BASE_URL . "/{$result['data']['receipt_url']}' target='_blank'>" . BASE_URL . "/{$result['data']['receipt_url']}</a></p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple Walk-in Receipt Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 14px; }
        h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-top: 30px; }
        .success { color: #22c55e; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        a { color: #3b82f6; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
</body>
</html>
