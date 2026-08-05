<?php
/**
 * API to update student discount for packages
 * PATCH /api/packages/update-student-discount.php
 */
require_once '../config.php';
require_once '../session.php';

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method !== 'PATCH') {
    sendResponse(false, 'Method not allowed', null, 405);
    exit;
}

$packageId = $input['package_id'] ?? null;
$studentDiscount = $input['student_discount'] ?? null;

if (!$packageId || $studentDiscount === null) {
    sendResponse(false, 'Package ID and student discount percentage are required', null, 400);
    exit;
}

// Validate discount percentage
$studentDiscount = floatval($studentDiscount);
if ($studentDiscount < 0 || $studentDiscount > 100) {
    sendResponse(false, 'Discount percentage must be between 0 and 100', null, 400);
    exit;
}

try {
    $conn = getDBConnection();

    // Verify package exists
    $checkStmt = $conn->prepare("SELECT id, name, price FROM packages WHERE id = ?");
    $checkStmt->bind_param("i", $packageId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        sendResponse(false, 'Package not found', null, 404);
        exit;
    }

    $package = $checkResult->fetch_assoc();

    // Update student discount
    $updateStmt = $conn->prepare("UPDATE packages SET student_discount = ? WHERE id = ?");
    $updateStmt->bind_param("di", $studentDiscount, $packageId);

    if (!$updateStmt->execute()) {
        sendResponse(false, 'Failed to update student discount', null, 500);
        exit;
    }

    // Calculate discount amount
    $discountAmount = ($package['price'] * $studentDiscount) / 100;
    $discountedPrice = $package['price'] - $discountAmount;

    sendResponse(true, 'Student discount updated successfully', [
        'package_id' => $packageId,
        'package_name' => $package['name'],
        'original_price' => floatval($package['price']),
        'student_discount_percentage' => floatval($studentDiscount),
        'discount_amount' => floatval($discountAmount),
        'student_price' => floatval($discountedPrice)
    ]);

} catch (Exception $e) {
    error_log("Error updating student discount: " . $e->getMessage());
    sendResponse(false, 'Internal server error', null, 500);
}
?>
