<?php
/**
 * Forgot Password - Step 1
 * Accepts an email, verifies the account exists, generates an OTP, and emails it.
 */
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$email = trim(strtolower($data['email'] ?? ''));

if (empty($email)) {
    sendResponse(false, 'Email is required', null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', null, 400);
}

$conn = getDBConnection();

// Check account exists and is verified
$stmt = $conn->prepare("SELECT id, name, email_verified FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Always return the same message to avoid email enumeration
if (!$user || !$user['email_verified']) {
    $conn->close();
    sendResponse(true, 'If an account with that email exists, a reset code has been sent.', [
        'email' => $email
    ]);
}

// Generate 6-digit OTP
$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

// Delete any old OTPs for this email
$stmt = $conn->prepare("DELETE FROM otps WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->close();

// Insert new OTP (5-minute expiry)
$stmt = $conn->prepare("INSERT INTO otps (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();

if ($stmt->error) {
    error_log("forgot-password OTP insert error: " . $stmt->error);
    $stmt->close();
    $conn->close();
    sendResponse(false, 'Error generating reset code. Please try again.', null, 500);
}

// Fetch expiry timestamps for client-side timer
$checkStmt = $conn->prepare("SELECT expires_at, UNIX_TIMESTAMP(expires_at) as expires_at_timestamp FROM otps WHERE email = ? AND code = ? ORDER BY created_at DESC LIMIT 1");
$checkStmt->bind_param("ss", $email, $otp);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$expiresAt = 'N/A';
$expiresAtTimestamp = null;
if ($checkRow = $checkResult->fetch_assoc()) {
    $expiresAt          = $checkRow['expires_at'];
    $expiresAtTimestamp = $checkRow['expires_at_timestamp'];
}
$checkStmt->close();
$stmt->close();
$conn->close();

error_log("forgot-password OTP - Email: $email, OTP: $otp, Expires: $expiresAt");

// Send reset email
require_once '../email.php';
$emailSent = sendPasswordResetEmail($email, $otp, $user['name']);

if (!$emailSent) {
    error_log("CRITICAL: Failed to send password reset email to $email");
}

sendResponse(true, 'If an account with that email exists, a reset code has been sent.', [
    'email'                => $email,
    'expires_at'           => $expiresAt,
    'expires_at_timestamp' => $expiresAtTimestamp
]);
