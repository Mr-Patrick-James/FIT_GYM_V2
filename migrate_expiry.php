<?php
require_once 'api/config.php';

try {
    $conn = getDBConnection();
    
    // Check if column exists first
    $check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'expires_at'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE bookings ADD COLUMN expires_at DATETIME AFTER booking_date");
        echo "Column 'expires_at' added to bookings table.\n";
    } else {
        echo "Column 'expires_at' already exists.\n";
    }

    // Optional: Populate expires_at for existing verified bookings
    $sql = "SELECT b.id, b.booking_date, b.created_at, p.duration 
            FROM bookings b 
            JOIN packages p ON b.package_id = p.id 
            WHERE b.status = 'verified' AND b.expires_at IS NULL";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "Updating existing verified bookings...\n";
        while ($row = $result->fetch_assoc()) {
            $startDate = $row['booking_date'] ?: $row['created_at'];
            $duration = $row['duration'];
            
            // Basic duration parsing
            $days = 0;
            if (stripos($duration, 'Day') !== false) {
                $days = (int)$duration;
            } elseif (stripos($duration, 'Week') !== false) {
                $days = (int)$duration * 7;
            } elseif (stripos($duration, 'Month') !== false) {
                $days = (int)$duration * 30;
            } elseif (stripos($duration, 'Year') !== false) {
                $days = (int)$duration * 365;
            }
            
            if ($days > 0) {
                $expiresAt = date('Y-m-d H:i:s', strtotime($startDate . " + $days days"));
                $updateStmt = $conn->prepare("UPDATE bookings SET expires_at = ? WHERE id = ?");
                $updateStmt->bind_param("si", $expiresAt, $row['id']);
                $updateStmt->execute();
            }
        }
        echo "Existing bookings updated.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
