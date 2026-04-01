<?php
// Add Manager Role to Users Table and Create Manager Account
require_once 'config.php';
require_once 'session.php';

// Check if user is admin or manager
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Admin or Manager privileges required.'
    ]);
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

try {
    // Step 1: Check if manager role already exists in the database
    $checkRoleQuery = "SELECT DISTINCT role FROM users WHERE role = 'manager'";
    $result = $conn->query($checkRoleQuery);
    
    if ($result && $result->num_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Manager role already exists in the database.',
            'role_added' => false
        ]);
        exit;
    }
    
    // Step 2: Since role is likely an ENUM, we need to modify the table to add 'manager'
    // First, let's check the current ENUM values
    $describeQuery = "DESCRIBE users";
    $result = $conn->query($describeQuery);
    $roleField = null;
    
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'role') {
            $roleField = $row;
            break;
        }
    }
    
    if (!$roleField) {
        throw new Exception('Role field not found in users table');
    }
    
    // Extract current ENUM values
    $type = $roleField['Type'];
    if (preg_match("/enum\((.*)\)/", $type, $matches)) {
        $enumValues = str_getcsv($matches[1], ',', "'");
        
        // Add 'manager' to the enum values if not already present
        if (!in_array('manager', $enumValues)) {
            $enumValues[] = 'manager';
            $newEnum = "enum('" . implode("','", $enumValues) . "')";
            
            // Modify the table to add manager to the enum
            $alterQuery = "ALTER TABLE users MODIFY role $newEnum NOT NULL DEFAULT 'user'";
            if (!$conn->query($alterQuery)) {
                throw new Exception('Failed to add manager role to enum: ' . $conn->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Manager role successfully added to users table.',
                'role_added' => true,
                'enum_values' => $enumValues
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Manager role already exists in enum.',
                'role_added' => false
            ]);
        }
    } else {
        throw new Exception('Role field is not an enum type');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>
