<?php
// Ensure no output before headers
ob_start();
require_once '../config.php';
require_once '../session.php';
requireAdmin();

// Clean any accidental output from config/session
if (ob_get_length()) ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$conn = getDBConnection();

// Robustly handle tables
$conn->query("CREATE TABLE IF NOT EXISTS `trainers` (
    `id` int NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Ensure all columns exist (for older versions of the table)
$columns = [
    'user_id' => "int DEFAULT NULL",
    'email' => "varchar(255) DEFAULT NULL",
    'contact' => "varchar(20) DEFAULT NULL",
    'specialization' => "varchar(255) DEFAULT 'General Fitness'",
    'experience' => "varchar(50) DEFAULT '1-2 years'",
    'max_clients' => "int DEFAULT '10'",
    'is_active' => "tinyint(1) DEFAULT '1'",
    'bio' => "text",
    'image_url' => "varchar(255) DEFAULT NULL",
    'certifications' => "text",
    'availability' => "text"
];

foreach ($columns as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM `trainers` LIKE '$col'");
    if ($check && $check->num_rows == 0) {
        $conn->query("ALTER TABLE `trainers` ADD `$col` $def");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS `package_trainers` (
    `id` int NOT NULL AUTO_INCREMENT,
    `package_id` int NOT NULL,
    `trainer_id` int NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$query = "SELECT t.*,
          (SELECT COUNT(*) FROM package_trainers pt WHERE pt.trainer_id = t.id) as package_count,
          (SELECT COUNT(*) FROM bookings b WHERE b.trainer_id = t.id AND b.status = 'verified' AND (b.expires_at IS NULL OR b.expires_at >= CURDATE())) as active_client_count,
          (SELECT COUNT(*) FROM bookings b WHERE b.trainer_id = t.id AND b.status = 'verified') as total_clients_handled
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
        $row['active_client_count'] = (int)($row['active_client_count'] ?? 0);
        $row['total_clients_handled'] = (int)($row['total_clients_handled'] ?? 0);
        $row['max_clients'] = (int)($row['max_clients'] ?? 10);
        // availability and certifications are stored as JSON strings
        $trainers[] = $row;
    }
}

$conn->close();
sendResponse(true, 'Trainers retrieved successfully', $trainers);
