<?php
require_once '../config.php';
require_once '../session.php';

ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    $conn = getDBConnection();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendResponse(false, 'Invalid request data', null, 400);
    }

    $bookingId   = (int)($input['booking_id'] ?? 0);
    $newPackage  = trim($input['package'] ?? '');
    $newDate     = trim($input['date'] ?? '');
    $newContact  = trim($input['contact'] ?? '');
    $newNotes    = trim($input['notes'] ?? '');
    $newReceipt  = trim($input['receipt'] ?? '');
    $userId      = (int)$_SESSION['user_id'];

    if (!$bookingId || !$newPackage || !$newDate || !$newContact) {
        sendResponse(false, 'Missing required fields', null, 400);
    }

    // Fetch the booking and verify ownership + pending status
    $bStmt = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
    $bStmt->bind_param("ii", $bookingId, $userId);
    $bStmt->execute();
    $booking = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();

    if (!$booking) {
        sendResponse(false, 'Booking not found', null, 404);
    }

    if ($booking['status'] !== 'pending') {
        sendResponse(false, 'Only pending bookings can be edited', null, 409);
    }

    // Fetch the new package
    $pStmt = $conn->prepare("SELECT id, name, price, duration FROM packages WHERE name = ? AND is_active = 1 LIMIT 1");
    $pStmt->bind_param("s", $newPackage);
    $pStmt->execute();
    $package = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();

    if (!$package) {
        sendResponse(false, 'Package not found or inactive', null, 404);
    }

    // Pre-compute expiry based on booking_date + new duration
    $days = 0;
    $dur  = $package['duration'];
    if (stripos($dur, 'Year')  !== false) $days = (int)$dur * 365;
    elseif (stripos($dur, 'Month') !== false) $days = (int)$dur * 30;
    elseif (stripos($dur, 'Week')  !== false) $days = (int)$dur * 7;
    elseif (stripos($dur, 'Day')   !== false) $days = (int)$dur;
    else $days = (int)$dur;

    $expiresAt = $days > 0 ? date('Y-m-d H:i:s', strtotime($newDate . " + $days days")) : null;
    
    // Determine the receipt to use
    $finalReceipt = !empty($newReceipt) ? $newReceipt : $booking['receipt_url'];

    // Update the booking with new info
    $uStmt = $conn->prepare(
        "UPDATE bookings SET 
            package_id = ?, 
            package_name = ?, 
            amount = ?, 
            booking_date = ?, 
            contact = ?, 
            notes = ?, 
            receipt_url = ?, 
            expires_at = ? 
        WHERE id = ?"
    );
    $uStmt->bind_param(
        "isdsssssi", 
        $package['id'], 
        $package['name'], 
        $package['price'], 
        $newDate, 
        $newContact, 
        $newNotes, 
        $finalReceipt, 
        $expiresAt, 
        $bookingId
    );
    $result = $uStmt->execute();
    $uStmt->close();

    if (!$result) {
        sendResponse(false, 'Failed to edit booking: ' . $conn->error, null, 500);
    }

    // Notify admins
    try {
        $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
        if ($adminStmt) {
            $adminStmt->execute();
            $adminRes = $adminStmt->get_result();
            while ($adminRow = $adminRes->fetch_assoc()) {
                createNotification(
                    (int)$adminRow['id'],
                    'Booking Updated',
                    $booking['name'] . ' edited their pending booking parameters.',
                    'info'
                );
            }
            $adminStmt->close();
        }
    } catch (Throwable $e) {
        error_log("Failed to notify admin about booking edit: " . $e->getMessage());
    }

    sendResponse(true, 'Booking edited successfully');

} catch (Exception $e) {
    error_log("Error editing booking: " . $e->getMessage());
    sendResponse(false, 'Error editing booking: ' . $e->getMessage(), null, 500);
}
?>
