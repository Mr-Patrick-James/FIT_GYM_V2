<?php
require_once 'config.php';
$conn = getDBConnection();

$users = $conn->query("SELECT id, name, email, role FROM users ORDER BY id");
$userRows = [];
while($r = $users->fetch_assoc()) $userRows[] = $r;

$bookings = $conn->query("SELECT id, user_id, status, package_name, amount FROM bookings ORDER BY id");
$bookingRows = [];
while($r = $bookings->fetch_assoc()) $bookingRows[] = $r;

header('Content-Type: application/json');
echo json_encode([
    'users' => $userRows,
    'user_count' => count($userRows),
    'role_user_count' => count(array_filter($userRows, fn($u) => $u['role'] === 'user')),
    'bookings' => $bookingRows,
    'booking_count' => count($bookingRows)
], JSON_PRETTY_PRINT);
