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
    
    $target_package_id = $input['package_id'] ?? null;
    $contact = $input['contact'] ?? null;
    $notes = $input['notes'] ?? null;
    $receipt_url = $input['receipt'] ?? null;
    $user_id = $_SESSION['user_id'];
    
    if (!$target_package_id) {
        sendResponse(false, 'Target package ID is required', null, 400);
    }
    
    // Get user's current active booking
    $currentBookingQuery = "SELECT b.*, p.price as current_price, p.duration as current_duration 
                           FROM bookings b 
                           LEFT JOIN packages p ON b.package_id = p.id 
                           WHERE b.user_id = ? AND b.status = 'verified' 
                           ORDER BY b.created_at DESC LIMIT 1";
    $currentStmt = $conn->prepare($currentBookingQuery);
    $currentStmt->bind_param("i", $user_id);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentBooking = $currentResult->fetch_assoc();
    
    if (!$currentBooking) {
        sendResponse(false, 'No active booking found for upgrade', null, 404);
    }
    
    // Check if current booking is still valid (not expired)
    if ($currentBooking['expires_at']) {
        $currentTime = date('Y-m-d H:i:s');
        if ($currentBooking['expires_at'] <= $currentTime) {
            sendResponse(false, 'Current booking has expired. Please create a new booking.', null, 400);
        }
    }
    
    // Get target package info
    $targetPackageQuery = "SELECT id, name, price, duration FROM packages WHERE id = ? AND is_active = 1";
    $targetPackageStmt = $conn->prepare($targetPackageQuery);
    $targetPackageStmt->bind_param("i", $target_package_id);
    $targetPackageStmt->execute();
    $targetPackageResult = $targetPackageStmt->get_result();
    $targetPackage = $targetPackageResult->fetch_assoc();
    
    if (!$targetPackage) {
        sendResponse(false, 'Target package not found or inactive', null, 404);
    }
    
    // Validate that target package is actually higher tier (more expensive)
    if ($targetPackage['price'] <= $currentBooking['current_price']) {
        sendResponse(false, 'Target package must be higher tier than current package', null, 400);
    }
    
    // Get user info for the upgrade booking
    $userQuery = "SELECT name, email FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    
    if (!$user) {
        sendResponse(false, 'User not found', null, 404);
    }
    
    // Calculate upgrade price and new expiry date
    $upgradePrice = $targetPackage['price'] - $currentBooking['current_price'];
    
    // Calculate remaining days from current booking
    $remainingDays = 0;
    if ($currentBooking['expires_at']) {
        $currentTime = new DateTime();
        $expiryTime = new DateTime($currentBooking['expires_at']);
        $interval = $currentTime->diff($expiryTime);
        $remainingDays = $interval->days;
    }
    
    // Calculate new duration for target package
    $targetDuration = $targetPackage['duration'] ?? '';
    $targetDays = 0;
    if (stripos($targetDuration, 'Day') !== false) {
        $targetDays = (int)$targetDuration;
    } elseif (stripos($targetDuration, 'Week') !== false) {
        $targetDays = (int)$targetDuration * 7;
    } elseif (stripos($targetDuration, 'Month') !== false) {
        $targetDays = (int)$targetDuration * 30;
    } elseif (stripos($targetDuration, 'Year') !== false) {
        $targetDays = (int)$targetDuration * 365;
    }
    
    // New expiry date starts from now with target package duration
    $newExpiresAt = null;
    if ($targetDays > 0) {
        $newExpiresAt = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . " + $targetDays days"));
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Mark current booking as upgraded (but keep it for record)
        $updateCurrentQuery = "UPDATE bookings SET status = 'upgraded', notes = CONCAT(IFNULL(notes, ''), '\nUpgraded to: " . $targetPackage['name'] . " on " . date('Y-m-d H:i:s') . "') WHERE id = ?";
        $updateCurrentStmt = $conn->prepare($updateCurrentQuery);
        $updateCurrentStmt->bind_param("i", $currentBooking['id']);
        $updateCurrentStmt->execute();
        
        // Create new upgrade booking
        $upgradeBookingQuery = "INSERT INTO bookings (user_id, name, email, contact, package_id, package_name, amount, booking_date, expires_at, notes, receipt_url, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')";
        
        $upgradeNotes = "Upgrade from: " . $currentBooking['package_name'] . "\n" . ($notes ?: '');
        
        $upgradeStmt = $conn->prepare($upgradeBookingQuery);
        $upgradeStmt->bind_param("isssisdssss", 
            $user_id,
            $user['name'],
            $user['email'],
            $contact,
            $targetPackage['id'],
            $targetPackage['name'],
            $upgradePrice,
            date('Y-m-d H:i:s'),
            $newExpiresAt,
            $upgradeNotes,
            $receipt_url
        );
        
        $upgradeResult = $upgradeStmt->execute();
        
        if (!$upgradeResult) {
            throw new Exception('Failed to create upgrade booking');
        }
        
        $newBookingId = $conn->insert_id;
        
        // Add payment record for the upgrade
        $paymentSql = "INSERT INTO payments (user_id, booking_id, amount, status, payment_method, transaction_id, receipt_url, created_at) 
                       VALUES (?, ?, ?, 'completed', 'Upgrade Payment', CONCAT('UP_', ?), ?, NOW())";
        $paymentStmt = $conn->prepare($paymentSql);
        $paymentStmt->bind_param("iidss", 
            $user_id,
            $newBookingId,
            $upgradePrice,
            $newBookingId,
            $receipt_url
        );
        $paymentStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Send upgrade confirmation email
        try {
            require_once '../email.php';
            // You can create a new email function for upgrades or use existing one
            sendBookingVerificationEmail([
                'user_email' => $user['email'],
                'user_name' => $user['name'],
                'package_name' => $targetPackage['name'],
                'expiry_date' => $newExpiresAt
            ]);
        } catch (Exception $e) {
            error_log("Failed to send upgrade confirmation email: " . $e->getMessage());
        }
        
        // Notify admins about the upgrade
        try {
            $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
            if ($adminStmt) {
                $adminStmt->execute();
                $adminRes = $adminStmt->get_result();
                while ($adminRow = $adminRes->fetch_assoc()) {
                    $adminId = (int)$adminRow['id'];
                    createNotification(
                        $adminId,
                        'Booking Upgraded',
                        $user['name'] . ' upgraded from ' . $currentBooking['package_name'] . ' to ' . $targetPackage['name'] . '.',
                        'success'
                    );
                }
                $adminStmt->close();
            }
        } catch (Exception $e) {
            error_log("Failed to notify admins about upgrade: " . $e->getMessage());
        }
        
        sendResponse(true, 'Booking upgraded successfully', [
            'new_booking_id' => $newBookingId,
            'upgrade_price' => $upgradePrice,
            'new_expiry_date' => $newExpiresAt
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error upgrading booking: " . $e->getMessage());
    sendResponse(false, 'Error upgrading booking: ' . $e->getMessage(), null, 500);
}
?>
