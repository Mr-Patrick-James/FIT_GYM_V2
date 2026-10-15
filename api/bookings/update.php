<?php
require_once '../config.php';
require_once '../session.php';
require_once __DIR__ . '/../package-dates.php';

// Allow POST or PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in and is admin or manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    $conn = getDBConnection();
    
    // Get the request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Get the booking ID from the URL or JSON body
    $bookingId = (int)($_GET['id'] ?? ($input['id'] ?? 0));
    
    if (!$input) {
        sendResponse(false, 'Invalid request data', null, 400);
    }
    
    $status = $input['status'] ?? null;
    $notes = $input['notes'] ?? null;
    $trainerId = $input['trainer_id'] ?? null;
    
    if (!$status) {
        sendResponse(false, 'Status is required', null, 400);
    }
    
    // Validate status
    if (!in_array($status, ['pending', 'verified', 'rejected', 'upgraded'])) {
        sendResponse(false, 'Invalid status', null, 400);
    }
    
    if ($bookingId <= 0) sendResponse(false, 'A valid booking ID is required.', null, 400);

    // Validate before changing status. Keep the expiry purchased at booking time even
    // if the package duration is edited later; only backfill a missing expiry.
    $verificationExpiry = null;
    if ($status === 'verified') {
        $dateStmt = $conn->prepare("SELECT b.*, p.duration, p.name as package_name FROM bookings b
            LEFT JOIN packages p ON b.package_id = p.id WHERE b.id = ?");
        $dateStmt->bind_param('i', $bookingId);
        $dateStmt->execute();
        $dateBooking = $dateStmt->get_result()->fetch_assoc();
        if (!$dateBooking) sendResponse(false, 'Booking not found.', null, 404);
        if (!packageCalendarDate($dateBooking['booking_date'] ?? null)) {
            sendResponse(false, 'The booking has an invalid start date. Please correct it before verification.', null, 400);
        }
        $verificationExpiry = $dateBooking['expires_at'];
        if ($verificationExpiry === null) {
            $dates = requirePackageBookingDates($dateBooking['booking_date'], $dateBooking['duration'] ?? '');
            $verificationExpiry = $dates['expires_at'];
        }
    }

    // Update the booking
    if ($trainerId !== null) {
        $sql = "UPDATE bookings SET status = ?, notes = ?, trainer_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $notes, $trainerId, $bookingId);
    } else {
        // Don't overwrite trainer_id with NULL if none was passed
        $sql = "UPDATE bookings SET status = ?, notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $notes, $bookingId);
    }
    $result = $stmt->execute();
    
    if (!$result) {
        sendResponse(false, 'Failed to update booking', null, 500);
    }
    
    // If status is verified, also add to payments and set expiry date
    if ($status === 'verified') {
        // verified_at is a TIMESTAMP: let the DB store the actual instant, just like
        // created_at. A Manila wall-clock literal would shift it on UTC DB sessions.
        $verifiedStmt = $conn->prepare('UPDATE bookings SET verified_at = CURRENT_TIMESTAMP WHERE id = ?');
        $verifiedStmt->bind_param('i', $bookingId);
        $verifiedStmt->execute();

        // Get booking details to add to payments
        $sql = "SELECT b.*, p.duration, p.name as package_name FROM bookings b 
                LEFT JOIN packages p ON b.package_id = p.id 
                WHERE b.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $bookingResult = $stmt->get_result();
        $booking = $bookingResult->fetch_assoc();
        
        if ($booking) {
            $expiresAt = $verificationExpiry;
            if ($booking['expires_at'] === null) {
                // Never reset the start day or extend the purchased range on verification.
                $expiryStmt = $conn->prepare('UPDATE bookings SET expires_at = ? WHERE id = ? AND expires_at IS NULL');
                $expiryStmt->bind_param('si', $expiresAt, $bookingId);
                $expiryStmt->execute();
            }

            // Add to payments table including the receipt_url
            // Use REPLACE INTO or INSERT ... ON DUPLICATE KEY UPDATE to avoid duplicates
            $paymentSql = "INSERT INTO payments (user_id, booking_id, amount, status, payment_method, transaction_id, receipt_url, created_at) 
                           VALUES (?, ?, ?, 'completed', 'Booking Payment', CONCAT('BK_', ?), ?, NOW())
                           ON DUPLICATE KEY UPDATE 
                           amount = VALUES(amount),
                           status = VALUES(status),
                           receipt_url = VALUES(receipt_url)";
            $paymentStmt = $conn->prepare($paymentSql);
            if ($paymentStmt) {
                $paymentStmt->bind_param("iidss", 
                    $booking['user_id'],
                    $booking['id'],
                    $booking['amount'],
                    $booking['id'],
                    $booking['receipt_url']
                );
                $paymentStmt->execute();
            }

            // Send verification email to user
            try {
                require_once '../email.php';
                sendBookingVerificationEmail([
                    'user_email' => $booking['email'],
                    'user_name' => $booking['name'],
                    'package_name' => $booking['package_name'],
                    'expiry_date' => $expiresAt ?? null
                ]);
            } catch (Throwable $e) {
                error_log("Failed to send booking verification email: " . $e->getMessage());
            }

            // Auto-assign trainer from package_trainers if none was manually assigned
            if (empty($booking['trainer_id'])) {
                $ptQuery = $conn->prepare(
                    "SELECT pt.trainer_id FROM package_trainers pt
                     JOIN trainers t ON t.id = pt.trainer_id
                     WHERE pt.package_id = ? AND t.is_active = 1
                     ORDER BY pt.trainer_id ASC LIMIT 1"
                );
                if ($ptQuery) {
                    $ptQuery->bind_param("i", $booking['package_id']);
                    $ptQuery->execute();
                    $ptResult = $ptQuery->get_result();
                    if ($ptRow = $ptResult->fetch_assoc()) {
                        $autoTrainerId = (int)$ptRow['trainer_id'];
                        $conn->query("UPDATE bookings SET trainer_id = $autoTrainerId WHERE id = $bookingId");
                        $booking['trainer_id'] = $autoTrainerId;
                    }
                    $ptQuery->close();
                }
            }

            // Notify assigned trainer about the new client
            if (!empty($booking['trainer_id'])) {
                try {
                    $trainerQ = $conn->query("SELECT t.email, t.name, t.user_id FROM trainers t WHERE t.id = " . (int)$booking['trainer_id']);
                    if ($trainerQ && $trainerRow = $trainerQ->fetch_assoc()) {
                        // In-app notification (do this before closing conn)
                        $notifTitle = 'New Client Assigned';
                        $notifMsg = $booking['name'] . ' has been verified on your package: ' . $booking['package_name'];
                        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')");
                        $notifStmt->bind_param("iss", $trainerRow['user_id'], $notifTitle, $notifMsg);
                        $notifStmt->execute();

                        // Email (after all DB work)
                        try {
                            require_once '../email.php';
                            sendTrainerNewClientEmail(
                                $trainerRow['email'],
                                $trainerRow['name'],
                                $booking['name'],
                                $booking['package_name'],
                                $expiresAt ?? null
                            );
                        } catch (Throwable $e) {
                            error_log("Failed to send trainer new client email: " . $e->getMessage());
                        }
                    }
                } catch (Throwable $e) {
                    error_log("Failed to notify trainer: " . $e->getMessage());
                }
            }

            // Notify the member
            try {
                $memberNotifStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'Booking Verified', 'Your booking has been verified! Your membership is now active.', 'success')");
                $memberNotifStmt->bind_param("i", $booking['user_id']);
                $memberNotifStmt->execute();
            } catch (Throwable $e) {
                error_log("Failed to notify member: " . $e->getMessage());
            }

            // Notify admins about booking verification
            try {
                $currentAdminId = $_SESSION['user_id'] ?? null;
                $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' " . ($currentAdminId ? "AND id != ?" : ""));
                if ($adminStmt) {
                    if ($currentAdminId) {
                        $adminStmt->bind_param("i", $currentAdminId);
                    }
                    $adminStmt->execute();
                    $adminRes = $adminStmt->get_result();
                    while ($adminRow = $adminRes->fetch_assoc()) {
                        $adminId = (int)$adminRow['id'];
                        createNotification(
                            $adminId,
                            'Booking Verified',
                            $booking['name'] . ' booking verified for: ' . $booking['package_name'] . '.',
                            'success'
                        );
                    }
                    $adminStmt->close();
                }
            } catch (Throwable $e) {
                error_log("Failed to notify admins about booking verification: " . $e->getMessage());
            }
        }
    } elseif ($status === 'rejected') {
        try {
            $sql = "SELECT b.*, p.name as package_name FROM bookings b LEFT JOIN packages p ON b.package_id = p.id WHERE b.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $bookingId);
            $stmt->execute();
            $booking = $stmt->get_result()->fetch_assoc();
            
            if ($booking) {
                require_once '../email.php';
                sendBookingRejectionEmail([
                    'user_email' => $booking['email'],
                    'user_name' => $booking['name'],
                    'package_name' => $booking['package_name'],
                    'rejection_reason' => $notes
                ]);
            }

                // Notify admins about booking rejection
                try {
                    $currentAdminId = $_SESSION['user_id'] ?? null;
                    $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' " . ($currentAdminId ? "AND id != ?" : ""));
                    if ($adminStmt) {
                        if ($currentAdminId) {
                            $adminStmt->bind_param("i", $currentAdminId);
                        }
                        $adminStmt->execute();
                        $adminRes = $adminStmt->get_result();
                        while ($adminRow = $adminRes->fetch_assoc()) {
                            $adminId = (int)$adminRow['id'];
                            createNotification(
                                $adminId,
                                'Booking Rejected',
                                $booking['name'] . ' booking was rejected for: ' . $booking['package_name'] . '.',
                                'danger'
                            );
                        }
                        $adminStmt->close();
                    }
                } catch (Throwable $e) {
                    error_log("Failed to notify admins about booking rejection: " . $e->getMessage());
                }
        } catch (Throwable $e) {
            error_log("Failed to send booking rejection email: " . $e->getMessage());
        }
    }
    
    sendResponse(true, 'Booking updated successfully');

} catch (Exception $e) {
    error_log("Error updating booking: " . $e->getMessage());
    sendResponse(false, 'Error updating booking: ' . $e->getMessage(), null, 500);
}
?>