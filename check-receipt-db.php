<?php
require_once 'api/config.php';

echo "<h2>🔍 Check Receipt Database Records</h2>";

try {
    $conn = getDBConnection();
    
    // Check booking ID 5
    $sql = "SELECT id, name, email, receipt_url, created_at FROM bookings WHERE id = 5";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        echo "<h3>Booking ID 5 Details:</h3>";
        echo "<pre>";
        echo "ID: " . $booking['id'] . "\n";
        echo "Name: " . $booking['name'] . "\n";
        echo "Email: " . $booking['email'] . "\n";
        echo "Receipt URL: " . ($booking['receipt_url'] ?? 'NULL') . "\n";
        echo "Created At: " . $booking['created_at'] . "\n";
        echo "</pre>";
        
        if ($booking['receipt_url']) {
            $receiptPath = $booking['receipt_url'];
            $fullPath = __DIR__ . '/' . $receiptPath;
            
            echo "<h3>Receipt File Check:</h3>";
            echo "<pre>";
            echo "Database Path: $receiptPath\n";
            echo "Full Path: $fullPath\n";
            echo "File Exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
            
            if (file_exists($fullPath)) {
                echo "File Size: " . filesize($fullPath) . " bytes\n";
                echo "File Type: " . mime_content_type($fullPath) . "\n";
            }
            echo "</pre>";
            
            // Test web access
            $webUrl = BASE_URL . '/' . $receiptPath;
            echo "<h3>Web Access Test:</h3>";
            echo "<pre>";
            echo "Web URL: $webUrl\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "HTTP Status: $httpCode\n";
            echo "</pre>";
            
            if ($httpCode === 200) {
                echo "<p>✅ <a href='$webUrl' target='_blank'>View Receipt</a></p>";
            } else {
                echo "<p>❌ Receipt not accessible via web</p>";
            }
        } else {
            echo "<p>⚠️ No receipt URL in database</p>";
        }
    } else {
        echo "<p>❌ Booking ID 5 not found</p>";
    }
    
    // Check all bookings with receipts
    echo "<h3>All Bookings with Receipts:</h3>";
    echo "<pre>";
    $sql = "SELECT id, name, receipt_url FROM bookings WHERE receipt_url IS NOT NULL AND receipt_url != '' ORDER BY id";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        echo "Booking {$row['id']}: {$row['name']} -> {$row['receipt_url']}\n";
    }
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<h3>Available Receipt Files:</h3>";
echo "<pre>";
$files = glob(__DIR__ . '/uploads/receipts/*');
foreach ($files as $file) {
    $filename = basename($file);
    $size = filesize($file);
    $type = mime_content_type($file);
    echo "$filename ($size bytes, $type)\n";
}
echo "</pre>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Check Receipt Database</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 8px; }
        h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px; }
        a { color: #007bff; }
    </style>
</head>
<body>
</body>
</html>
