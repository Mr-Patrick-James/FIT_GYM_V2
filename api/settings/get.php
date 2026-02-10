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
        'gym_address' => '123 Fitness Ave, Metro Manila',
        'gym_contact' => '0917-123-4567',
        'gym_email' => 'info@martinezfitness.com',
        'gcash_number' => '0917-123-4567',
        'gcash_name' => 'Martinez Fitness',
        'gcash_qr_path' => '',
        'payment_instructions' => 'Please send payment via GCash to the number above. Include your name and booking reference in the payment notes.',
        'about_text' => 'Martinez Fitness Gym is more than just a place to work out. We are a community dedicated to helping you reach your peak physical condition through elite training, state-of-the-art equipment, and a supportive environment.',
        'mission_text' => 'Founded with the mission to provide high-quality fitness access to everyone, we offer flexible membership plans and expert guidance to ensure you get the most out of every session.',
        'years_experience' => '10+',
        'footer_tagline' => 'Pushing your limits since 2014. Join the elite fitness community today.',
        'admin_name' => 'Admin Martinez',
        'admin_email' => 'admin@martinezfitness.com',
        'email_new_booking' => 'true',
        'email_payment_verified' => 'true',
        'email_daily_report' => 'false',
        'browser_new_booking' => 'true',
        'browser_payment_verified' => 'true',
        'notification_sound' => 'true'
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

    // Override admin_name and admin_email with current user's data if logged in
    require_once '../session.php';
    if (isLoggedIn()) {
        $user = getCurrentUser();
        $raw_settings['admin_name'] = $user['name'];
        $raw_settings['admin_email'] = $user['email'];
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
