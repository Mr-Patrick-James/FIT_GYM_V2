<?php
header('Content-Type: application/json');
require_once '../config.php';

// Check if user is logged in as admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Admin privileges required.']);
    exit();
}

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// Get all tables
$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sql_dump = "-- Martinez Fitness Database Backup\n";
$sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql_dump .= "-- Database: " . DB_NAME . "\n\n";
$sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Get table creation SQL
    $res = $conn->query("SHOW CREATE TABLE `$table` ");
    $row = $res->fetch_row();
    $sql_dump .= "\n\n" . $row[1] . ";\n\n";

    // Get table data
    $res = $conn->query("SELECT * FROM `$table` ");
    $num_fields = $res->field_count;

    while ($row = $res->fetch_row()) {
        $sql_dump .= "INSERT INTO `$table` VALUES(";
        for ($j = 0; $j < $num_fields; $j++) {
            $row[$j] = $conn->real_escape_string($row[$j]);
            if (isset($row[$j])) {
                $sql_dump .= '"' . $row[$j] . '"';
            } else {
                $sql_dump .= 'NULL';
            }
            if ($j < ($num_fields - 1)) {
                $sql_dump .= ',';
            }
        }
        $sql_dump .= ");\n";
    }
    $sql_dump .= "\n\n\n";
}

$sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Close connection
$conn->close();

// Set headers for file download
$filename = "fitpay_backup_" . date('Y-m-d_H-i-s') . ".sql";
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($sql_dump));

// Output the SQL dump and exit
echo $sql_dump;
exit();
?>
