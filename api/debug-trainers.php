<?php
require_once 'config.php';
require_once 'session.php';
$conn = getDBConnection();

$trainers = $conn->query("SELECT id, name, is_active FROM trainers");
$rows = [];
while($r = $trainers->fetch_assoc()) $rows[] = $r;

$pt = $conn->query("SELECT * FROM package_trainers LIMIT 20");
$ptRows = [];
while($r = $pt->fetch_assoc()) $ptRows[] = $r;

header('Content-Type: application/json');
echo json_encode([
    'trainers' => $rows,
    'trainer_count' => count($rows),
    'package_trainers' => $ptRows
], JSON_PRETTY_PRINT);
