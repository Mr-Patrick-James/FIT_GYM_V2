<?php
require_once '../config.php';
require_once '../session.php';

if (!isLoggedIn()) {
    sendResponse(false, 'Unauthorized access');
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendResponse(false, 'Invalid data');
}

$conn = getDBConnection();

// Prepare the fields
$age = isset($data['age']) ? (int)$data['age'] : null;
$sex = isset($data['sex']) ? $conn->real_escape_string($data['sex']) : null;
$height = isset($data['height']) ? (float)$data['height'] : null;
$weight = isset($data['weight']) ? (float)$data['weight'] : null;
$medical_conditions = isset($data['medical_conditions']) ? $conn->real_escape_string($data['medical_conditions']) : null;
$exercise_history = isset($data['exercise_history']) ? $conn->real_escape_string($data['exercise_history']) : 'Beginner';

$primary_goal = isset($data['primary_goal']) ? $conn->real_escape_string($data['primary_goal']) : 'Stay fit / general health';
$goal_pace = isset($data['goal_pace']) ? $conn->real_escape_string($data['goal_pace']) : 'Moderately';

$workout_days_per_week = isset($data['workout_days_per_week']) ? $conn->real_escape_string($data['workout_days_per_week']) : '1-2 days';
$preferred_workout_time = isset($data['preferred_workout_time']) ? $conn->real_escape_string($data['preferred_workout_time']) : 'Morning';

$injuries_limitations = isset($data['injuries_limitations']) ? $conn->real_escape_string($data['injuries_limitations']) : null;
$focus_areas = isset($data['focus_areas']) ? $conn->real_escape_string($data['focus_areas']) : 'Full body';

$workout_type = isset($data['workout_type']) ? $conn->real_escape_string($data['workout_type']) : 'Mixed';
$trainer_guidance = isset($data['trainer_guidance']) ? $conn->real_escape_string($data['trainer_guidance']) : 'Independent workout';

$equipment_confidence = isset($data['equipment_confidence']) ? $conn->real_escape_string($data['equipment_confidence']) : 'Not confident';

// Check if profile already exists
$check_sql = "SELECT id FROM user_questionnaire WHERE user_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing profile
    $sql = "UPDATE user_questionnaire SET 
            age = ?, sex = ?, height = ?, weight = ?, medical_conditions = ?, exercise_history = ?,
            primary_goal = ?, goal_pace = ?, workout_days_per_week = ?, preferred_workout_time = ?,
            injuries_limitations = ?, focus_areas = ?, workout_type = ?, trainer_guidance = ?,
            equipment_confidence = ?
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isddsssssssssssi", 
        $age, $sex, $height, $weight, $medical_conditions, $exercise_history,
        $primary_goal, $goal_pace, $workout_days_per_week, $preferred_workout_time,
        $injuries_limitations, $focus_areas, $workout_type, $trainer_guidance,
        $equipment_confidence, $user_id
    );
} else {
    // Insert new profile
    $sql = "INSERT INTO user_questionnaire (
            user_id, age, sex, height, weight, medical_conditions, exercise_history,
            primary_goal, goal_pace, workout_days_per_week, preferred_workout_time,
            injuries_limitations, focus_areas, workout_type, trainer_guidance,
            equipment_confidence
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisddsssssssssss", 
        $user_id, $age, $sex, $height, $weight, $medical_conditions, $exercise_history,
        $primary_goal, $goal_pace, $workout_days_per_week, $preferred_workout_time,
        $injuries_limitations, $focus_areas, $workout_type, $trainer_guidance,
        $equipment_confidence
    );
}

if ($stmt->execute()) {
    sendResponse(true, 'Questionnaire saved successfully');
} else {
    error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    sendResponse(false, 'Error saving questionnaire: ' . $stmt->error);
}

$conn->close();
?>
