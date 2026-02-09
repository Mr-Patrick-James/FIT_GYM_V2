<?php
/**
 * Simplified OTP Verification and Account Creation
 * 
 * Flow:
 * 1. User signs up -> signup.php generates OTP and sends email (NO account created)
 * 2. User enters OTP -> This file verifies OTP (email + code + not expired + not used)
 * 3. On success -> Account is created/updated and marked email_verified = TRUE
 * 4. User session is set and JSON returned
 */

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();

$email    = trim(strtolower($data['email'] ?? ''));
$otp      = trim((string)($data['otp'] ?? ''));
$name     = trim($data['name'] ?? '');
$password = $data['password'] ?? '';
$contact  = trim($data['contact'] ?? '');
$address  = trim($data['address'] ?? '');

// Basic validation
if ($email === '' || $otp === '' || $name === '' || $password === '') {
    sendResponse(false, 'Email, OTP, name, and password are required', null, 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email format', null, 400);
}

if (!preg_match('/^\d{6}$/', $otp)) {
    sendResponse(false, 'OTP must be exactly 6 digits', null, 400);
}

$conn = getDBConnection();

// 1) Find OTP that matches THIS email + code, not used, and not expired
//    Give 60s grace period for small clock differences.
$stmt = $conn->prepare("
    SELECT id, email, code, expires_at, used,
           TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS seconds_remaining
    FROM otps
    WHERE email = ? AND code = ? AND used = 0
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$result = $stmt->get_result();
$otpData = $result->fetch_assoc();
$stmt->close();

if (!$otpData) {
    $conn->close();
    sendResponse(false, 'Invalid OTP code. Please check and try again.', null, 400);
}

// Check expiration (allow small negative for network delay)
$secondsRemaining = (int)$otpData['seconds_remaining'];
if ($secondsRemaining < -60) {
    $conn->close();
    sendResponse(false, 'OTP has expired. Please request a new one.', null, 400);
}

// 2) Mark OTP as used
$updateOtp = $conn->prepare("UPDATE otps SET used = 1 WHERE id = ?");
$updateOtp->bind_param("i", $otpData['id']);
$updateOtp->execute();
if ($updateOtp->error) {
    $err = $updateOtp->error;
    $updateOtp->close();
    $conn->close();
    sendResponse(false, 'Error verifying OTP: ' . $err, null, 500);
}
$updateOtp->close();

// 3) Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// 4) Create or update user
$checkUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkUser->bind_param("s", $email);
$checkUser->execute();
$userResult = $checkUser->get_result();
$existingUser = $userResult->fetch_assoc();
$checkUser->close();

if ($existingUser) {
    $userId = (int)$existingUser['id'];
    $updateUser = $conn->prepare("
        UPDATE users
        SET name = ?, password = ?, contact = ?, address = ?, email_verified = 1, updated_at = NOW()
        WHERE id = ?
    ");
    $updateUser->bind_param("ssssi", $name, $hashedPassword, $contact, $address, $userId);
    $updateUser->execute();
    if ($updateUser->error) {
        $err = $updateUser->error;
        $updateUser->close();
        $conn->close();
        sendResponse(false, 'Error updating account: ' . $err, null, 500);
    }
    $updateUser->close();
} else {
    $insertUser = $conn->prepare("
        INSERT INTO users (name, email, password, contact, address, email_verified, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $insertUser->bind_param("sssss", $name, $email, $hashedPassword, $contact, $address);
    $insertUser->execute();
    if ($insertUser->error) {
        $err = $insertUser->error;
        $insertUser->close();
        $conn->close();
        sendResponse(false, 'Error creating account: ' . $err, null, 500);
    }
    $userId = $insertUser->insert_id;
    $insertUser->close();
}

$conn->close();

// 5) Set user session (server-side)
require_once '../session.php';
setUserSession([
    'id'      => $userId,
    'name'    => $name,
    'email'   => $email,
    'role'    => 'user',
    'contact' => $contact,
    'address' => $address,
]);

// 6) Return clean JSON
sendResponse(true, 'Email verified and account created successfully', [
    'user_id' => $userId,
    'name'    => $name,
    'email'   => $email,
    'role'    => 'user',
]);

?>
