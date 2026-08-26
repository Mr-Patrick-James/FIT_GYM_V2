<?php
ob_start();
require_once '../config.php';

if (ob_get_length()) ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();

    // Check trainers table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'trainers'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        sendResponse(true, 'Trainers retrieved', []);
    }

    $result = $conn->query(
        "SELECT id, name, specialization
         FROM trainers
         WHERE is_active = 1
         ORDER BY name ASC"
    );

    $trainers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $trainers[] = [
                'id'             => (int)$row['id'],
                'name'           => $row['name'],
                'specialization' => $row['specialization'] ?? 'General Fitness',
                'is_active'      => true,
            ];
        }
    }

    $conn->close();
    sendResponse(true, 'Trainers retrieved', $trainers);

} catch (Exception $e) {
    error_log("get-for-assignment error: " . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
