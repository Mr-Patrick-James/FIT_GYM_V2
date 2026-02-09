<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$name = trim($data['name'] ?? '');
$email = trim(strtolower($data['email'] ?? ''));
$password = $data['password'] ?? '';

// Validation
if (empty($name) || empty($email) || empty($password)) {
    sendResponse(false, 'Name, email, and password are required', null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', null, 400);
}

if (strlen($password) < 6) {
    sendResponse(false, 'Password must be at least 6 characters long', null, 400);
}

try {
    $conn = getDBConnection();
    
    // Check if user already exists
    $stmt = $conn->prepare("SELECT id, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // If user exists and is already verified, prevent signup with same email
    if ($user && $user['email_verified']) {
        $conn->close();
        sendResponse(false, 'An account with this email already exists. Please use a different email address.', null, 400);
    }
    
    // If user exists but not verified, allow resending OTP (for users who didn't complete verification)
    
    // Validate that the email domain exists (optional, might fail on some hosting)
    /*
    $domain = substr(strrchr($email, '@'), 1);
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        $conn->close();
        sendResponse(false, 'Invalid email domain. Please enter a valid email address.', null, 400);
    }
    */
    
    // ============================================
    // IMPORTANT: NO ACCOUNT CREATION HERE
    // ============================================
    // This endpoint ONLY generates and sends OTP
    // Account creation happens ONLY in verify-otp.php
    // AFTER successful OTP verification
    // ============================================
    
    // The OTP verification will handle account creation
    
    // Generate 6-digit OTP (ensure it's exactly 6 digits, no whitespace)
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp = trim($otp); // Ensure no whitespace
    
    // Delete old OTPs for this email
    $stmt = $conn->prepare("DELETE FROM otps WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();
    
    // Insert new OTP using MySQL's DATE_ADD to ensure timezone consistency
    // This ensures expires_at uses MySQL's timezone, matching the NOW() comparison
    $stmt = $conn->prepare("INSERT INTO otps (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    
    if ($stmt->error) {
        error_log("Error inserting OTP: " . $stmt->error);
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Error generating OTP. Please try again.', null, 500);
    }
    
    // Get the actual expiration time from database for logging
    // Return both datetime string and Unix timestamp for client-side timer sync
    $checkStmt = $conn->prepare("SELECT expires_at, UNIX_TIMESTAMP(expires_at) as expires_at_timestamp FROM otps WHERE email = ? AND code = ? ORDER BY created_at DESC LIMIT 1");
    $checkStmt->bind_param("ss", $email, $otp);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $expiresAt = 'N/A';
    $expiresAtTimestamp = null;
    if ($checkRow = $checkResult->fetch_assoc()) {
        $expiresAt = $checkRow['expires_at'];
        $expiresAtTimestamp = $checkRow['expires_at_timestamp'];
    }
    $checkStmt->close();
    
    // Log the OTP for debugging (remove in production)
    error_log("OTP generated and stored - Email: $email, OTP: $otp, Expires: $expiresAt, Unix Timestamp: $expiresAtTimestamp");
    $stmt->close();
    
    $conn->close();
} catch (Exception $e) {
    error_log("Signup Error: " . $e->getMessage());
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}

// Send OTP via email
require_once '../email.php';
error_log("Attempting to send OTP email to: $email, OTP: $otp, Name: $name");
$emailSent = sendOTPEmail($email, $otp, $name);

if (!$emailSent) {
    // Log error but don't fail the request - OTP is still saved in database
    error_log("CRITICAL: Failed to send OTP email to: $email. OTP code is: $otp");
    error_log("Please check: 1) Database email_configs table, 2) SMTP credentials, 3) PHP error logs");
    // Still return success - user can request resend if needed
    sendResponse(true, 'OTP sent to your email. Please check your inbox. If you don\'t receive it, check your spam folder or click "Resend".', [
        'email' => $email,
        'otp_sent' => false, // Flag to indicate email might not have been sent
        'expires_at' => $expiresAt, // Return datetime string for display
        'expires_at_timestamp' => $expiresAtTimestamp // Return Unix timestamp (timezone-independent) for client-side timer
    ]);
} else {
    error_log("SUCCESS: OTP email sent to: $email");
    sendResponse(true, 'OTP sent to your email. Please check your inbox.', [
        'email' => $email,
        'otp_sent' => true,
        'expires_at' => $expiresAt, // Return datetime string for display
        'expires_at_timestamp' => $expiresAtTimestamp // Return Unix timestamp (timezone-independent) for client-side timer
    ]);
}

?>
