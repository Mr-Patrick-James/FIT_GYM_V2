<?php
require_once 'api/config.php';

$conn = getDBConnection();

$seedPackages = [
    [
        'name' => 'Muscle Building PRO',
        'duration' => '30 Days',
        'price' => '₱2,500',
        'tag' => 'Premium',
        'description' => 'High-intensity program focused on hypertrophy and strength gains.',
        'goal' => 'Muscle Building',
        'is_trainer_assisted' => 1
    ],
    [
        'name' => 'Body Toning Express',
        'duration' => '15 Days',
        'price' => '₱1,200',
        'tag' => 'Popular',
        'description' => 'Targeted workouts to define muscles and improve overall physique.',
        'goal' => 'Body Toning',
        'is_trainer_assisted' => 1
    ],
    [
        'name' => 'Weight Loss Journey',
        'duration' => '60 Days',
        'price' => '₱4,000',
        'tag' => 'Best Value',
        'description' => 'Comprehensive cardio and strength mix designed for maximum calorie burn.',
        'goal' => 'Weight Loss',
        'is_trainer_assisted' => 1
    ],
    [
        'name' => 'Power & Strength',
        'duration' => '30 Days',
        'price' => '₱2,200',
        'tag' => 'Advanced',
        'description' => 'Focus on compound lifts to build raw power and functional strength.',
        'goal' => 'Strength & Power',
        'is_trainer_assisted' => 1
    ]
];

echo "<h2>Seeding Goal-Specific Packages</h2>";

foreach ($seedPackages as $pkg) {
    $name = $conn->real_escape_string($pkg['name']);
    $duration = $conn->real_escape_string($pkg['duration']);
    $price = $conn->real_escape_string($pkg['price']);
    $tag = $conn->real_escape_string($pkg['tag']);
    $description = $conn->real_escape_string($pkg['description']);
    $goal = $conn->real_escape_string($pkg['goal']);
    $is_trainer_assisted = $pkg['is_trainer_assisted'];
    
    $check = $conn->query("SELECT id FROM packages WHERE name = '$name'");
    if ($check && $check->num_rows == 0) {
        $sql = "INSERT INTO packages (name, duration, price, tag, description, goal, is_trainer_assisted) 
                VALUES ('$name', '$duration', '$price', '$tag', '$description', '$goal', $is_trainer_assisted)";
        if ($conn->query($sql)) {
            echo "<p>✅ Added Package: $name ($goal)</p>";
        } else {
            echo "<p>❌ Error adding $name: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>ℹ️ Package already exists: $name</p>";
    }
}

$conn->close();
echo "<p>Seeding complete!</p>";
?>
