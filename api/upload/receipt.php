<?php
require_once '../config.php';
require_once '../session.php';

// Allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

try {
    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'No receipt file uploaded or upload error', null, 400);
    }

    $file = $_FILES['receipt'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $fileType = $file['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        sendResponse(false, 'Invalid file type. Only JPG, PNG, and PDF files are allowed.', null, 400);
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        sendResponse(false, 'File too large. Maximum size is 5MB.', null, 400);
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts' . DIRECTORY_SEPARATOR;
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            sendResponse(false, 'Failed to create upload directory. Please check permissions.', null, 500);
        }
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'receipt_' . $_SESSION['user_id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        sendResponse(false, 'Failed to save uploaded file', null, 500);
    }
    
    // Return the file path (relative to project root)
    $relativePath = 'uploads/receipts/' . $filename;
    
    sendResponse(true, 'Receipt uploaded successfully', ['url' => $relativePath]);

} catch (Exception $e) {
    error_log("Error uploading receipt: " . $e->getMessage());
    sendResponse(false, 'Error uploading receipt: ' . $e->getMessage(), null, 500);
}
?>