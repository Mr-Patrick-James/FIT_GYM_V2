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
    if (!isset($_FILES['student_id'])) {
        sendResponse(false, 'No student ID file was received. Please select a file and try again.', null, 400);
    }

    $uploadError = $_FILES['student_id']['error'];
    if ($uploadError !== UPLOAD_ERR_OK) {
        $uploadErrorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'The file is too large. Please compress the image or use a smaller photo (max 10MB).',
            UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the allowed size limit.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected. Please choose your Student ID photo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error (no temp directory). Please contact support.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not save the file. Please contact support.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension. Please contact support.',
        ];
        $msg = $uploadErrorMessages[$uploadError] ?? 'Upload error (code ' . $uploadError . '). Please try again.';
        sendResponse(false, $msg, null, 400);
    }

    $file = $_FILES['student_id'];

    // Accept common mobile image formats including HEIC
    $allowedTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    // Use finfo for reliable MIME detection instead of trusting browser-reported type
    $finfo       = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $fileType = $detectedType ?: $file['type'];

    if (!in_array($fileType, $allowedTypes)) {
        sendResponse(false, 'Invalid file type "' . htmlspecialchars($fileType) . '". Only JPG, PNG, WEBP, and HEIC images are allowed.', null, 400);
    }

    // Max 10MB — matches receipt.php and .htaccess limits
    if ($file['size'] > 10 * 1024 * 1024) {
        sendResponse(false, 'File too large. Maximum size is 10MB. Please compress the photo and try again.', null, 400);
    }

    // Create upload directory
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student-ids' . DIRECTORY_SEPARATOR;
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            sendResponse(false, 'Failed to create upload directory. Please contact support.', null, 500);
        }
    }

    // Unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Normalise HEIC -> jpg for broader browser compatibility
    if (in_array($extension, ['heic', 'heif'])) {
        $extension = 'jpg';
    }
    $filename = 'student_id_' . $_SESSION['user_id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        sendResponse(false, 'Failed to save the uploaded file. Please try again.', null, 500);
    }

    $relativePath = 'api/uploads/student-ids/' . $filename;
    sendResponse(true, 'Student ID uploaded successfully', ['url' => $relativePath]);

} catch (Exception $e) {
    error_log("Error uploading student ID: " . $e->getMessage());
    sendResponse(false, 'Error uploading student ID: ' . $e->getMessage(), null, 500);
}
?>
