<?php
require_once '../config.php';
require_once '../session.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " START\nSCRIPT=" . basename(__FILE__) . "\nREQUEST_METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? '') . "\nSESSION=" . var_export($_SESSION, true) . "\n\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

if (!isLoggedIn()) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

$user_id = $_SESSION['user_id'];
$data = getRequestData();

// Fallback if JSON body parsing failed
if ((!is_array($data) || empty($data)) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody = file_get_contents('php://input');
    if (!empty($rawBody)) {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $decoded;
        } else {
            error_log('save-questionnaire.php JSON parse error: ' . json_last_error_msg() . ' raw=' . substr($rawBody, 0, 1000));
        }
    }
}

if (!is_array($data) || empty($data)) {
    file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " INVALID REQUEST DATA\n" . var_export($data, true) . "\nBODY=" . file_get_contents('php://input') . "\nSESSION=" . var_export($_SESSION, true) . "\n\n", FILE_APPEND);
    sendResponse(false, 'Invalid request data', null, 400);
}

file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " REQUEST\n" . json_encode([
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'user_id' => $_SESSION['user_id'] ?? null,
    'payload' => $data
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

$conn = getDBConnection();
file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " DB CONNECTED\n\n", FILE_APPEND);

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
if (!$stmt) {
    $prepareError = "Prepare failed on check: (" . $conn->errno . ") " . $conn->error;
    file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " CHECK PREPARE ERROR\n" . $prepareError . "\nSQL=" . $check_sql . "\n\n", FILE_APPEND);
    sendResponse(false, 'Server error preparing query', null, 500);
}
$stmt->bind_param("i", $user_id);
$bindOk = $stmt->execute();
file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " CHECK EXECUTE " . ($bindOk ? 'OK' : 'FAIL') . "\nstmt_errno=" . $stmt->errno . " stmt_error=" . $stmt->error . "\n\n", FILE_APPEND);
$result = $stmt->get_result();
file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " CHECK GET_RESULT " . ($result === false ? 'FALSE' : 'OK') . "\n\n", FILE_APPEND);

if ($result === false) {
    sendResponse(false, 'Server error retrieving questionnaire check', null, 500);
}

if ($result->num_rows > 0) {
    // Update existing profile
    $sql = "UPDATE user_questionnaire SET 
            age = ?, sex = ?, height = ?, weight = ?, medical_conditions = ?, exercise_history = ?,
            primary_goal = ?, goal_pace = ?, workout_days_per_week = ?, preferred_workout_time = ?,
            injuries_limitations = ?, focus_areas = ?, workout_type = ?, trainer_guidance = ?,
            equipment_confidence = ?
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $prepareError = "Prepare failed on update: (" . $conn->errno . ") " . $conn->error;
        error_log($prepareError);
        file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " PREPARE ERROR\n" . $prepareError . "\nSQL=" . $sql . "\n\n", FILE_APPEND);
        sendResponse(false, 'Error preparing questionnaire update: ' . $conn->error, null, 500);
    }
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
    if (!$stmt) {
        $prepareError = "Prepare failed on insert: (" . $conn->errno . ") " . $conn->error;
        error_log($prepareError);
        file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " PREPARE ERROR\n" . $prepareError . "\nSQL=" . $sql . "\n\n", FILE_APPEND);
        sendResponse(false, 'Error saving questionnaire: prepare failed', null, 500);
    }
    $stmt->bind_param("iisddsssssssssss", 
        $user_id, $age, $sex, $height, $weight, $medical_conditions, $exercise_history,
        $primary_goal, $goal_pace, $workout_days_per_week, $preferred_workout_time,
        $injuries_limitations, $focus_areas, $workout_type, $trainer_guidance,
        $equipment_confidence
    );
}

if ($stmt->execute()) {
    file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " SUCCESS\n" . json_encode(['user_id' => $user_id, 'user_id_session' => $_SESSION['user_id'], 'payload' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
    sendResponse(true, 'Questionnaire saved successfully');
} else {
    $errorMessage = "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    error_log($errorMessage);
    file_put_contents(__DIR__ . '/save-questionnaire-debug.log', date('c') . " SQL ERROR\n" . $errorMessage . "\n\n", FILE_APPEND);
    sendResponse(false, 'Error saving questionnaire: ' . $stmt->error, null, 500);
}

$conn->close();
?>
