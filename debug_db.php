<?php
require_once 'api/config.php';
$conn = getDBConnection();
$result = $conn->query("SELECT id, name, receipt_url FROM bookings");
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | URL: " . $row['receipt_url'] . "\n";
}
unlink(__FILE__);
