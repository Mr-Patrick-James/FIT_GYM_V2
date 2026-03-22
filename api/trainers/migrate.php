<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

$conn = getDBConnection();
$results = [];

// Safe column additions — check information_schema first
$columns = [
    'availability'   => "ALTER TABLE trainers ADD COLUMN availability VARCHAR(500) DEFAULT NULL",
    'certifications' => "ALTER TABLE trainers ADD COLUMN certifications TEXT DEFAULT NULL",
    'max_clients'    => "ALTER TABLE trainers ADD COLUMN max_clients INT DEFAULT 10",
];

foreach ($columns as $col => $sql) {
    // Check if column already exists
    $check = $conn->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS 
                           WHERE TABLE_SCHEMA = DATABASE() 
                           AND TABLE_NAME = 'trainers' 
                           AND COLUMN_NAME = '$col'");
    $row = $check->fetch_assoc();

    if ((int)$row['cnt'] === 0) {
        if ($conn->query($sql)) {
            $results[] = ['column' => $col, 'status' => 'added'];
        } else {
            $results[] = ['column' => $col, 'status' => 'error', 'error' => $conn->error];
        }
    } else {
        $results[] = ['column' => $col, 'status' => 'already_exists'];
    }
}

$conn->close();
sendResponse(true, 'Migration complete', $results);
?>
