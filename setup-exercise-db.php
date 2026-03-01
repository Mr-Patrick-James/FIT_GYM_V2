<?php
require_once 'api/config.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Setting up Exercise Planning Database</h2>";

$conn = getDBConnection();

// Create equipment table
$sql1 = "
CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category ENUM('Strength', 'Cardio', 'Free Weights', 'Functional', 'Other') DEFAULT 'Strength',
    description TEXT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create exercises table
$sql2 = "
CREATE TABLE IF NOT EXISTS exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    category ENUM('Chest', 'Back', 'Legs', 'Shoulders', 'Arms', 'Core', 'Cardio', 'Full Body') DEFAULT 'Full Body',
    equipment_id INT,
    description TEXT,
    instructions TEXT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE SET NULL
)";

// Create package_exercises table
$sql3 = "
CREATE TABLE IF NOT EXISTS package_exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    exercise_id INT NOT NULL,
    sets INT DEFAULT 3,
    reps VARCHAR(50) DEFAULT '10-12',
    notes TEXT,
    UNIQUE KEY unique_pkg_exercise (package_id, exercise_id),
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
)";

if ($conn->query($sql1) === TRUE) echo "<p>✅ Equipment table created</p>";
else echo "<p>❌ Error creating equipment table: " . $conn->error . "</p>";

if ($conn->query($sql2) === TRUE) echo "<p>✅ Exercises table created</p>";
else echo "<p>❌ Error creating exercises table: " . $conn->error . "</p>";

if ($conn->query($sql3) === TRUE) echo "<p>✅ Package Exercises table created</p>";
else echo "<p>❌ Error creating package_exercises table: " . $conn->error . "</p>";

// Insert Default Equipment for Martinez Fitness Gym
$equipment = [
    ['Dumbbells (Standard Iron)', 'Free Weights', 'Various iron dumbbells for versatile strength training'],
    ['Olympic Barbells', 'Free Weights', '2" Olympic bars for heavy compound lifting'],
    ['Flat Bench Press', 'Strength', 'Standard flat bench for chest pressing'],
    ['Incline Bench Press', 'Strength', 'Adjustable bench for upper chest development'],
    ['Smith Machine', 'Strength', 'Guided barbell for safe squats and presses'],
    ['Leg Press (Plate-loaded)', 'Strength', '45-degree leg press for quad and glute training'],
    ['Lat Pulldown / Seated Row', 'Strength', 'Multi-purpose cable machine for back strength'],
    ['Cable Crossover', 'Strength', 'Functional cable trainer for isolation exercises'],
    ['Leg Extension / Curl', 'Strength', 'Machine for isolating quads and hamstrings'],
    ['Pec Deck (Chest Fly)', 'Strength', 'Isolates the pectoral muscles'],
    ['Preacher Curl Bench', 'Strength', 'Isolates the biceps with an EZ bar'],
    ['Pull-up / Dip Tower', 'Functional', 'Bodyweight station for upper body strength'],
    ['Treadmill', 'Cardio', 'Modern treadmill for cardiovascular endurance'],
    ['Stationary Bike', 'Cardio', 'For low-impact cardio and leg conditioning'],
    ['Kettlebells (Iron)', 'Functional', 'Traditional iron kettlebells for functional movements']
];

$stmt = $conn->prepare("INSERT IGNORE INTO equipment (name, category, description) VALUES (?, ?, ?)");
foreach ($equipment as $item) {
    $stmt->bind_param("sss", $item[0], $item[1], $item[2]);
    $stmt->execute();
}
echo "<p>✅ Martinez Fitness Gym equipment inserted</p>";

// Get equipment IDs for exercises
$equipIds = [];
$res = $conn->query("SELECT id, name FROM equipment");
while($row = $res->fetch_assoc()) {
    $equipIds[$row['name']] = $row['id'];
}

// Insert Default Exercises for Martinez Fitness Gym
$exercises = [
    ['Flat Barbell Bench Press', 'Chest', $equipIds['Flat Bench Press'], 'Build chest mass with this foundational lift.', 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&q=80&w=800'],
    ['Incline Dumbbell Press', 'Chest', $equipIds['Incline Bench Press'], 'Target upper pecs with inclined dumbbell pressing.', 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?auto=format&fit=crop&q=80&w=800'],
    ['Smith Machine Squat', 'Legs', $equipIds['Smith Machine'], 'Guided squat for quad and glute strength.', 'https://images.unsplash.com/photo-1574673139054-949479e0a0f4?auto=format&fit=crop&q=80&w=800'],
    ['Plate-loaded Leg Press', 'Legs', $equipIds['Leg Press (Plate-loaded)'], 'High volume leg training for maximum growth.', 'https://images.unsplash.com/photo-1590239068531-97f267a14e6e?auto=format&fit=crop&q=80&w=800'],
    ['Wide-grip Lat Pulldown', 'Back', $equipIds['Lat Pulldown / Seated Row'], 'Essential move for building a wider back.', 'https://images.unsplash.com/photo-1605296867304-46d5465a13f1?auto=format&fit=crop&q=80&w=800'],
    ['Seated Cable Row', 'Back', $equipIds['Lat Pulldown / Seated Row'], 'Build thickness in the mid-back area.', 'https://images.unsplash.com/photo-1594737625785-a6badabb3ff7?auto=format&fit=crop&q=80&w=800'],
    ['EZ Bar Preacher Curl', 'Arms', $equipIds['Preacher Curl Bench'], 'Strict bicep isolation on the preacher bench.', 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?auto=format&fit=crop&q=80&w=800'],
    ['Dumbbell Side Lateral Raise', 'Shoulders', $equipIds['Dumbbells (Standard Iron)'], 'Build capped shoulders by targeting lateral delts.', 'https://images.unsplash.com/photo-1532029837206-aba2ff3519c2?auto=format&fit=crop&q=80&w=800'],
    ['Overhead Dumbbell Press', 'Shoulders', $equipIds['Dumbbells (Standard Iron)'], 'Overall shoulder mass builder.', 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&q=80&w=800'],
    ['Tricep Cable Pushdown', 'Arms', $equipIds['Cable Crossover'], 'Isolation move for the back of the arms.', 'https://images.unsplash.com/photo-1598575212252-c20974395ff6?auto=format&fit=crop&q=80&w=800'],
    ['Cable Chest Fly', 'Chest', $equipIds['Cable Crossover'], 'Great isolation exercise for inner chest squeeze.', 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?auto=format&fit=crop&q=80&w=800'],
    ['Hanging Leg Raise', 'Core', $equipIds['Pull-up / Dip Tower'], 'Effective lower abdominal exercise.', 'https://images.unsplash.com/photo-1571019613576-2b22c76fd94b?auto=format&fit=crop&q=80&w=800'],
    ['Pull-ups', 'Back', $equipIds['Pull-up / Dip Tower'], 'Classic bodyweight back builder.', 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?auto=format&fit=crop&q=80&w=800'],
    ['Treadmill Jogging', 'Cardio', $equipIds['Treadmill'], 'Steady state cardio for fat loss.', 'https://images.unsplash.com/photo-1541534442868-9f84c1747add?auto=format&fit=crop&q=80&w=800'],
    ['Stationary Cycling', 'Cardio', $equipIds['Stationary Bike'], 'Low impact cardio conditioning.', 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&q=80&w=800'],
    ['Kettlebell Swing', 'Full Body', $equipIds['Kettlebells (Iron)'], 'Functional power move for the whole body.', 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&q=80&w=800']
];

$stmt = $conn->prepare("INSERT INTO exercises (name, category, equipment_id, description, image_url) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE description=VALUES(description), image_url=VALUES(image_url)");
foreach ($exercises as $ex) {
    $stmt->bind_param("ssiss", $ex[0], $ex[1], $ex[2], $ex[3], $ex[4]);
    $stmt->execute();
}
echo "<p>✅ Martinez Fitness Gym exercises inserted with images</p>";

// Link exercises to packages
$pkgRes = $conn->query("SELECT id, name FROM packages");
$pkgsByName = [];
while($row = $pkgRes->fetch_assoc()) {
    $pkgsByName[$row['name']] = $row['id'];
}

$exRes = $conn->query("SELECT id, name FROM exercises");
$exs = [];
while($row = $exRes->fetch_assoc()) {
    $exs[$row['name']] = $row['id'];
}

// Define assignments
$packageAssignments = [
    'DAILY PASS' => [
        [$exs['Treadmill Jogging'], 1, '15 mins'],
        [$exs['Flat Barbell Bench Press'], 3, '10'],
        [$exs['Wide-grip Lat Pulldown'], 3, '12'],
        [$exs['Hanging Leg Raise'], 2, '15']
    ],
    'BASIC PACKAGE' => [
        [$exs['Treadmill Jogging'], 1, '20 mins'],
        [$exs['Smith Machine Squat'], 3, '10'],
        [$exs['Flat Barbell Bench Press'], 3, '10'],
        [$exs['Wide-grip Lat Pulldown'], 3, '12'],
        [$exs['Dumbbell Side Lateral Raise'], 3, '12'],
        [$exs['Hanging Leg Raise'], 3, '15']
    ],
    'WEEKLY PASS' => [
        [$exs['Treadmill Jogging'], 1, '20 mins'],
        [$exs['Smith Machine Squat'], 3, '10'],
        [$exs['Flat Barbell Bench Press'], 3, '10'],
        [$exs['Incline Dumbbell Press'], 3, '12'],
        [$exs['Wide-grip Lat Pulldown'], 3, '12'],
        [$exs['Seated Cable Row'], 3, '12'],
        [$exs['Hanging Leg Raise'], 3, '20']
    ],
    'STANDARD PACKAGE' => [
        [$exs['Treadmill Jogging'], 1, '20 mins'],
        [$exs['Smith Machine Squat'], 4, '8'],
        [$exs['Plate-loaded Leg Press'], 3, '12'],
        [$exs['Flat Barbell Bench Press'], 4, '8'],
        [$exs['Incline Dumbbell Press'], 3, '10'],
        [$exs['Wide-grip Lat Pulldown'], 4, '10'],
        [$exs['Seated Cable Row'], 3, '12'],
        [$exs['Pull-ups'], 3, 'Failure'],
        [$exs['Overhead Dumbbell Press'], 3, '10'],
        [$exs['Hanging Leg Raise'], 4, '20']
    ],
    'PREMIUM PACKAGE' => [
        [$exs['Stationary Cycling'], 1, '20 mins'],
        [$exs['Smith Machine Squat'], 4, '8'],
        [$exs['Plate-loaded Leg Press'], 3, '12'],
        [$exs['Flat Barbell Bench Press'], 4, '8'],
        [$exs['Incline Dumbbell Press'], 3, '10'],
        [$exs['Wide-grip Lat Pulldown'], 4, '10'],
        [$exs['Seated Cable Row'], 3, '12'],
        [$exs['Pull-ups'], 3, 'Failure'],
        [$exs['Overhead Dumbbell Press'], 3, '10'],
        [$exs['Cable Chest Fly'], 3, '15'],
        [$exs['EZ Bar Preacher Curl'], 4, '10'],
        [$exs['Tricep Cable Pushdown'], 4, '12'],
        [$exs['Kettlebell Swing'], 3, '20'],
        [$exs['Hanging Leg Raise'], 4, '20']
    ],
    'ULTIMATE PACKAGE' => [
        [$exs['Treadmill Jogging'], 1, '30 mins'],
        [$exs['Smith Machine Squat'], 5, '5'],
        [$exs['Plate-loaded Leg Press'], 4, '10'],
        [$exs['Flat Barbell Bench Press'], 5, '5'],
        [$exs['Incline Dumbbell Press'], 4, '8'],
        [$exs['Wide-grip Lat Pulldown'], 5, '8'],
        [$exs['Seated Cable Row'], 4, '10'],
        [$exs['Pull-ups'], 4, 'Failure'],
        [$exs['Overhead Dumbbell Press'], 4, '8'],
        [$exs['Cable Chest Fly'], 4, '12'],
        [$exs['EZ Bar Preacher Curl'], 4, '8'],
        [$exs['Tricep Cable Pushdown'], 4, '10'],
        [$exs['Kettlebell Swing'], 4, '15'],
        [$exs['Hanging Leg Raise'], 5, '20'],
        [$exs['Stationary Cycling'], 1, '15 mins']
    ]
];

// Clear existing assignments
$conn->query("DELETE FROM package_exercises");
$conn->query("ALTER TABLE package_exercises AUTO_INCREMENT = 1");

// Assign exercises to all detected packages
$stmt = $conn->prepare("INSERT IGNORE INTO package_exercises (package_id, exercise_id, sets, reps) VALUES (?, ?, ?, ?)");
foreach ($packageAssignments as $pkgName => $assignments) {
    if (isset($pkgsByName[$pkgName])) {
        $pkgId = $pkgsByName[$pkgName];
        foreach ($assignments as $a) {
            $stmt->bind_param("iiis", $pkgId, $a[0], $a[1], $a[2]);
            $stmt->execute();
        }
    }
}
echo "<p>✅ Exercises assigned to all packages: " . implode(', ', array_keys($packageAssignments)) . "</p>";

$conn->close();
echo "<p>Done! <a href='index.php'>Go back home</a></p>";
?>
