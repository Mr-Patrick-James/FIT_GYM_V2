<?php
require_once 'api/config.php';
$conn = getDBConnection();

$tables = ['bookings', 'trainers', 'users'];
foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>";
        while ($row = $res->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "Error: " . $conn->error;
    }
}

// Check if a trainer exists for user_id 1
$res = $conn->query("SELECT * FROM trainers");
echo "<h3>All Trainers:</h3>";
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
        echo "<br>";
    }
}

$conn->close();
?>
