<?php
require 'api/config.php';
$conn = getDBConnection();
$res = $conn->query('DESCRIBE user_questionnaire');
if($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . ' ' . $row['Type'] . PHP_EOL;
    }
} else {
    echo "Error: " . $conn->error . PHP_EOL;
}
$conn->close();
?>
