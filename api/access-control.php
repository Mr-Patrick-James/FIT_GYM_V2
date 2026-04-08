<?php
// Enhanced Access Control System
require_once 'config.php';

/**
 * Role Hierarchy: admin > manager > trainer > user
 * Each role has access to everything below it plus specific permissions
 */

// Role hierarchy levels (higher number = higher privilege)
define('ROLE_LEVELS', [
    'user' => 1,
    'trainer' => 2,
    'manager' => 3,
    'admin' => 4
]);

// Role permissions
define('ROLE_PERMISSIONS', [
    'user' => [
        'view_own_profile',
        'view_own_bookings',
        'view_own_payments',
        'manage_own_progress',
        'view_trainer_info'
    ],
    'trainer' => [
        'view_own_profile',
        'view_own_bookings',
        'view_own_payments',
        'manage_own_progress',
        'view_trainer_info',
        'manage_assigned_clients',
        'view_client_progress',
        'create_training_plans',
        'manage_client_sessions'
    ],
    'manager' => [
        'all_permissions' // Manager has almost all permissions like admin
    ],
    'admin' => [
        'all_permissions' // Admin has access to everything
    ]
]);

/**
 * Check if user has specific permission
 */
function hasPermission($permission, $userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['user_role'] ?? null;
    }
    
    if (!$userRole) {
        return false;
    }
    
    // Admin has all permissions
    if ($userRole === 'admin') {
        return true;
    }
    
    $rolePerms = ROLE_PERMISSIONS[$userRole] ?? [];
    return in_array($permission, $rolePerms) || in_array('all_permissions', $rolePerms);
}

/**
 * Check if user can access a specific role level or higher
 */
function hasRoleLevel($requiredLevel, $userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['user_role'] ?? null;
    }
    
    if (!$userRole) {
        return false;
    }
    
    $userLevel = ROLE_LEVELS[$userRole] ?? 0;
    $required = ROLE_LEVELS[$requiredLevel] ?? 0;
    
    return $userLevel >= $required;
}

/**
 * Require specific permission or deny access
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Insufficient permissions.',
            'required_permission' => $permission
        ]);
        exit;
    }
}

/**
 * Require specific role level or deny access
 */
function requireRoleLevel($role) {
    if (!hasRoleLevel($role)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Access denied. {$role} privileges required.",
            'required_role' => $role
        ]);
        exit;
    }
}

/**
 * Get user's accessible roles (for dropdowns, etc.)
 */
function getAccessibleRoles($userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['user_role'] ?? null;
    }
    
    $userLevel = ROLE_LEVELS[$userRole] ?? 0;
    $accessibleRoles = [];
    
    foreach (ROLE_LEVELS as $role => $level) {
        if ($level <= $userLevel) {
            $accessibleRoles[] = $role;
        }
    }
    
    return $accessibleRoles;
}

/**
 * Check if user can manage another user
 */
function canManageUser($targetUserId, $currentUserId = null, $currentUserRole = null) {
    if ($currentUserId === null) {
        $currentUserId = $_SESSION['user_id'] ?? null;
    }
    
    if ($currentUserRole === null) {
        $currentUserRole = $_SESSION['user_role'] ?? null;
    }
    
    // Users can always manage themselves
    if ($targetUserId == $currentUserId) {
        return true;
    }
    
    // Admin can manage everyone
    if ($currentUserRole === 'admin') {
        return true;
    }
    
    // Manager can manage everyone except other admins (but can manage other managers)
    if ($currentUserRole === 'manager') {
        // Need to check target user's role from database
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->bind_param("i", $targetUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $targetUser = $result->fetch_assoc();
            $stmt->close();
            
            if ($targetUser) {
                $targetRole = $targetUser['role'];
                // Manager can manage users, trainers, and other managers, but not admins
                return in_array($targetRole, ['user', 'trainer', 'manager']);
            }
        } catch (Exception $e) {
            error_log("Error checking user management permission: " . $e->getMessage());
        }
    }
    
    return false;
}

/**
 * Send standardized access denied response
 */
function sendAccessDenied($message = 'Access denied') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error_code' => 'ACCESS_DENIED'
    ]);
    exit;
}

?>
