<?php
require_once '../config.php';
require_once '../session.php';

// Only admins can update settings
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

try {
    $conn = getDBConnection();
    
    // Handle text settings
    $settings = $_POST;
    unset($settings['qr_image']); // Handled separately

    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO gym_settings (setting_key, setting_value) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
    }

    // Handle QR Image Upload
    if (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['qr_image'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];
        $fileType = $file['type'];

        $fileExt = explode('.', $fileName);
        $fileActualExt = strtolower(end($fileExt));

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileActualExt, $allowed)) {
            if ($fileSize < 5000000) { // 5MB limit
                $fileNameNew = "gcash_qr_" . time() . "." . $fileActualExt;
                $uploadDir = '../../uploads/settings/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $fileDestination = $uploadDir . $fileNameNew;
                
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    $dbPath = 'uploads/settings/' . $fileNameNew;
                    
                    // Update database
                    $key = 'gcash_qr_path';
                    $stmt = $conn->prepare("INSERT INTO gym_settings (setting_key, setting_value) VALUES (?, ?) 
                                            ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->bind_param("sss", $key, $dbPath, $dbPath);
                    $stmt->execute();
                } else {
                    throw new Exception("Failed to move uploaded file.");
                }
            } else {
                throw new Exception("File is too large.");
            }
        } else {
            throw new Exception("Invalid file type.");
        }
    }

    sendResponse(true, 'Settings updated successfully');

} catch (Exception $e) {
    sendResponse(false, 'Error updating settings: ' . $e->getMessage(), null, 500);
}
?>
