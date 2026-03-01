<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
$package_name = isset($_GET['package_name']) ? $_GET['package_name'] : '';

if ($package_id <= 0 && empty($package_name)) {
    sendResponse(false, 'Package ID or Name is required', null, 400);
}

$conn = getDBConnection();

// If we only have name, find the ID first
if ($package_id <= 0 && !empty($package_name)) {
    $stmt = $conn->prepare("SELECT id FROM packages WHERE name = ?");
    $stmt->bind_param("s", $package_name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $package_id = $row['id'];
    }
    $stmt->close();
}

if ($package_id <= 0) {
    sendResponse(false, 'Package not found', null, 404);
}

// Get exercises for the package with equipment details
$query = "
    SELECT 
        e.id, 
        e.name, 
        e.category, 
        e.description, 
        e.instructions,
        e.image_url,
        eq.name as equipment_name,
        pe.sets, 
        pe.reps, 
        pe.notes
    FROM package_exercises pe
    JOIN exercises e ON pe.exercise_id = e.id
    LEFT JOIN equipment eq ON e.equipment_id = eq.id
    WHERE pe.package_id = ?
    ORDER BY pe.id ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $package_id);
$stmt->execute();
$result = $stmt->get_result();

$exercises = [];
while ($row = $result->fetch_assoc()) {
    $exercises[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'category' => $row['category'],
        'description' => $row['description'],
        'instructions' => $row['instructions'],
        'image_url' => $row['image_url'],
        'equipment_name' => $row['equipment_name'],
        'sets' => (int)$row['sets'],
        'reps' => $row['reps'],
        'notes' => $row['notes']
    ];
}

$stmt->close();
$conn->close();

sendResponse(true, 'Exercises retrieved successfully', $exercises);
?>
