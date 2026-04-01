<?php
// Create Manager Account Script
require_once 'api/config.php';
require_once 'api/session.php';

// Start session
session_start();

// Set admin session for this operation
$_SESSION['user_role'] = 'admin';
$_SESSION['user_id'] = 1;

echo "<h2>Creating Manager Account...</h2>";

// Step 1: Add Manager Role
echo "<h3>Step 1: Adding Manager Role to Database</h3>";

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

try {
    // Check if manager role already exists
    $checkRoleQuery = "SELECT DISTINCT role FROM users WHERE role = 'manager'";
    $result = $conn->query($checkRoleQuery);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: blue;'>✅ Manager role already exists in the database.</p>";
    } else {
        // Get current enum values
        $describeQuery = "DESCRIBE users";
        $result = $conn->query($describeQuery);
        $roleField = null;
        
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === 'role') {
                $roleField = $row;
                break;
            }
        }
        
        if ($roleField && preg_match("/enum\((.*)\)/", $roleField['Type'], $matches)) {
            $enumValues = str_getcsv($matches[1], ',', "'");
            
            if (!in_array('manager', $enumValues)) {
                $enumValues[] = 'manager';
                $newEnum = "enum('" . implode("','", $enumValues) . "')";
                
                $alterQuery = "ALTER TABLE users MODIFY role $newEnum NOT NULL DEFAULT 'user'";
                if ($conn->query($alterQuery)) {
                    echo "<p style='color: green;'>✅ Manager role successfully added to users table.</p>";
                } else {
                    throw new Exception('Failed to add manager role: ' . $conn->error);
                }
            }
        }
    }
    
    // Step 2: Create Manager Account
    echo "<h3>Step 2: Creating Manager Account</h3>";
    
    // Manager account details
    $name = "Gym Manager";
    $email = "manager@fitgym.com";
    $password = "manager123";
    $contact = "0917-234-5678";
    $address = "Gym Office, Fitness Center";
    
    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p style='color: orange;'>⚠️ Manager account with email '$email' already exists.</p>";
    } else {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert manager account
        $insertUser = $conn->prepare("INSERT INTO users (name, email, password, role, contact, address, email_verified, created_at) VALUES (?, ?, ?, 'manager', ?, ?, 1, NOW())");
        $insertUser->bind_param("sssss", $name, $email, $hashedPassword, $contact, $address);
        
        if ($insertUser->execute()) {
            $managerId = $insertUser->insert_id;
            echo "<p style='color: green;'>✅ Manager account created successfully!</p>";
            echo "<p><strong>Login Details:</strong></p>";
            echo "<ul>";
            echo "<li><strong>Email:</strong> $email</li>";
            echo "<li><strong>Password:</strong> $password</li>";
            echo "<li><strong>Name:</strong> $name</li>";
            echo "<li><strong>Role:</strong> Manager</li>";
            echo "</ul>";
            echo "<p style='color: blue;'>📝 Please save these login credentials for the manager.</p>";
        } else {
            throw new Exception('Failed to create manager account: ' . $insertUser->error);
        }
        $insertUser->close();
    }
    $checkEmail->close();
    
    // Step 3: Show all managers
    echo "<h3>Step 3: Current Managers in System</h3>";
    
    $sql = "SELECT id, name, email, role, contact, created_at FROM users WHERE role = 'manager' ORDER BY created_at DESC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Created</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['contact']) . "</td>";
            echo "<td>" . date('M j, Y', strtotime($row['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No managers found in the system.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
} finally {
    $conn->close();
}

echo "<hr>";
echo "<h3>✅ Manager Setup Complete!</h3>";
echo "<p>You can now:</p>";
echo "<ul>";
echo "<li>📱 Access the manager login at: <a href='views/login.php'>Login Page</a></li>";
echo "<li>🔧 Use the manager interface for gym management</li>";
echo "<li>👥 Create additional managers using the setup interface</li>";
echo "</ul>";
?>
