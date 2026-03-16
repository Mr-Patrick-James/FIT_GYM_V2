<?php
require_once 'api/config.php';

$conn = getDBConnection();

$seedData = [
    ['Dumbbells', 'Free Weights', 'Various weights for isolation exercises'],
    ['Barbell', 'Free Weights', 'Olympic barbell for compound lifts'],
    ['Bench Press', 'Machines', 'Standard flat bench press machine'],
    ['Treadmill', 'Cardio', 'High-end running treadmill'],
    ['Kettlebell', 'Free Weights', 'Cast iron kettlebells'],
    ['Resistance Bands', 'Accessories', 'Various tension levels for mobility'],
    ['Yoga Mat', 'Accessories', 'Standard cushioning mat'],
    ['Pull-up Bar', 'Machines', 'Wall-mounted pull-up bar']
];

echo "<h2>Seeding Equipment Data</h2>";

foreach ($seedData as $data) {
    $name = $conn->real_escape_string($data[0]);
    $category = $conn->real_escape_string($data[1]);
    $description = $conn->real_escape_string($data[2]);
    
    $check = $conn->query("SELECT id FROM equipment WHERE name = '$name'");
    if ($check && $check->num_rows == 0) {
        $sql = "INSERT INTO equipment (name, category, description, status) VALUES ('$name', '$category', '$description', 'active')";
        if ($conn->query($sql)) {
            echo "<p>✅ Added: $name</p>";
        } else {
            echo "<p>❌ Error adding $name: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>ℹ️ Already exists: $name</p>";
    }
}

$conn->close();
echo "<p>Seeding complete!</p>";
?>
