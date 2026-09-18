<?php
/**
 * API to get student discount info for a package
 * GET /api/packages/get-student-discount.php?package_id=ID
 */
require_once '../config.php';

$packageId = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
$packageName = isset($_GET['package_name']) ? trim($_GET['package_name']) : '';

if ($packageId <= 0 && empty($packageName)) {
    sendResponse(false, 'Package ID or name is required', null, 400);
    exit;
}

try {
    $conn = getDBConnection();

    // Build query based on input
    if ($packageId > 0) {
        $query = "SELECT id, name, price, COALESCE(student_discount, 0) as student_discount FROM packages WHERE id = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $packageId);
    } else {
        $query = "SELECT id, name, price, COALESCE(student_discount, 0) as student_discount FROM packages WHERE name = ? AND is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $packageName);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendResponse(false, 'Package not found', null, 404);
        exit;
    }

    $package = $result->fetch_assoc();
    $studentDiscount = floatval($package['student_discount']);
    $discountAmount = ($package['price'] * $studentDiscount) / 100;
    $studentPrice = $package['price'] - $discountAmount;

    sendResponse(true, 'Package student discount info retrieved', [
        'package_id' => (int)$package['id'],
        'package_name' => $package['name'],
        'original_price' => floatval($package['price']),
        'student_discount_percentage' => $studentDiscount,
        'discount_amount' => round($discountAmount, 2),
        'student_price' => round($studentPrice, 2),
        'has_student_discount' => $studentDiscount > 0
    ]);

} catch (Exception $e) {
    error_log("Error getting student discount: " . $e->getMessage());
    sendResponse(false, 'Internal server error', null, 500);
}
?>
