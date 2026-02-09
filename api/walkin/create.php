<?php
require_once '../config.php';

// Allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(false, 'Invalid request data', null, 400);
    }
    
    // Extract walk-in customer data
    $customer_name = $input['customer_name'] ?? null;
    $customer_email = $input['customer_email'] ?? null;
    $customer_contact = $input['customer_contact'] ?? null;
    $package_name = $input['package'] ?? null;
    $booking_date = $input['date'] ?? date('Y-m-d'); // Default to today if not provided
    $notes = $input['notes'] ?? null;
    $receipt_url = $input['receipt'] ?? null;
    $payment_method = $input['payment_method'] ?? 'cash'; // Default payment method for walk-ins
    
    // Validate required fields
    if (!$customer_name || !$customer_email || !$customer_contact || !$package_name) {
        sendResponse(false, 'Missing required fields: customer_name, customer_email, customer_contact, package', null, 400);
    }
    
    // Validate email format
    if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format', null, 400);
    }
    
    // Get package info
    $packageQuery = "SELECT id, price FROM packages WHERE name = ? AND is_active = 1";
    $packageStmt = $conn->prepare($packageQuery);
    $packageStmt->bind_param("s", $package_name);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();
    $package = $packageResult->fetch_assoc();
    
    if (!$package) {
        sendResponse(false, 'Package not found or inactive', null, 404);
    }
    
    // Insert walk-in booking into database (user_id will be NULL)
    // Note: 'status' defaults to 'pending' in schema, but walk-ins should be 'verified'
    $sql = "INSERT INTO bookings (user_id, name, email, contact, package_id, package_name, amount, booking_date, notes, receipt_url, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')";
    
    $stmt = $conn->prepare($sql);
    $nullUserId = null; // This makes user_id NULL for walk-in customers
    $stmt->bind_param("isssisdsss", 
        $nullUserId,
        $customer_name,
        $customer_email,
        $customer_contact,
        $package['id'],
        $package_name,
        $package['price'],
        $booking_date,
        $notes,
        $receipt_url
    );
    
    $result = $stmt->execute();
    
    if (!$result) {
        sendResponse(false, 'Failed to create walk-in booking: ' . $conn->error, null, 500);
    }
    
    // Get the ID of the newly created booking
    $booking_id = $conn->insert_id;

    // Set expiry date for walk-in booking immediately since it's verified/completed
    $duration = null;
    $durationQuery = "SELECT duration FROM packages WHERE id = ?";
    $durationStmt = $conn->prepare($durationQuery);
    $durationStmt->bind_param("i", $package['id']);
    $durationStmt->execute();
    $durationResult = $durationStmt->get_result();
    if ($row = $durationResult->fetch_assoc()) {
        $duration = $row['duration'];
    }

    if ($duration) {
        $startDate = date('Y-m-d H:i:s');
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
            $updateSql = "UPDATE bookings SET booking_date = ?, expires_at = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssi", $startDate, $expiresAt, $booking_id);
            $updateStmt->execute();
        }
    }

    // Create payment record for walk-in transaction
    // Use transaction_id to ensure uniqueness
    $paymentSql = "INSERT INTO payments (user_id, booking_id, amount, status, payment_method, transaction_id, receipt_url, notes, created_at) 
                   VALUES (?, ?, ?, ?, ?, CONCAT('WALK_', ?), ?, ?, NOW())
                   ON DUPLICATE KEY UPDATE 
                   amount = VALUES(amount),
                   status = VALUES(status),
                   payment_method = VALUES(payment_method),
                   receipt_url = VALUES(receipt_url),
                   notes = VALUES(notes),
                   updated_at = NOW()";
    
    $paymentStmt = $conn->prepare($paymentSql);
    $paymentStatus = 'completed'; // Walk-ins typically pay immediately
    $paymentNotes = "Walk-in customer payment via " . $payment_method;
    
    $paymentStmt->bind_param("iidsssss", 
        $nullUserId,
        $booking_id,
        $package['price'],
        $paymentStatus,
        $payment_method,
        $booking_id,
        $receipt_url,
        $paymentNotes
    );
    
    $paymentResult = $paymentStmt->execute();
    
    if (!$paymentResult) {
        error_log("Warning: Failed to create payment record for walk-in booking $booking_id: " . $conn->error);
        // Don't fail the whole operation if payment record fails
    }
    
    sendResponse(true, 'Walk-in booking created successfully', [
        'id' => $booking_id,
        'customer_name' => $customer_name,
        'package' => $package_name,
        'amount' => $package['price'],
        'payment_method' => $payment_method,
        'payment_status' => $paymentStatus
    ]);

} catch (Exception $e) {
    error_log("Error creating walk-in booking: " . $e->getMessage());
    sendResponse(false, 'Error creating walk-in booking: ' . $e->getMessage(), null, 500);
}
?>
