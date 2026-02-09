<?php
require_once 'api/config.php';

$conn = getDBConnection();

$sql = "CREATE TABLE IF NOT EXISTS gym_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table gym_settings created successfully. ";
    
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
        $stmt = $conn->prepare("INSERT IGNORE INTO gym_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    echo "Default settings seeded.";
} else {
    echo "Error creating table: " . $conn->error;
}
$conn->close();
?>
