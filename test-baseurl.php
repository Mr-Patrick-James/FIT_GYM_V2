<?php
require_once 'api/config.php';

echo "BASE_URL: " . BASE_URL . "\n";

// Test the receipt URL
$testBookingId = 5;
$receiptFilename = 'receipt_5_2026-01-30_12-25-02.html';
$receiptPath = 'uploads/receipts/' . $receiptFilename;
$fullUrl = BASE_URL . '/' . $receiptPath;

echo "Receipt Path: $receiptPath\n";
echo "Full URL: $fullUrl\n";

// Check if file exists
$filePath = __DIR__ . '/uploads/receipts/' . $receiptFilename;
echo "File Path: $filePath\n";
echo "File exists: " . (file_exists($filePath) ? 'YES' : 'NO') . "\n";

if (file_exists($filePath)) {
    echo "File size: " . filesize($filePath) . " bytes\n";
}

// Test HTTP access
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
?>
