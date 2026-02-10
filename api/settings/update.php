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
    
    // We'll handle existing_gallery separately as well
    $existingGallery = [];
    if (isset($settings['existing_gallery'])) {
        $existingGallery = json_decode($settings['existing_gallery'], true);
        unset($settings['existing_gallery']);
    }

    foreach ($settings as $key => $value) {
        // Skip keys that are handled by file uploads or special logic
        if (strpos($key, 'gallery_file_') === 0 || 
            $key === 'current_password' || 
            $key === 'new_password' || 
            $key === 'admin_name' || 
            $key === 'admin_email') continue;
        
        $stmt = $conn->prepare("INSERT INTO gym_settings (setting_key, setting_value) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
    }

    // Handle Admin Profile Update (Name & Email)
    if (isset($_POST['admin_name']) || isset($_POST['admin_email'])) {
        $user = getCurrentUser();
        $userId = $user['id'];
        $newName = $_POST['admin_name'] ?? $user['name'];
        $newEmail = $_POST['admin_email'] ?? $user['email'];

        // Update user in database
        $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $updateStmt->bind_param("ssi", $newName, $newEmail, $userId);
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update admin profile.");
        }

        // Update session data
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_name'] = $newName;
        $_SESSION['user_email'] = $newEmail;
    }

    // Handle Password Change
    if (isset($_POST['current_password']) && isset($_POST['new_password'])) {
        $user = getCurrentUser();
        $userId = $user['id'];
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();

        if ($userData && password_verify($currentPassword, $userData['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userId);
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update password in database.");
            }
        } else {
            throw new Exception("Incorrect current password.");
        }
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

    // Handle Gallery Image Uploads
    $newGalleryPaths = [];
    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'gallery_file_') === 0 && $file['error'] === UPLOAD_ERR_OK) {
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileExt = explode('.', $fileName);
            $fileActualExt = strtolower(end($fileExt));

            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($fileActualExt, $allowed) && $fileSize < 5000000) {
                $fileNameNew = "about_gallery_" . uniqid('', true) . "." . $fileActualExt;
                $uploadDir = '../../uploads/settings/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                if (move_uploaded_file($fileTmpName, $uploadDir . $fileNameNew)) {
                    $newGalleryPaths[] = 'uploads/settings/' . $fileNameNew;
                }
            }
        }
    }

    // Merge existing and new paths, then save as JSON
    $finalGallery = array_merge($existingGallery, $newGalleryPaths);
    $galleryJson = json_encode($finalGallery);
    
    $key = 'about_images';
    $stmt = $conn->prepare("INSERT INTO gym_settings (setting_key, setting_value) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $galleryJson, $galleryJson);
    $stmt->execute();

    sendResponse(true, 'Settings updated successfully');

} catch (Exception $e) {
    sendResponse(false, 'Error updating settings: ' . $e->getMessage(), null, 500);
}
?>
