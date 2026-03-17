<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$conn = getDBConnection();

$query = "SELECT t.*, 
          (SELECT COUNT(*) FROM package_trainers pt WHERE pt.trainer_id = t.id) as package_count 
          FROM trainers t 
          ORDER BY t.name ASC";
$result = $conn->query($query);

$trainers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['user_id'] = $row['user_id'] ? (int)$row['user_id'] : null;
        $row['is_active'] = (bool)$row['is_active'];
        $row['package_count'] = (int)($row['package_count'] ?? 0);
        $trainers[] = $row;
    }
}

$conn->close();

sendResponse(true, 'Trainers retrieved successfully', $trainers);
?>
