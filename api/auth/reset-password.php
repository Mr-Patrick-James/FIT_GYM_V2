<?php
/**
 * Reset Password - Step 2
 * Verifies OTP and updates the user password.
 */
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data     = getRequestData();
$email    = trim(strtolower($data['email']    ?? ''));
$otp      = trim((string)($data['otp']        ?? ''));
$password = $data['password']                 ?? '';

if (empty($email) || empty($otp) || empty($password)) {
    sendResponse(false, 'Email, OTP, and new password are required', null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', null, 400);
}

if (!preg_match('/^\d{6}$/', $otp)) {
    sendResponse(false, 'OTP must be exactly 6 digits', null, 400);
}

if (strlen($password) < 6) {
    sendResponse(false, 'Password must be at least 6 characters long', null, 400);
}

$conn = getDBConnection();

// Find valid OTP (not used, not expired, 60s grace period)
$stmt = $conn->prepare("
    SELECT id, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_remaining
    FROM otps
    WHERE email = ? AND code = ? AND used = 0
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$result  = $stmt->get_result();
$otpData = $result->fetch_assoc();
$stmt->close();

if (!$otpData) {
    $conn->close();
    sendResponse(false, 'Invalid reset code. Please check and try again.', null, 400);
}

if ((int)$otpData['seconds_remaining'] < -60) {
    $conn->close();
    sendResponse(false, 'Reset code has expired. Please request a new one.', null, 400);
}

// Mark OTP as used
$markUsed = $conn->prepare("UPDATE otps SET used = 1 WHERE id = ?");
$markUsed->bind_param("i", $otpData['id']);
$markUsed->execute();
$markUsed->close();

// Update user password
$hashed = password_hash($password, PASSWORD_BCRYPT);
$update = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
$update->bind_param("ss", $hashed, $email);
$update->execute();

if ($update->error || $update->affected_rows === 0) {
    $err = $update->error;
    $update->close();
    $conn->close();
    sendResponse(false, 'Could not update password. Please try again.', null, 500);
}
$update->close();
$conn->close();

error_log("Password reset successful for: $email");

sendResponse(true, 'Password reset successfully. You can now log in with your new password.');
