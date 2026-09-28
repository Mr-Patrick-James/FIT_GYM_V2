<?php
/**
 * One-time fix: Populate missing amounts in bookings table
 * Run this once to fix old bookings that don't have amount set
 */
require_once '../config.php';
require_once '../session.php';
requireAdmin();

try {
    $conn = getDBConnection();
    
    // Find bookings with NULL or 0 amount
    $sql = "SELECT b.id, b.package_id, p.price 
            FROM bookings b 
            JOIN packages p ON b.package_id = p.id 
            WHERE b.amount IS NULL OR b.amount = 0";
    
    $result = $conn->query($sql);
    $fixedCount = 0;
    
    if ($result->num_rows > 0) {
        $updateStmt = $conn->prepare("UPDATE bookings SET amount = ? WHERE id = ?");
        
        while ($row = $result->fetch_assoc()) {
            $bookingId = $row['id'];
            $price = (float)$row['price'];
            
            $updateStmt->bind_param("di", $price, $bookingId);
            if ($updateStmt->execute()) {
                $fixedCount++;
            }
        }
        
        $updateStmt->close();
    }
    
    $conn->close();
    
    sendResponse(true, "Fixed $fixedCount bookings with missing amounts", [
        'fixed_count' => $fixedCount
    ]);
    
} catch (Exception $e) {
    error_log("Error fixing missing amounts: " . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
