<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$conn = getDBConnection();

$query = "
    SELECT 
        e.id, 
        e.name, 
        e.category, 
        e.equipment_id, 
        e.description, 
        e.instructions, 
        e.image_url,
        (SELECT COUNT(*) FROM package_exercises pe WHERE pe.exercise_id = e.id) as package_count
    FROM exercises e 
    ORDER BY e.name ASC
";

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

$exercises = [];
while ($row = $result->fetch_assoc()) {
    $exercises[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'category' => $row['category'],
        'equipment_id' => $row['equipment_id'] ? (int)$row['equipment_id'] : null,
        'description' => $row['description'],
        'instructions' => $row['instructions'],
        'image_url' => $row['image_url'],
        'package_count' => (int)$row['package_count']
    ];
}

$stmt->close();
$conn->close();

sendResponse(true, 'Exercises retrieved successfully', $exercises);
?>
