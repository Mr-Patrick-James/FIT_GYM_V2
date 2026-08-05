<?php
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    if (!isset($_FILES['student_id']) || $_FILES['student_id']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'No student ID file uploaded or upload error', null, 400);
    }

    $file = $_FILES['student_id'];

    // Only images allowed for student ID
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        sendResponse(false, 'Invalid file type. Only JPG, PNG, and WEBP images are allowed.', null, 400);
    }

    // Max 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        sendResponse(false, 'File too large. Maximum size is 5MB.', null, 400);
    }

    // Create upload directory
    $uploadDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student-ids' . DIRECTORY_SEPARATOR;
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            sendResponse(false, 'Failed to create upload directory. Please check permissions.', null, 500);
        }
    }

    // Unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename   = 'student_id_' . $_SESSION['user_id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath   = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        sendResponse(false, 'Failed to save uploaded file', null, 500);
    }

    $relativePath = 'uploads/student-ids/' . $filename;
    sendResponse(true, 'Student ID uploaded successfully', ['url' => $relativePath]);

} catch (Exception $e) {
    error_log("Error uploading student ID: " . $e->getMessage());
    sendResponse(false, 'Error uploading student ID: ' . $e->getMessage(), null, 500);
}
?>
