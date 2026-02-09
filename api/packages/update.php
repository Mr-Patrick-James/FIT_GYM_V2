<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();

$id = (int)($data['id'] ?? 0);
$name = trim($data['name'] ?? '');
$duration = trim($data['duration'] ?? '');
$price = trim($data['price'] ?? '');
$tag = trim($data['tag'] ?? '');
$description = trim($data['description'] ?? '');

// Validation
if ($id <= 0) {
    sendResponse(false, 'Invalid package ID', null, 400);
}

if (empty($name) || empty($duration) || empty($price)) {
    sendResponse(false, 'Package name, duration, and price are required', null, 400);
}

// Validate and clean price (remove ₱ symbol and commas)
$cleanPrice = preg_replace('/[₱,]/', '', $price);
if (!is_numeric($cleanPrice)) {
    sendResponse(false, 'Invalid price format', null, 400);
}

$priceValue = floatval($cleanPrice);

if ($priceValue <= 0) {
    sendResponse(false, 'Price must be greater than zero', null, 400);
}

$conn = getDBConnection();

// Check if package exists
$checkStmt = $conn->prepare("SELECT id FROM packages WHERE id = ?");
$checkStmt->bind_param("i", $id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    $checkStmt->close();
    $conn->close();
    sendResponse(false, 'Package not found', null, 404);
}
$checkStmt->close();

// Update package
$stmt = $conn->prepare("UPDATE packages SET name = ?, duration = ?, price = ?, tag = ?, description = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("ssdsii", $name, $duration, $priceValue, $tag, $description, $id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    
    sendResponse(true, 'Package updated successfully', [
        'id' => $id,
        'name' => $name,
        'duration' => $duration,
        'price' => '₱' . number_format($priceValue, 2),
        'tag' => $tag,
        'description' => $description
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendResponse(false, 'Error updating package: ' . $error, null, 500);
}
?>