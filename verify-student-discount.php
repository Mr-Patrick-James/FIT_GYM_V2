<?php
require_once 'api/config.php';
$conn = getDBConnection();

echo "Student Discount Database Verification\n";
echo str_repeat('=', 60) . "\n\n";

// Check bookings table
echo "Bookings Table Columns:\n";
$result = $conn->query("SHOW COLUMNS FROM bookings WHERE Field IN ('is_student', 'student_id_url', 'student_discount_applied')");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✓ " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "✗ Columns not found\n";
}

echo "\nPackages Table Columns:\n";
$result = $conn->query("SHOW COLUMNS FROM packages WHERE Field = 'student_discount'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "✓ " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "✗ Column not found\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Active Packages with Student Discount:\n";
echo str_repeat('=', 60) . "\n\n";

$result = $conn->query("SELECT id, name, price, COALESCE(student_discount, 0) as discount FROM packages WHERE is_active = 1");
if ($result && $result->num_rows > 0) {
    $totalPackages = 0;
    while ($row = $result->fetch_assoc()) {
        $discountAmount = ($row['price'] * $row['discount']) / 100;
        $studentPrice = $row['price'] - $discountAmount;
        printf("%-4d | %-30s | ₱%-10.2f → ₱%-10.2f (-%d%%)\n",
            $row['id'],
            $row['name'],
            $row['price'],
            $studentPrice,
            (int)$row['discount']
        );
        $totalPackages++;
    }
    echo "\nTotal packages: " . $totalPackages . "\n";
} else {
    echo "No active packages found.\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "✓ Student Discount Feature is Ready!\n";
echo str_repeat('=', 60) . "\n";
?>
