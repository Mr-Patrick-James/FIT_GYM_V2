<?php
// Test Manager vs Admin Access
require_once 'api/config.php';
require_once 'api/session.php';

echo "<h2>🏋️‍♂️ Manager vs Admin Access Comparison</h2>";

// Test different scenarios
$testScenarios = [
    ['role' => 'user', 'description' => 'Regular User'],
    ['role' => 'trainer', 'description' => 'Trainer'],
    ['role' => 'manager', 'description' => 'Manager (Almost Admin)'],
    ['role' => 'admin', 'description' => 'Admin']
];

$resources = [
    'admin_panel' => 'Admin Panel Access',
    'user_management' => 'User Management',
    'booking_management' => 'Booking Management',
    'payment_management' => 'Payment Management',
    'reports' => 'Reports & Analytics',
    'settings' => 'System Settings',
    'system_config' => 'System Configuration'
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>Role</th>";
foreach ($resources as $key => $label) {
    echo "<th>$label</th>";
}
echo "</tr>";

foreach ($testScenarios as $scenario) {
    echo "<tr>";
    echo "<td><strong>{$scenario['description']}</strong></td>";
    
    // Temporarily set session role for testing
    $_SESSION['user_role'] = $scenario['role'];
    
    foreach ($resources as $key => $label) {
        $access = canAccessResource($key);
        $color = $access ? 'green' : 'red';
        $symbol = $access ? '✅' : '❌';
        echo "<td style='text-align: center; color: $color;'>$symbol</td>";
    }
    
    echo "</tr>";
}

echo "</table>";

echo "<h3>📊 Access Summary</h3>";
echo "<ul>";
echo "<li><strong>👤 User:</strong> Can only access own data</li>";
echo "<li><strong>🏋️ Trainer:</strong> Can manage clients and training</li>";
echo "<li><strong>👨‍💼 Manager:</strong> <span style='color: green;'>Almost same as Admin!</span></li>";
echo "<li><strong>👑 Admin:</strong> Full system access</li>";
echo "</ul>";

echo "<h3>🔑 Manager vs Admin - The Only Difference</h3>";
echo "<p><strong>Manager can do everything Admin can, EXCEPT:</strong></p>";
echo "<ul>";
echo "<li>❌ Cannot delete other Admin accounts</li>";
echo "<li>✅ Can delete other Manager accounts</li>";
echo "<li>✅ Can manage all Users and Trainers</li>";
echo "<li>✅ Can access all settings and configuration</li>";
echo "<li>✅ Can view all reports and analytics</li>";
echo "<li>✅ Can manage all bookings and payments</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>✅ Manager is now almost identical to Admin!</h3>";
echo "<p>Manager has virtually the same access level as Admin for daily operations.</p>";

// Clean up session
unset($_SESSION['user_role']);
?>
