<?php
require_once 'api/config.php';

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

$conn = getDB();

echo "Starting payment cleanup...\n";

// 1. Find duplicates
$sql = "SELECT booking_id, COUNT(*) as count 
        FROM payments 
        WHERE booking_id IS NOT NULL 
        GROUP BY booking_id 
        HAVING count > 1";

$result = $conn->query($sql);
if (!$result) {
    die("Error finding duplicates: " . $conn->error);
}

$duplicates = [];
while ($row = $result->fetch_assoc()) {
    $duplicates[] = $row['booking_id'];
}

echo "Found " . count($duplicates) . " bookings with duplicate payments.\n";

foreach ($duplicates as $booking_id) {
    echo "Cleaning booking ID: $booking_id\n";
    
    // Keep the latest one
    $keepSql = "SELECT id FROM payments WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($keepSql);
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $keepRes = $stmt->get_result()->fetch_assoc();
    if (!$keepRes) continue;
    $keepId = $keepRes['id'];
    
    // Delete others
    $deleteSql = "DELETE FROM payments WHERE booking_id = ? AND id != ?";
    $delStmt = $conn->prepare($deleteSql);
    $delStmt->bind_param("ii", $booking_id, $keepId);
    $delStmt->execute();
    echo "  Deleted " . $conn->affected_rows . " duplicate records.\n";
}

// 2. Add UNIQUE constraint to booking_id and transaction_id
echo "Adding UNIQUE constraints...\n";

// First, check if constraints already exist to avoid errors
$checkSql = "SHOW INDEX FROM payments WHERE Column_name = 'booking_id' AND Non_unique = 0";
$res = $conn->query($checkSql);
if ($res && $res->num_rows == 0) {
    if ($conn->query("ALTER TABLE payments ADD UNIQUE (booking_id)")) {
        echo "  Added UNIQUE constraint to booking_id.\n";
    } else {
        echo "  Failed to add UNIQUE constraint to booking_id: " . $conn->error . "\n";
    }
} else {
    echo "  UNIQUE constraint for booking_id already exists or error checking.\n";
}

$checkSql = "SHOW INDEX FROM payments WHERE Column_name = 'transaction_id' AND Non_unique = 0";
$res = $conn->query($checkSql);
if ($res && $res->num_rows == 0) {
    if ($conn->query("ALTER TABLE payments ADD UNIQUE (transaction_id)")) {
        echo "  Added UNIQUE constraint to transaction_id.\n";
    } else {
        echo "  Failed to add UNIQUE constraint to transaction_id: " . $conn->error . "\n";
    }
} else {
    echo "  UNIQUE constraint for transaction_id already exists or error checking.\n";
}

echo "Cleanup complete!\n";
$conn->close();
?>
