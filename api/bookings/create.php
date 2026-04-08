<?php
require_once '../config.php';
require_once '../session.php';

// Allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    $conn = getDBConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(false, 'Invalid request data', null, 400);
    }
    
    $package_name = $input['package'] ?? null;
    $booking_date = $input['date'] ?? null;
    $contact = $input['contact'] ?? null;
    $notes = $input['notes'] ?? null;
    $receipt_url = $input['receipt'] ?? null;
    $user_id = $_SESSION['user_id'];
    
    // Get user info
    $userQuery = "SELECT name, email FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }
    
    // Get package info
    $packageQuery = "SELECT id, price, duration FROM packages WHERE name = ? AND is_active = 1";
    $packageStmt = $conn->prepare($packageQuery);
    $packageStmt->bind_param("s", $package_name);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();
    $package = $packageResult->fetch_assoc();
    
    if (!$package) {
        sendResponse(false, 'Package not found or inactive', null, 404);
    }
    
    // Validate required fields
    if (!$package_name || !$booking_date || !$contact) {
        sendResponse(false, 'Missing required fields', null, 400);
    }

    // First, auto-expire any verified bookings for this user that have already passed their expiry date
    $conn->query("UPDATE bookings SET status = 'expired' WHERE user_id = $user_id AND status = 'verified' AND expires_at IS NOT NULL AND expires_at < NOW()");

    // Check if user already has an active (non-expired) verified or pending booking
    $existingBookingQuery = "SELECT id, package_name, status, expires_at FROM bookings 
                            WHERE user_id = ? AND status IN ('verified', 'pending')
                            ORDER BY created_at DESC LIMIT 1";
    $existingStmt = $conn->prepare($existingBookingQuery);
    $existingStmt->bind_param("i", $user_id);
    $existingStmt->execute();
    $existingResult = $existingStmt->get_result();
    $existingBooking = $existingResult->fetch_assoc();

    if ($existingBooking) {
        // If user has a still-active verified booking (not expired)
        if ($existingBooking['status'] === 'verified') {
            if (!$existingBooking['expires_at'] || $existingBooking['expires_at'] > date('Y-m-d H:i:s')) {
                sendResponse(false, 'You already have an active booking. Please wait for it to expire or upgrade to a higher package.', null, 409);
            }
        }
        
        // If user has a pending booking, don't allow new booking
        if ($existingBooking['status'] === 'pending') {
            sendResponse(false, 'You already have a pending booking. Please wait for it to be verified or rejected.', null, 409);
        }
    }

    // Compute expiry date immediately (so user/admin views can show it even before verification)
    $expiresAt = null;
    $duration = $package['duration'] ?? '';
    $days = 0;
    if (stripos($duration, 'Day') !== false) {
        $days = (int)$duration;
    } elseif (stripos($duration, 'Week') !== false) {
        $days = (int)$duration * 7;
    } elseif (stripos($duration, 'Month') !== false) {
        $days = (int)$duration * 30;
    } elseif (stripos($duration, 'Year') !== false) {
        $days = (int)$duration * 365;
    } else {
        // Best-effort fallback: if duration begins with a number, treat as days
        $days = (int) $duration;
    }

    if ($days > 0) {
        $expiresAt = date('Y-m-d H:i:s', strtotime($booking_date . " + $days days"));
    }
    
    // Insert booking into database
    $sql = "INSERT INTO bookings (user_id, name, email, contact, package_id, package_name, amount, booking_date, expires_at, notes, receipt_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssisdssss", 
        $user_id,
        $user['name'],
        $user['email'],
        $contact,
        $package['id'],
        $package_name,
        $package['price'],
        $booking_date,
        $expiresAt,
        $notes,
        $receipt_url
    );
    
    $result = $stmt->execute();
    
    if (!$result) {
        sendResponse(false, 'Failed to create booking: ' . $conn->error, null, 500);
    }
    
    // Get the ID of the newly created booking
    $booking_id = $conn->insert_id;
    
    // Send email notification to admin
    try {
        require_once '../email.php';
        sendBookingNotificationEmail([
            'user_name' => $user['name'],
            'user_email' => $user['email'],
            'contact' => $contact,
            'package_name' => $package_name,
            'amount' => $package['price'],
            'booking_date' => $booking_date,
            'notes' => $notes
        ]);
    } catch (Exception $e) {
        error_log("Failed to send booking notification email: " . $e->getMessage());
    }

    // Notify trainers assigned to this package
    try {
        require_once '../email.php';
        $trainerQ = $conn->prepare(
            "SELECT t.email, t.name, t.user_id FROM trainers t
             JOIN package_trainers pt ON pt.trainer_id = t.id
             WHERE pt.package_id = ? AND t.is_active = 1"
        );
        $trainerQ->bind_param("i", $package['id']);
        $trainerQ->execute();
        $trainerResult = $trainerQ->get_result();
        while ($trainer = $trainerResult->fetch_assoc()) {
            sendTrainerNewBookingEmail($trainer['email'], $trainer['name'], $user['name'], $package_name, $booking_date);
            createNotification(
                $trainer['user_id'],
                'New Booking Pending',
                $user['name'] . ' submitted a booking for your package: ' . $package_name,
                'info'
            );
        }
        $trainerQ->close();
    } catch (Exception $e) {
        error_log("Failed to send trainer booking notification: " . $e->getMessage());
    }

    // Notify admins about new booking request
    try {
        $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
        if ($adminStmt) {
            $adminStmt->execute();
            $adminRes = $adminStmt->get_result();
            while ($adminRow = $adminRes->fetch_assoc()) {
                $adminId = (int)$adminRow['id'];
                createNotification(
                    $adminId,
                    'New Booking Pending',
                    $user['name'] . ' submitted a booking for: ' . $package_name . ' (' . $booking_date . ').',
                    'info'
                );
            }
            $adminStmt->close();
        }
    } catch (Throwable $e) {
        error_log("Failed to notify admins about new booking: " . $e->getMessage());
    }
    
    sendResponse(true, 'Booking created successfully', ['id' => $booking_id]);

} catch (Exception $e) {
    error_log("Error creating booking: " . $e->getMessage());
    sendResponse(false, 'Error creating booking: ' . $e->getMessage(), null, 500);
}
?>