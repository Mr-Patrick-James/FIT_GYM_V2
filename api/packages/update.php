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
$goal = trim($data['goal'] ?? 'General Fitness');
$isTrainerAssisted = isset($data['is_trainer_assisted']) ? (bool)$data['is_trainer_assisted'] : false;
$trainerIds = $data['trainer_ids'] ?? [];

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
$conn->begin_transaction();

try {
    // Check if package exists
    $checkStmt = $conn->prepare("SELECT id FROM packages WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        throw new Exception('Package not found');
    }
    $checkStmt->close();

    // Update package
    $stmt = $conn->prepare("UPDATE packages SET name = ?, duration = ?, price = ?, tag = ?, description = ?, is_trainer_assisted = ?, goal = ?, updated_at = NOW() WHERE id = ?");
    $isTrainerAssistedInt = $isTrainerAssisted ? 1 : 0;
    $stmt->bind_param("ssdssisi", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal, $id);

    if ($stmt->execute()) {
        // Sync trainers
        $conn->query("DELETE FROM package_trainers WHERE package_id = $id");
        
        if (!empty($trainerIds) && $isTrainerAssisted) {
            $trainerStmt = $conn->prepare("INSERT INTO package_trainers (package_id, trainer_id) VALUES (?, ?)");
            foreach ($trainerIds as $trainerId) {
                $tId = (int)$trainerId;
                $trainerStmt->bind_param("ii", $id, $tId);
                $trainerStmt->execute();

                // Notify trainer (could be optimized to only notify new ones, but for simplicity)
                $tUserQuery = $conn->query("SELECT user_id FROM trainers WHERE id = $tId");
                if ($tUserQuery && $tUser = $tUserQuery->fetch_assoc()) {
                    createNotification($tUser['user_id'], 'Package Assignment Updated', "You are assigned to handle the package: $name.", 'assignment');
                }
            }
            $trainerStmt->close();
        }
        
        $conn->commit();
        $stmt->close();
        $conn->close();
        sendResponse(true, 'Package updated successfully');
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, 'Failed to update package: ' . $e->getMessage());
}
?>