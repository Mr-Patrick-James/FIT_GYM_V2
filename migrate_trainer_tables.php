<?php
require_once 'api/config.php';
require_once 'api/session.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Comprehensive Migration for Trainer Features</h2>";

$conn = getDBConnection();

// 1. Create trainers table
$sqlTrainers = "
CREATE TABLE IF NOT EXISTS trainers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    contact VARCHAR(50),
    email VARCHAR(100),
    bio TEXT,
    photo_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active)
)";
if ($conn->query($sqlTrainers) === TRUE) echo "<p>✅ trainers table created/verified</p>";
else echo "<p>❌ Error creating trainers table: " . $conn->error . "</p>";

// 2. Add trainer_id to bookings table if it doesn't exist
$checkColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'trainer_id'");
if ($checkColumn && $checkColumn->num_rows == 0) {
    $sqlAlterBookings = "ALTER TABLE bookings ADD COLUMN trainer_id INT AFTER package_id, ADD FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL";
    if ($conn->query($sqlAlterBookings) === TRUE) echo "<p>✅ trainer_id column added to bookings</p>";
    else echo "<p>❌ Error adding trainer_id to bookings: " . $conn->error . "</p>";
} else {
    echo "<p>ℹ️ trainer_id column already exists in bookings</p>";
}

// 2.1 Add verified_at to bookings table if it doesn't exist
$checkVerifiedColumn = $conn->query("SHOW COLUMNS FROM bookings LIKE 'verified_at'");
if ($checkVerifiedColumn && $checkVerifiedColumn->num_rows == 0) {
    $sqlAlterVerified = "ALTER TABLE bookings ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL AFTER status";
    if ($conn->query($sqlAlterVerified) === TRUE) {
        echo "<p>✅ verified_at column added to bookings</p>";
        // Update existing verified bookings with current_timestamp if needed
        $conn->query("UPDATE bookings SET verified_at = updated_at WHERE status = 'verified' AND verified_at IS NULL");
    }
    else echo "<p>❌ Error adding verified_at to bookings: " . $conn->error . "</p>";
} else {
    echo "<p>ℹ️ verified_at column already exists in bookings</p>";
}

// 2.1.1 Add is_trainer_assisted and goal to packages table if they don't exist
$checkAssistedColumn = $conn->query("SHOW COLUMNS FROM packages LIKE 'is_trainer_assisted'");
if ($checkAssistedColumn && $checkAssistedColumn->num_rows == 0) {
    $sqlAlterPackages = "ALTER TABLE packages ADD COLUMN is_trainer_assisted BOOLEAN DEFAULT FALSE AFTER description";
    if ($conn->query($sqlAlterPackages) === TRUE) echo "<p>✅ is_trainer_assisted column added to packages</p>";
    else echo "<p>❌ Error adding is_trainer_assisted to packages: " . $conn->error . "</p>";
}

$checkGoalColumn = $conn->query("SHOW COLUMNS FROM packages LIKE 'goal'");
if ($checkGoalColumn && $checkGoalColumn->num_rows == 0) {
    $sqlAlterGoal = "ALTER TABLE packages ADD COLUMN goal VARCHAR(50) DEFAULT 'General Fitness' AFTER is_trainer_assisted";
    if ($conn->query($sqlAlterGoal) === TRUE) echo "<p>✅ goal column added to packages</p>";
    else echo "<p>❌ Error adding goal to packages: " . $conn->error . "</p>";
} else {
    echo "<p>ℹ️ goal column already exists in packages</p>";
}

// 2.1.2 Create package_trainers junction table
$sqlPackageTrainers = "
CREATE TABLE IF NOT EXISTS package_trainers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    trainer_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pkg_trainer (package_id, trainer_id)
)";
if ($conn->query($sqlPackageTrainers) === TRUE) echo "<p>✅ package_trainers junction table created/verified</p>";
else echo "<p>❌ Error creating package_trainers table: " . $conn->error . "</p>";

// 2.2 Ensure current user is a trainer if role is trainer
$user = getCurrentUser();
if ($user && $user['role'] === 'trainer') {
    $userId = $user['id'];
    $checkTrainer = $conn->query("SELECT id FROM trainers WHERE user_id = $userId");
    if ($checkTrainer && $checkTrainer->num_rows == 0) {
        $name = $conn->real_escape_string($user['name']);
        $email = $conn->real_escape_string($user['email']);
        $contact = $conn->real_escape_string($user['contact'] ?? '');
        $sqlAddTrainer = "INSERT INTO trainers (user_id, name, email, contact, specialization) VALUES ($userId, '$name', '$email', '$contact', 'General Fitness')";
        if ($conn->query($sqlAddTrainer) === TRUE) {
            echo "<p>✅ Created trainer record for current user: $name</p>";
        } else {
            echo "<p>❌ Error creating trainer record: " . $conn->error . "</p>";
        }
    }
}

// 3. Create member_exercise_plans table
$sqlMemberPlans = "
CREATE TABLE IF NOT EXISTS member_exercise_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    exercise_id INT NOT NULL,
    sets INT DEFAULT 3,
    reps VARCHAR(50) DEFAULT '10-12',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id)
)";
if ($conn->query($sqlMemberPlans) === TRUE) echo "<p>✅ member_exercise_plans table created/verified</p>";
else echo "<p>❌ Error creating member_exercise_plans table: " . $conn->error . "</p>";

// 4. Create member_progress table
$sqlMemberProgress = "
CREATE TABLE IF NOT EXISTS member_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    trainer_id INT NOT NULL,
    weight DECIMAL(5,2),
    remarks TEXT,
    logged_at DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_trainer (trainer_id)
)";
if ($conn->query($sqlMemberProgress) === TRUE) echo "<p>✅ member_progress table created/verified</p>";
else echo "<p>❌ Error creating member_progress table: " . $conn->error . "</p>";

// 5. Create notifications table
$sqlNotifications = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
)";
if ($conn->query($sqlNotifications) === TRUE) echo "<p>✅ notifications table created/verified</p>";
else echo "<p>❌ Error creating notifications table: " . $conn->error . "</p>";

// 6. Create trainer_sessions table
$sqlSessions = "
CREATE TABLE IF NOT EXISTS trainer_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    booking_id INT NOT NULL,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    duration INT DEFAULT 60,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    type ENUM('workout', 'assessment', 'consultation', 'rest_day') DEFAULT 'workout',
    title VARCHAR(100) DEFAULT 'Workout Session',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_date (session_date),
    INDEX idx_trainer_date (trainer_id, session_date)
)";
if ($conn->query($sqlSessions) === TRUE) echo "<p>✅ trainer_sessions table created/verified</p>";
else echo "<p>❌ Error creating trainer_sessions table: " . $conn->error . "</p>";

// 6.1 Add type column to trainer_sessions if it doesn't exist
$checkTypeColumn = $conn->query("SHOW COLUMNS FROM trainer_sessions LIKE 'type'");
if ($checkTypeColumn && $checkTypeColumn->num_rows == 0) {
    $sqlAlterType = "ALTER TABLE trainer_sessions ADD COLUMN type ENUM('workout', 'assessment', 'consultation', 'rest_day') DEFAULT 'workout' AFTER status";
    if ($conn->query($sqlAlterType) === TRUE) echo "<p>✅ type column added to trainer_sessions</p>";
    else echo "<p>❌ Error adding type to trainer_sessions: " . $conn->error . "</p>";
}

// 6.2 Add title column to trainer_sessions if it doesn't exist
$checkTitleColumn = $conn->query("SHOW COLUMNS FROM trainer_sessions LIKE 'title'");
if ($checkTitleColumn && $checkTitleColumn->num_rows == 0) {
    $sqlAlterTitle = "ALTER TABLE trainer_sessions ADD COLUMN title VARCHAR(100) DEFAULT 'Workout Session' AFTER type";
    if ($conn->query($sqlAlterTitle) === TRUE) echo "<p>✅ title column added to trainer_sessions</p>";
    else echo "<p>❌ Error adding title to trainer_sessions: " . $conn->error . "</p>";
}

// 6.3 Add exercises column to trainer_sessions if it doesn't exist
$checkExColumn = $conn->query("SHOW COLUMNS FROM trainer_sessions LIKE 'exercises'");
if ($checkExColumn && $checkExColumn->num_rows == 0) {
    $sqlAlterEx = "ALTER TABLE trainer_sessions ADD COLUMN exercises TEXT AFTER notes";
    if ($conn->query($sqlAlterEx) === TRUE) echo "<p>✅ exercises column added to trainer_sessions</p>";
    else echo "<p>❌ Error adding exercises to trainer_sessions: " . $conn->error . "</p>";
}

// 7. Create trainer_tips table
$sqlTips = "
CREATE TABLE IF NOT EXISTS trainer_tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    tip_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sqlTips) === TRUE) echo "<p>✅ trainer_tips table created/verified</p>";
else echo "<p>❌ Error creating trainer_tips table: " . $conn->error . "</p>";

// 8. Create food_recommendations table
$sqlFood = "
CREATE TABLE IF NOT EXISTS food_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT NOT NULL,
    member_id INT NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snack', 'pre-workout', 'post-workout') NOT NULL,
    food_items TEXT NOT NULL,
    calories INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
)";
if ($conn->query($sqlFood) === TRUE) echo "<p>✅ food_recommendations table created/verified</p>";
else echo "<p>❌ Error creating food_recommendations table: " . $conn->error . "</p>";

// 9. Create equipment table
$sqlEquipment = "
CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    description TEXT,
    image_url VARCHAR(255),
    status ENUM('active', 'maintenance', 'out_of_order') DEFAULT 'active',
    last_maintained DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sqlEquipment) === TRUE) echo "<p>✅ equipment table created/verified</p>";
else echo "<p>❌ Error creating equipment table: " . $conn->error . "</p>";

// 9.1 Ensure equipment table has all necessary columns (status, description, image_url)
$equipmentColumns = [
    'status' => "ENUM('active', 'maintenance', 'out_of_order') DEFAULT 'active' AFTER category",
    'description' => "TEXT AFTER category",
    'image_url' => "VARCHAR(255) AFTER description"
];

foreach ($equipmentColumns as $col => $definition) {
    $checkCol = $conn->query("SHOW COLUMNS FROM equipment LIKE '$col'");
    if ($checkCol && $checkCol->num_rows == 0) {
        $sqlAlter = "ALTER TABLE equipment ADD COLUMN $col $definition";
        if ($conn->query($sqlAlter) === TRUE) echo "<p>✅ $col column added to equipment</p>";
        else echo "<p>❌ Error adding $col to equipment: " . $conn->error . "</p>";
    }
}

// 10. Add equipment_id to exercises table if it doesn't exist
$checkExEquip = $conn->query("SHOW COLUMNS FROM exercises LIKE 'equipment_id'");
if ($checkExEquip && $checkExEquip->num_rows == 0) {
    $sqlAlterExercises = "ALTER TABLE exercises ADD COLUMN equipment_id INT AFTER category, ADD FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE SET NULL";
    if ($conn->query($sqlAlterExercises) === TRUE) echo "<p>✅ equipment_id column added to exercises</p>";
    else echo "<p>❌ Error adding equipment_id to exercises: " . $conn->error . "</p>";
} else {
    echo "<p>ℹ️ equipment_id column already exists in exercises</p>";
}

$conn->close();
echo "<p>Migration complete!</p>";
?>
