<?php
ob_start();
require_once '../config.php';
require_once '../session.php';

if (ob_get_length()) ob_clean();

// Only require login (admin or manager can assign trainers)
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();

    $result = $conn->query(
        "SELECT id, name, specialization, is_active
         FROM trainers
         WHERE is_active = 1
         ORDER BY name ASC"
    );

    $trainers = [];
    while ($row = $result->fetch_assoc()) {
        $trainers[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'specialization' => $row['specialization'] ?? 'General Fitness',
            'is_active'      => (bool)$row['is_active'],
        ];
    }

    $conn->close();
    sendResponse(true, 'Trainers retrieved', $trainers);

} catch (Exception $e) {
    error_log("get-for-assignment error: " . $e->getMessage());
    sendResponse(false, 'Error: ' . $e->getMessage(), null, 500);
}
?>
