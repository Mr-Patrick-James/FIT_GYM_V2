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
    // Provide a specific, user-friendly message per PHP upload error code
    if (!isset($_FILES['receipt'])) {
        sendResponse(false, 'No receipt file was received. Please select a file and try again.', null, 400);
    }

    $uploadError = $_FILES['receipt']['error'];
    if ($uploadError !== UPLOAD_ERR_OK) {
        $uploadErrorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'The file is too large. Please compress the image or use a smaller screenshot (max 10MB).',
            UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the allowed size limit.',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected. Please choose your GCash receipt image.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error (no temp directory). Please contact support.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not save the file. Please contact support.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension. Please contact support.',
        ];
        $msg = $uploadErrorMessages[$uploadError] ?? 'Upload error (code ' . $uploadError . '). Please try again.';
        sendResponse(false, $msg, null, 400);
    }

    $file = $_FILES['receipt'];
    
    // Validate file type — accept common image formats including HEIC/WEBP from mobile
    $allowedTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/heic',
        'image/heif',
        'application/pdf',
    ];

    // Use finfo for more reliable MIME detection (not just the browser-reported type)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Fall back to browser-reported type if finfo returns a generic type
    $fileType = $detectedType ?: $file['type'];

    if (!in_array($fileType, $allowedTypes)) {
        sendResponse(false, 'Invalid file type "' . htmlspecialchars($fileType) . '". Only JPG, PNG, WEBP, HEIC, and PDF files are allowed.', null, 400);
    }
    
    // Validate file size (max 10MB — GCash screenshots from modern phones can be large)
    if ($file['size'] > 10 * 1024 * 1024) {
        sendResponse(false, 'File too large. Maximum size is 10MB. Please compress the screenshot and try again.', null, 400);
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts' . DIRECTORY_SEPARATOR;
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            sendResponse(false, 'Failed to create upload directory. Please contact support.', null, 500);
        }
    }
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Normalise HEIC -> jpg extension for broader browser compatibility
    if (in_array($extension, ['heic', 'heif'])) {
        $extension = 'jpg';
    }
    $filename = 'receipt_' . $_SESSION['user_id'] . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        sendResponse(false, 'Failed to save the uploaded file. Please try again.', null, 500);
    }
    
    // Return the file path (relative to project root)
    $relativePath = 'api/uploads/receipts/' . $filename;
    
    sendResponse(true, 'Receipt uploaded successfully', ['url' => $relativePath]);

} catch (Exception $e) {
    error_log("Error uploading receipt: " . $e->getMessage());
    sendResponse(false, 'Error uploading receipt: ' . $e->getMessage(), null, 500);
}
?>