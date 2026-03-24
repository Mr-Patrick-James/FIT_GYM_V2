<?php
require_once '../config.php';
require_once '../session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(false, 'No photo uploaded or upload error', null, 400);
    }

    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif'];

    if (!in_array($file['type'], $allowedTypes)) {
        sendResponse(false, 'Invalid file type. Only JPG, PNG, WEBP, AVIF allowed.', null, 400);
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        sendResponse(false, 'File too large. Max 5MB.', null, 400);
    }

    $uploadDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'progress' . DIRECTORY_SEPARATOR;
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename  = 'progress_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath  = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        sendResponse(false, 'Failed to save uploaded file', null, 500);
    }

    sendResponse(true, 'Photo uploaded', ['url' => 'assets/uploads/progress/' . $filename]);

} catch (Exception $e) {
    sendResponse(false, 'Upload error: ' . $e->getMessage(), null, 500);
}
?>
