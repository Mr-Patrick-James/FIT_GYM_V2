<?php
require_once '../config.php';

// Create packages table
$sql = "
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    tag VARCHAR(50),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_tag (tag)
)";

$conn = getDBConnection();

if ($conn->query($sql) === TRUE) {
    echo "Packages table created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>