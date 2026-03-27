<?php
ob_start();
require_once '../config.php';
require_once '../email.php';
ob_end_clean();

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
$dietInfo = trim($data['diet_info'] ?? '');
$guidanceInfo = trim($data['guidance_info'] ?? '');
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
    $checkDiet = $conn->query("SHOW COLUMNS FROM packages LIKE 'diet_info'");
    $hasDiet = ($checkDiet && $checkDiet->num_rows > 0);
    $checkGuidance = $conn->query("SHOW COLUMNS FROM packages LIKE 'guidance_info'");
    $hasGuidance = ($checkGuidance && $checkGuidance->num_rows > 0);

    $sql = "UPDATE packages SET name = ?, duration = ?, price = ?, tag = ?, description = ?, is_trainer_assisted = ?, goal = ?";
    if ($hasDiet) $sql .= ", diet_info = ?";
    if ($hasGuidance) $sql .= ", guidance_info = ?";
    $sql .= ", updated_at = NOW() WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $isTrainerAssistedInt = $isTrainerAssisted ? 1 : 0;
    
    if ($hasDiet && $hasGuidance) {
        $stmt->bind_param("ssdssisssi", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal, $dietInfo, $guidanceInfo, $id);
    } elseif ($hasDiet) {
        $stmt->bind_param("ssdssissi", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal, $dietInfo, $id);
    } elseif ($hasGuidance) {
        $stmt->bind_param("ssdssissi", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal, $guidanceInfo, $id);
    } else {
        $stmt->bind_param("ssdssisi", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal, $id);
    }

    if ($stmt->execute()) {
        // Sync trainers
        $conn->query("DELETE FROM package_trainers WHERE package_id = $id");

        $notifyTrainers = []; // collect for post-commit notifications

        if (!empty($trainerIds) && $isTrainerAssisted) {
            $trainerStmt = $conn->prepare("INSERT INTO package_trainers (package_id, trainer_id) VALUES (?, ?)");
            foreach ($trainerIds as $trainerId) {
                $tId = (int)$trainerId;
                $trainerStmt->bind_param("ii", $id, $tId);
                $trainerStmt->execute();

                $tUserQuery = $conn->query("SELECT t.user_id, t.email, t.name FROM trainers t WHERE t.id = $tId");
                if ($tUserQuery && $tUser = $tUserQuery->fetch_assoc()) {
                    $notifyTrainers[] = $tUser;
                }
            }
            $trainerStmt->close();
        }

        $conn->commit();
        $stmt->close();

        // Send notifications after commit (avoids interfering with transaction)
        foreach ($notifyTrainers as $tUser) {
            try {
                createNotification($tUser['user_id'], 'Package Assignment Updated', "You are assigned to handle the package: $name.", 'assignment');
                sendTrainerPackageAssignmentEmail($tUser['email'], $tUser['name'], $name, $description);
            } catch (Exception $emailEx) {
                error_log("Trainer assignment notification failed: " . $emailEx->getMessage());
            }
        }

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