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
    $packageQuery = "SELECT id, price FROM packages WHERE name = ? AND is_active = 1";
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
    
    // Insert booking into database
    $sql = "INSERT INTO bookings (user_id, name, email, contact, package_id, package_name, amount, booking_date, notes, receipt_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssisdsss", 
        $user_id,
        $user['name'],
        $user['email'],
        $contact,
        $package['id'],
        $package_name,
        $package['price'],
        $booking_date,
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
        // Don't fail the booking creation if email fails
    }
    
    sendResponse(true, 'Booking created successfully', ['id' => $booking_id]);

} catch (Exception $e) {
    error_log("Error creating booking: " . $e->getMessage());
    sendResponse(false, 'Error creating booking: ' . $e->getMessage(), null, 500);
}
?>