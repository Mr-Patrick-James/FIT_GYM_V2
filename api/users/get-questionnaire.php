<?php
require_once '../config.php';
require_once '../session.php';

if (!isLoggedIn()) {
    sendResponse(false, 'Unauthorized access');
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];

// If requesting someone else's profile, must be admin or trainer
if ($user_id !== $_SESSION['user_id'] && !isAdmin() && !isTrainer()) {
    sendResponse(false, 'Unauthorized access to other user profiles');
}

$conn = getDBConnection();
$sql = "SELECT * FROM user_questionnaire WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    sendResponse(true, 'Questionnaire retrieved successfully', $data);
} else {
    sendResponse(false, 'No questionnaire found');
}

$conn->close();
?>
