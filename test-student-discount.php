<?php
/**
 * Test script for Student Discount Feature
 * Tests all API endpoints related to student discounts
 */

echo "=== Student Discount Feature - API Test ===\n\n";

// Test API endpoints
$baseUrl = "http://localhost/FIT_GYM_V2";

// Test 1: Get student discount info
echo "1. Testing GET /api/packages/get-student-discount.php\n";
echo "   Getting discount info for package ID 22...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . "/api/packages/get-student-discount.php?package_id=22");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data['success']) {
    echo "   ✓ Success\n";
    echo "   - Package: " . $data['data']['package_name'] . "\n";
    echo "   - Original Price: ₱" . number_format($data['data']['original_price'], 2) . "\n";
    echo "   - Student Discount: " . $data['data']['student_discount_percentage'] . "%\n";
    echo "   - Discount Amount: ₱" . number_format($data['data']['discount_amount'], 2) . "\n";
    echo "   - Student Price: ₱" . number_format($data['data']['student_price'], 2) . "\n\n";
} else {
    echo "   ✗ Failed: " . $data['message'] . "\n\n";
}

// Test 2: Show example discount scenarios
echo "2. Example Discount Scenarios\n\n";

$scenarios = [
    [
        'package' => 'Weekly Pass',
        'price' => 150,
        'discount' => 10
    ],
    [
        'package' => 'Annual Membership',
        'price' => 800,
        'discount' => 10
    ],
    [
        'package' => 'Monthly Membership',
        'price' => 1500,
        'discount' => 10
    ]
];

foreach ($scenarios as $scenario) {
    $discount_amount = ($scenario['price'] * $scenario['discount']) / 100;
    $student_price = $scenario['price'] - $discount_amount;
    
    echo "   Package: " . $scenario['package'] . "\n";
    echo "   - Original Price: ₱" . number_format($scenario['price'], 2) . "\n";
    echo "   - Student Discount: " . $scenario['discount'] . "%\n";
    echo "   - Discount Amount: ₱" . number_format($discount_amount, 2) . "\n";
    echo "   - Student Pays: ₱" . number_format($student_price, 2) . "\n";
    echo "   - Savings: ₱" . number_format($discount_amount, 2) . "\n\n";
}

// Test 3: Database verification
echo "3. Database Schema Verification\n";

require_once 'api/config.php';

try {
    $conn = getDBConnection();
    
    // Check bookings table columns
    echo "   Checking bookings table...\n";
    $result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'is_student'");
    echo "   - is_student: " . ($result->num_rows > 0 ? "✓" : "✗") . "\n";
    
    $result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'student_id_url'");
    echo "   - student_id_url: " . ($result->num_rows > 0 ? "✓" : "✗") . "\n";
    
    $result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'student_discount_applied'");
    echo "   - student_discount_applied: " . ($result->num_rows > 0 ? "✓" : "✗") . "\n";
    
    // Check packages table columns
    echo "\n   Checking packages table...\n";
    $result = $conn->query("SHOW COLUMNS FROM packages LIKE 'student_discount'");
    echo "   - student_discount: " . ($result->num_rows > 0 ? "✓" : "✗") . "\n";
    
    // Show current package discounts
    echo "\n   Current package discounts:\n";
    $result = $conn->query("SELECT id, name, price, COALESCE(student_discount, 0) as discount FROM packages WHERE is_active = 1 ORDER BY id");
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $discount_amount = ($row['price'] * $row['discount']) / 100;
            $student_price = $row['price'] - $discount_amount;
            echo "   - " . $row['name'] . "\n";
            echo "     Original: ₱" . number_format($row['price'], 2) . " | Discount: " . $row['discount'] . "% | Student: ₱" . number_format($student_price, 2) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ✗ Database Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "\nFor more information, see:\n";
echo "- STUDENT_DISCOUNT_GUIDE.md - Full user guide\n";
echo "- STUDENT_DISCOUNT_SUMMARY.md - Implementation summary\n";
?>
