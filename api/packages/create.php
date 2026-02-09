<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();

$name = trim($data['name'] ?? '');
$duration = trim($data['duration'] ?? '');
$price = trim($data['price'] ?? '');
$tag = trim($data['tag'] ?? '');
$description = trim($data['description'] ?? '');

// Validation
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

// Insert new package
$stmt = $conn->prepare("INSERT INTO packages (name, duration, price, tag, description) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("ssdss", $name, $duration, $priceValue, $tag, $description);

if ($stmt->execute()) {
    $packageId = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    
    sendResponse(true, 'Package created successfully', [
        'id' => $packageId,
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
    sendResponse(false, 'Error creating package: ' . $error, null, 500);
}
?>