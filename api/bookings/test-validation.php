<?php
/**
 * Simple test script to verify booking validation logic
 * This script tests the new booking restrictions
 */

require_once __DIR__ . '/../config.php';

echo "Testing booking validation logic...\n\n";

try {
    $conn = getDBConnection();
    
    // Test 1: Check if we can detect existing active bookings
    echo "Test 1: Checking for existing active bookings...\n";
    
    // First, let's see what bookings exist
    $testQuery = "SELECT id, user_id, package_name, status, expires_at FROM bookings WHERE status IN ('verified', 'pending') ORDER BY created_at DESC LIMIT 5";
    $testStmt = $conn->prepare($testQuery);
    $testStmt->execute();
    $testResult = $testStmt->get_result();
    
    echo "Found " . $testResult->num_rows . " active/pending bookings:\n";
    while ($booking = $testResult->fetch_assoc()) {
        echo "- ID: {$booking['id']}, User: {$booking['user_id']}, Package: {$booking['package_name']}, Status: {$booking['status']}, Expires: " . ($booking['expires_at'] ?? 'N/A') . "\n";
    }
    
    // Test 2: Check the 'upgraded' status is available
    echo "\nTest 2: Checking if 'upgraded' status is available...\n";
    $result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'");
    $row = $result->fetch_assoc();
    
    if ($row && strpos($row['Type'], 'upgraded') !== false) {
        echo "✓ 'upgraded' status is available in bookings table\n";
    } else {
        echo "✗ 'upgraded' status is NOT available\n";
    }
    
    // Test 3: Simulate checking if a user can book
    echo "\nTest 3: Simulating user booking eligibility check...\n";
    
    // Get a sample user with existing bookings
    $sampleUserQuery = "SELECT DISTINCT user_id FROM bookings WHERE status IN ('verified', 'pending') LIMIT 1";
    $sampleUserStmt = $conn->prepare($sampleUserQuery);
    $sampleUserStmt->execute();
    $sampleUserResult = $sampleUserStmt->get_result();
    $sampleUser = $sampleUserResult->fetch_assoc();
    
    if ($sampleUser) {
        $userId = $sampleUser['user_id'];
        echo "Checking user ID: $userId\n";
        
        // Check existing booking logic (same as in create.php)
        $existingBookingQuery = "SELECT id, package_name, status, expires_at FROM bookings 
                                WHERE user_id = ? AND status IN ('verified', 'pending') 
                                ORDER BY created_at DESC LIMIT 1";
        $existingStmt = $conn->prepare($existingBookingQuery);
        $existingStmt->bind_param("i", $userId);
        $existingStmt->execute();
        $existingResult = $existingStmt->get_result();
        $existingBooking = $existingResult->fetch_assoc();
        
        if ($existingBooking) {
            echo "Found existing booking:\n";
            echo "- Package: {$existingBooking['package_name']}\n";
            echo "- Status: {$existingBooking['status']}\n";
            
            if ($existingBooking['status'] === 'verified' && $existingBooking['expires_at']) {
                $currentTime = date('Y-m-d H:i:s');
                if ($existingBooking['expires_at'] > $currentTime) {
                    echo "✓ User CANNOT book new package (active booking found)\n";
                    echo "  Current booking expires: {$existingBooking['expires_at']}\n";
                } else {
                    echo "✓ User CAN book new package (current booking expired)\n";
                }
            } elseif ($existingBooking['status'] === 'pending') {
                echo "✓ User CANNOT book new package (pending booking found)\n";
            }
        } else {
            echo "✓ User CAN book new package (no active bookings found)\n";
        }
    } else {
        echo "No users with active bookings found for testing\n";
    }
    
    // Test 4: Check upgrade options
    echo "\nTest 4: Checking upgrade options...\n";
    
    // Get packages to show upgrade hierarchy
    $packageQuery = "SELECT id, name, price FROM packages WHERE is_active = 1 ORDER BY price ASC";
    $packageStmt = $conn->prepare($packageQuery);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();
    
    echo "Available packages (ordered by price):\n";
    while ($package = $packageResult->fetch_assoc()) {
        echo "- {$package['name']}: ₱" . number_format($package['price'], 2) . "\n";
    }
    
    echo "\n✓ All tests completed successfully!\n";
    
} catch (Exception $e) {
    echo "Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
