<?php
require_once '../config.php';

try {
    $conn = getDBConnection();
    
    // Check if table exists, if not create it (auto-setup)
    $sql = "CREATE TABLE IF NOT EXISTS gym_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($sql);

    // Fetch all settings
    $result = $conn->query("SELECT setting_key, setting_value FROM gym_settings");
    $settings = [];
    $raw_settings = [];
    
    while ($row = $result->fetch_assoc()) {
        $raw_settings[$row['setting_key']] = $row['setting_value'];
    }

    // Default values if empty
    $defaults = [
        'gym_name' => 'Martinez Fitness Gym',
        'gym_address' => '',
        'gym_contact' => '0917-123-4567',
        'gym_email' => 'info@martinezfitness.com',
        'gcash_number' => '0917-123-4567',
        'gcash_name' => 'Martinez Fitness',
        'gcash_qr_path' => '',
        'payment_instructions' => 'Please send payment via GCash to the number above. Include your name and booking reference in the payment notes.'
    ];

    foreach ($defaults as $key => $value) {
        if (!isset($raw_settings[$key])) {
            $raw_settings[$key] = $value;
            // Seed it into DB
            $stmt = $conn->prepare("INSERT IGNORE INTO gym_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
    }

    // Convert to array of objects for frontend consistency if needed, 
    // or just return as object map. The frontend seems to expect both ways in different places.
    // Let's return as a list of {setting_key, setting_value} for the user dashboard 
    // AND the object map for the admin settings.
    
    $formatted_data = [];
    foreach ($raw_settings as $key => $value) {
        $formatted_data[] = [
            'setting_key' => $key,
            'setting_value' => $value
        ];
    }

    sendResponse(true, 'Settings fetched successfully', $formatted_data);

} catch (Exception $e) {
    sendResponse(false, 'Error fetching settings: ' . $e->getMessage(), null, 500);
}
?>
