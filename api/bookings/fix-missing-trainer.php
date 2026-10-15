<?php
/**
 * One-time repair: assigns trainer_id to verified/expired bookings
 * that have no trainer set, using package_trainers as the source.
 *
 * Safe to run multiple times — only touches bookings where trainer_id IS NULL.
 */
require_once '../config.php';
require_once '../session.php';
requireAdmin();   // admin-only

$conn = getDBConnection();

// Find all verified/expired bookings with no trainer assigned
$bookings = $conn->query("
    SELECT b.id, b.package_id, b.status, b.name as client
    FROM bookings b
    WHERE b.trainer_id IS NULL
      AND b.status IN ('verified', 'expired')
      AND b.package_id IS NOT NULL
    ORDER BY b.id
");

$fixed   = 0;
$skipped = 0;

$ptStmt = $conn->prepare(
    "SELECT pt.trainer_id FROM package_trainers pt
     JOIN trainers t ON t.id = pt.trainer_id
     WHERE pt.package_id = ? AND t.is_active = 1
     ORDER BY pt.trainer_id ASC LIMIT 1"
);

$upStmt = $conn->prepare("UPDATE bookings SET trainer_id = ? WHERE id = ?");

while ($b = $bookings->fetch_assoc()) {
    $ptStmt->bind_param("i", $b['package_id']);
    $ptStmt->execute();
    $ptResult = $ptStmt->get_result();
    $pt = $ptResult->fetch_assoc();

    if ($pt) {
        $tid = (int)$pt['trainer_id'];
        $upStmt->bind_param("ii", $tid, $b['id']);
        $upStmt->execute();
        echo "FIXED  booking_id={$b['id']} client={$b['client']} status={$b['status']} -> trainer_id=$tid\n";
        $fixed++;
    } else {
        echo "SKIP   booking_id={$b['id']} client={$b['client']} pkg={$b['package_id']} — no trainer in package_trainers\n";
        $skipped++;
    }
}

$ptStmt->close();
$upStmt->close();
$conn->close();

echo "\nDone. Fixed=$fixed Skipped=$skipped\n";
