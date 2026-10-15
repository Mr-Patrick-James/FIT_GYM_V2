<?php
/**
 * Verify a password-reset OTP without changing anything, then redeem it only
 * when the user submits their new password. Both steps validate on the server.
 */
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$email = is_string($data['email'] ?? null) ? trim(strtolower($data['email'])) : '';
$otp = is_string($data['otp'] ?? null) || is_int($data['otp'] ?? null) ? trim((string)$data['otp']) : '';
$step = array_key_exists('_step', $data) ? $data['_step'] : 'reset'; // Only omitted legacy steps default to reset.
$password = $data['password'] ?? '';

if (!in_array($step, ['verify', 'reset'], true)) {
    sendResponse(false, 'Invalid password reset step', null, 400);
}
if ($email === '' || $otp === '') {
    sendResponse(false, 'Email and reset code are required', null, 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', null, 400);
}
if (!preg_match('/^\d{6}$/D', $otp)) {
    sendResponse(false, 'Reset code must be exactly 6 digits', null, 400);
}
if ($step === 'reset') {
    if (!is_string($password) || strlen($password) < 6 || strlen($password) > 72 || strpos($password, "\0") !== false) {
        sendResponse(false, 'Password must be between 6 and 72 bytes long', null, 400);
    }
    // Never save the placeholder submitted by the old broken verification UI.
    if ($password === '__VERIFY_ONLY__') {
        sendResponse(false, 'Please enter your new password', null, 400);
    }
}

$conn = null;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT o.id, u.id AS user_id,
            TIMESTAMPDIFF(SECOND, NOW(), o.expires_at) AS seconds_remaining
        FROM otps o INNER JOIN users u ON u.email = o.email
        WHERE o.email = ? AND o.code = ? AND o.used = 0 AND u.email_verified = 1
        ORDER BY o.created_at DESC, o.id DESC LIMIT 1");
    $stmt->bind_param('ss', $email, $otp);
    if (!$stmt->execute()) throw new RuntimeException('Reset code lookup failed');
    $otpData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$otpData) {
        $conn->close();
        sendResponse(false, 'Invalid reset code. Please check or request a new one.', ['reason' => 'invalid_otp'], 400);
    }
    if ((int)$otpData['seconds_remaining'] <= 0) {
        $conn->close();
        sendResponse(false, 'Reset code has expired. Please request a new one.', ['reason' => 'expired_otp'], 400);
    }
    if ($step === 'verify') {
        $conn->close();
        sendResponse(true, 'Reset code verified. You can now enter your new password.', ['verified' => true]);
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $userId = (int)$otpData['user_id'];
    $otpId = (int)$otpData['id'];
    // One guarded statement updates the password AND consumes the matching OTP.
    // Do not assume transaction rollback: older installs use MyISAM tables.
    // The predicates are checked again here, including after another reset/resend.
    $update = $conn->prepare("UPDATE users u INNER JOIN otps o ON o.email = u.email
        SET u.password = ?, u.updated_at = NOW(), o.used = 1
        WHERE u.id = ? AND o.id = ? AND u.email_verified = 1
          AND o.used = 0 AND o.expires_at > NOW()");
    $update->bind_param('sii', $hashed, $userId, $otpId);
    if (!$update->execute() || $update->error) throw new RuntimeException('Password update failed');
    $changed = $update->affected_rows;
    $update->close();
    $conn->close();
    $conn = null;

    if ($changed === 0) {
        sendResponse(false, 'Reset code is no longer valid. Please request a new one.', ['reason' => 'invalid_otp'], 400);
    }
    sendResponse(true, 'Password reset successfully. You can now log in with your new password.');
} catch (Exception $e) {
    if ($conn) $conn->close();
    // Do not log passwords, reset codes, or SQL binding values.
    error_log('Password reset request failed');
    sendResponse(false, 'Could not reset your password right now. Please try again.', null, 500);
}
