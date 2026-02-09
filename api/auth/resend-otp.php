<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$email = trim(strtolower($data['email'] ?? ''));

if (empty($email)) {
    sendResponse(false, 'Email is required', null, 400);
}

$conn = getDBConnection();

// Generate new 6-digit OTP (ensure it's exactly 6 digits, no whitespace)
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
error_log("OTP regenerated and stored - Email: $email, OTP: $otp, Expires: $expiresAt, Unix Timestamp: $expiresAtTimestamp");
$stmt->close();

$conn->close();

// Send OTP via email
require_once '../email.php';
$emailSent = sendOTPEmail($email, $otp);

if (!$emailSent) {
    // Log error but don't fail the request - OTP is still saved in database
    error_log("Failed to send OTP email to: $email");
}

sendResponse(true, 'New OTP sent to your email. Please check your inbox.', [
    'email' => $email,
    'expires_at' => $expiresAt, // Return datetime string for display
    'expires_at_timestamp' => $expiresAtTimestamp // Return Unix timestamp (timezone-independent) for client-side timer
]);

?>
