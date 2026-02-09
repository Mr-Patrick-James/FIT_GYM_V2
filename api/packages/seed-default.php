<?php
require_once '../api/config.php';

$conn = getDBConnection();

// Insert default packages
$defaultPackages = [
    [
        'name' => 'Walk-in Pass',
        'duration' => '1 Day',
        'price' => 200.00,
        'tag' => 'Basic',
        'description' => 'Perfect for trying out our facilities'
    ],
    [
        'name' => 'Weekly Pass',
        'duration' => '7 Days',
        'price' => 500.00,
        'tag' => 'Popular',
        'description' => 'Great for short-term fitness goals'
    ],
    [
        'name' => 'Monthly Membership',
        'duration' => '30 Days',
        'price' => 1500.00,
        'tag' => 'Best Value',
        'description' => 'Most popular choice for regular gym-goers'
    ],
    [
        'name' => '3-Month Package',
        'duration' => '90 Days',
        'price' => 4000.00,
        'tag' => 'Premium',
        'description' => 'Save more with our 3-month package'
    ],
    [
        'name' => 'Annual Membership',
        'duration' => '1 Year',
        'price' => 15000.00,
        'tag' => 'VIP',
        'description' => 'Best value for long-term commitment'
    ]
];

$stmt = $conn->prepare("INSERT IGNORE INTO packages (name, duration, price, tag, description) VALUES (?, ?, ?, ?, ?)");

$inserted = 0;
foreach ($defaultPackages as $package) {
    $stmt->bind_param("ssdss", 
        $package['name'],
        $package['duration'], 
        $package['price'],
        $package['tag'],
        $package['description']
    );
    
    if ($stmt->execute()) {
        $inserted++;
    }
}

$stmt->close();
$conn->close();

echo "Successfully inserted $inserted default packages!";
?>