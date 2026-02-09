<?php
require_once 'api/config.php';

echo "<h1>Setting up Bookings Table</h1>";

try {
    // Create bookings table
    $sql = "
    CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        contact VARCHAR(50),
        package_id INT,
        package_name VARCHAR(100),
        amount DECIMAL(10, 2) NOT NULL,
        booking_date DATE,
        status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        receipt_url VARCHAR(255),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_user_id (user_id),
        INDEX idx_package_id (package_id)
    )";

    $conn->exec($sql);
    echo "<p style='color: green;'>✓ Bookings table created successfully!</p>";
    
    // Create payments table
    $sql = "
    CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        booking_id INT,
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        payment_method VARCHAR(50),
        transaction_id VARCHAR(100),
        receipt_url VARCHAR(255),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_transaction_id (transaction_id),
        INDEX idx_user_id (user_id),
        INDEX idx_booking_id (booking_id)
    )";

    $conn->exec($sql);
    echo "<p style='color: green;'>✓ Payments table created successfully!</p>";
    
    // Insert some sample bookings
    $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $sampleBookings = [
            [
                'name' => 'John Dela Cruz',
                'email' => 'john.delacruz@email.com',
                'contact' => '0917-123-4567',
                'package_name' => 'Monthly Membership',
                'amount' => 1500.00,
                'status' => 'pending'
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@email.com',
                'contact' => '0918-234-5678',
                'package_name' => 'Annual Membership',
                'amount' => 15000.00,
                'status' => 'pending'
            ],
            [
                'name' => 'Carlos Rodriguez',
                'email' => 'carlos.rodriguez@email.com',
                'contact' => '0919-345-6789',
                'package_name' => 'Weekly Pass',
                'amount' => 500.00,
                'status' => 'verified'
            ],
            [
                'name' => 'Anna Garcia',
                'email' => 'anna.garcia@email.com',
                'contact' => '0912-456-7890',
                'package_name' => '3-Month Package',
                'amount' => 4000.00,
                'status' => 'verified'
            ]
        ];
        
        $insertStmt = $conn->prepare("
            INSERT INTO bookings (name, email, contact, package_name, amount, status) 
            VALUES (:name, :email, :contact, :package_name, :amount, :status)
        ");
        
        foreach ($sampleBookings as $booking) {
            $insertStmt->execute($booking);
        }
        
        echo "<p style='color: green;'>✓ Sample bookings inserted successfully!</p>";
    } else {
        echo "<p style='color: orange;'>ℹ Sample bookings already exist, skipping insertion.</p>";
    }

    echo "<h2>Setup Complete!</h2>";
    echo "<p>You can now access the bookings management page at: <a href='views/admin/bookings.php'>Bookings Admin Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>