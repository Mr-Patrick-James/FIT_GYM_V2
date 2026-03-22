<?php
ob_start();
require_once '../config.php';
require_once '../email.php';
ob_end_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();

$name = trim($data['name'] ?? '');
$duration = trim($data['duration'] ?? '');
$price = trim($data['price'] ?? '');
$tag = trim($data['tag'] ?? '');
$description = trim($data['description'] ?? '');
$goal = trim($data['goal'] ?? 'General Fitness');
$isTrainerAssisted = isset($data['is_trainer_assisted']) ? (bool)$data['is_trainer_assisted'] : false;
$trainerIds = $data['trainer_ids'] ?? [];

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
$conn->begin_transaction();

try {
    // Insert new package
    $stmt = $conn->prepare("INSERT INTO packages (name, duration, price, tag, description, is_trainer_assisted, goal) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $isTrainerAssistedInt = $isTrainerAssisted ? 1 : 0;
    $stmt->bind_param("ssdssis", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal);

    if ($stmt->execute()) {
        $packageId = $conn->insert_id;

        $notifyTrainers = []; // collect for post-commit notifications

        if (!empty($trainerIds) && $isTrainerAssisted) {
            $trainerStmt = $conn->prepare("INSERT INTO package_trainers (package_id, trainer_id) VALUES (?, ?)");
            foreach ($trainerIds as $trainerId) {
                $tId = (int)$trainerId;
                $trainerStmt->bind_param("ii", $packageId, $tId);
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

        // Send notifications after commit
        foreach ($notifyTrainers as $tUser) {
            try {
                createNotification($tUser['user_id'], 'New Package Assignment', "You have been assigned to handle the package: $name.", 'assignment');
                sendTrainerPackageAssignmentEmail($tUser['email'], $tUser['name'], $name, $description);
            } catch (Exception $emailEx) {
                error_log("Trainer assignment notification failed: " . $emailEx->getMessage());
            }
        }

        $conn->close();
        sendResponse(true, 'Package created successfully', ['id' => $packageId]);
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, 'Failed to create package: ' . $e->getMessage());
}
?>