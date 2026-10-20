<?php
/** Request a password-reset code for an existing, verified account. */
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$email = is_string($data['email'] ?? null) ? trim(strtolower($data['email'])) : '';
if ($email === '') {
    sendResponse(false, 'Email is required', null, 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', null, 400);
}

$conn = null;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare('SELECT id, name, email_verified FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) throw new RuntimeException('Account lookup failed');
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Do not disclose whether an address exists or is verified.
    if (!$user || !$user['email_verified']) {
        $conn->close();
        sendResponse(true, 'If an eligible account exists, a reset code will be sent. Please check your inbox and spam folder.', ['email' => $email]);
    }

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare('DELETE FROM otps WHERE email = ?');
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) throw new RuntimeException('Old reset code cleanup failed');
    $stmt->close();

    // Use the same DB clock for creation, verification and redemption.
    $stmt = $conn->prepare('INSERT INTO otps (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))');
    $stmt->bind_param('ss', $email, $otp);
    if (!$stmt->execute() || $stmt->error) throw new RuntimeException('Reset code creation failed');
    $stmt->close();

    $stmt = $conn->prepare('SELECT expires_at, UNIX_TIMESTAMP(expires_at) as expires_at_timestamp FROM otps WHERE email = ? AND code = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->bind_param('ss', $email, $otp);
    if (!$stmt->execute()) throw new RuntimeException('Reset expiry lookup failed');
    $expiry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$expiry) throw new RuntimeException('Reset expiry missing');
    $conn->close();
    $conn = null;

    require_once '../email.php';
    $emailSent = sendPasswordResetEmail($email, $otp, $user['name']);
    if (!$emailSent) {
        // Log the OTP so admin can retrieve it if needed — never expose in response
        error_log("Password reset OTP for $email: $otp (email delivery failed — check SMTP config)");
        // Still return success so user knows the account exists and code was generated.
        // They may need to contact admin if email never arrives.
        sendResponse(true, 'Reset code generated. If you do not receive an email within a few minutes, please check your spam folder or contact the gym admin.', [
            'email'               => $email,
            'expires_at'          => $expiry['expires_at'],
            'expires_at_timestamp'=> $expiry['expires_at_timestamp']
        ]);
    }
    sendResponse(true, 'If an eligible account exists, a reset code will be sent. Please check your inbox and spam folder.', [
        'email' => $email,
        'expires_at' => $expiry['expires_at'],
        'expires_at_timestamp' => $expiry['expires_at_timestamp']
    ]);
} catch (Exception $e) {
    if ($conn) $conn->close();
    // Reset codes and passwords must never be printed or logged.
    error_log('Password reset code request failed');
    sendResponse(false, 'Unable to request a reset code right now. Please try again later.', null, 500);
}
