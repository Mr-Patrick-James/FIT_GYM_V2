# APPENDIX F – PROGRAM LISTING

This appendix contains the actual important program code from the core modules of the system. This file is separate from the README and includes selected code excerpts only.

---

## 1. Main Entry Page

File: `index.php`

```php
<?php
require_once 'api/session.php';
require_once 'api/config.php';

// Fetch active packages for the landing page
$packages = [];
$settings = [];
$activeMemberCount = 0;
try {
    $conn = getDBConnection();

    // Fetch packages
    $result = $conn->query("SELECT * FROM packages WHERE is_active = 1 ORDER BY price ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
    }

    // Fetch gym settings
    $result = $conn->query("SELECT setting_key, setting_value FROM gym_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    // Fetch real active members count (users with role 'user')
    $countResult = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    if ($countResult) {
        $row = $countResult->fetch_assoc();
        $activeMemberCount = $row['total'];
    }

    // --- AUTO-SETUP WHO PLAN ---
    $whoCheck = $conn->query("SELECT id FROM packages WHERE name = 'WHO Health & Fitness Plan'");
    if ($whoCheck && $whoCheck->num_rows === 0) {
        $who_name = "WHO Health & Fitness Plan";
        $who_duration = "Weekly (WHO Standard)";
        $who_price = 450.00;
        $who_tag = "Health Standard";
        $who_desc = "Scientifically designed plan based on WHO (World Health Organization) physical activity guidelines for adults.";

        $stmt = $conn->prepare("INSERT INTO packages (name, duration, price, tag, description, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("ssdss", $who_name, $who_duration, $who_price, $who_tag, $who_desc);
        $stmt->execute();
        $whoId = $conn->insert_id;

        if ($whoId) {
            $ex_ids = [];
            $res = $conn->query("SELECT id, name FROM exercises");
            while ($row = $res->fetch_assoc()) {
                $ex_ids[$row['name']] = $row['id'];
            }

            $assignments = [
                ['Treadmill Jogging', 1, '30 mins (Aerobic)'],
                ['Stationary Cycling', 1, '20 mins (Aerobic)'],
                ['Smith Machine Squat', 3, '12-15 (Strength)'],
                ['Flat Barbell Bench Press', 3, '12-15 (Strength)']
            ];

            $stmt_ex = $conn->prepare("INSERT INTO package_exercises (package_id, exercise_id, sets, reps, notes) VALUES (?, ?, ?, ?, 'WHO Standard')");
            foreach ($assignments as $a) {
                if (isset($ex_ids[$a[0]])) {
                    $ex_id = $ex_ids[$a[0]];
                    $stmt_ex->bind_param("iiis", $whoId, $ex_id, $a[1], $a[2]);
                    $stmt_ex->execute();
                }
            }
            header("Location: index.php");
            exit();
        }
    }
} catch (Exception $e) {
    error_log("Error fetching data for index: " . $e->getMessage());
}

function getSetting($key, $default = '', $settings = [])
{
    return $settings[$key] ?? $default;
}

if (isLoggedIn() && !isset($_GET['auth']) && !isset($_POST['auth'])) {
    $redirect = (isAdmin() || isManager()) ? 'views/admin/dashboard.php' : 'views/user/dashboard.php';
    header("Location: $redirect");
    exit();
}
```

---

## 2. Database and Core Configuration

File: `api/config.php`

```php
<?php
// Load environment variables from .env file
$envLoaded = false;
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        $envLoaded = true;
    }
}

function getEnvVar($key, $default = '') {
    return $_ENV[$key] ?? $default;
}

$is_local = true;

if ($is_local) {
    define('DB_HOST', getEnvVar('DB_HOST', 'localhost'));
    define('DB_USER', getEnvVar('DB_USER', 'root'));
    define('DB_PASS', getEnvVar('DB_PASS', ''));
    define('DB_NAME', getEnvVar('DB_NAME', 'fitpay_gym'));
} else {
    define('DB_HOST', getEnvVar('DB_HOST', 'sql109.infinityfree.com'));
    define('DB_USER', getEnvVar('DB_USER', 'if0_40968761'));
    define('DB_PASS', getEnvVar('DB_PASS', ''));
    define('DB_NAME', getEnvVar('DB_NAME', 'if0_40968761_fitpay_gym'));
}

function getDBConnection() {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        $errorMsg = "Database connection failed: " . $conn->connect_error;
        error_log($errorMsg);

        if (stripos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
            if (ob_get_length()) ob_clean();
            header("Content-Type: application/json");
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection error. Please contact administrator.',
                'debug' => $errorMsg
            ]);
            exit();
        }

        die($errorMsg . " (Check your credentials in config.php)");
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

function createNotification($userId, $title, $message, $type = 'info') {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}

function sendResponse($success, $message, $data = null, $statusCode = 200) {
    if (!headers_sent()) {
        header("Content-Type: application/json");
    }
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}
```

---

## 3. Session and Authentication

File: `api/session.php`

```php
<?php
ob_start();

if (!defined('SESSION_COOKIE_PATH')) {
    define('SESSION_COOKIE_PATH', '/');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => SESSION_COOKIE_PATH,
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if (!session_start()) {
        error_log("ERROR: Failed to start session");
    }
}

require_once 'access-control.php';

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $loggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    return $loggedIn;
}

function isAdmin() {
    return hasRoleLevel('admin');
}

function isManager() {
    return hasRoleLevel('manager');
}

function isTrainer() {
    return hasRoleLevel('trainer');
}

function validateSession() {
    if (!isLoggedIn()) {
        if (isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required',
                'error_code' => 'AUTH_REQUIRED'
            ]);
            exit;
        } else {
            header('Location: ' . $basePath . '/views/login.php');
            exit;
        }
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'contact' => $_SESSION['user_contact'] ?? null,
        'address' => $_SESSION['user_address'] ?? null
    ];
}

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['user_email']);

    if (!$isLoggedIn) {
        if (isApiRequest()) {
            if (!headers_sent()) {
                header("Content-Type: application/json");
            }
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized access. Please log in.',
                'error_code' => 'UNAUTHORIZED'
            ]);
            exit();
        }

        header("Location: index.php");
        exit();
    }
}
```

---

## 4. Role Permission System

File: `api/access-control.php`

```php
<?php
require_once 'config.php';

define('ROLE_LEVELS', [
    'user' => 1,
    'trainer' => 2,
    'manager' => 3,
    'admin' => 4
]);

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
    'manager' => ['all_permissions'],
    'admin' => ['all_permissions']
]);

function hasPermission($permission, $userRole = null) {
    if ($userRole === null) {
        $userRole = $_SESSION['user_role'] ?? null;
    }

    if (!$userRole) {
        return false;
    }

    if ($userRole === 'admin') {
        return true;
    }

    $rolePerms = ROLE_PERMISSIONS[$userRole] ?? [];
    return in_array($permission, $rolePerms) || in_array('all_permissions', $rolePerms);
}

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

function requireLogin() {
    // implemented in session.php
}

function canManageUser($targetUserId, $currentUserId = null, $currentUserRole = null) {
    if ($currentUserId === null) {
        $currentUserId = $_SESSION['user_id'] ?? null;
    }

    if ($targetUserId == $currentUserId) {
        return true;
    }

    if ($currentUserRole === 'admin') {
        return true;
    }

    if ($currentUserRole === 'manager') {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->bind_param("i", $targetUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $targetUser = $result->fetch_assoc();

            if ($targetUser) {
                $targetRole = $targetUser['role'];
                return in_array($targetRole, ['user', 'trainer', 'manager']);
            }
        } catch (Exception $e) {
            error_log("Error checking user management permission: " . $e->getMessage());
        }
    }

    return false;
}
```

---

## 5. Login API

File: `api/auth/login.php`

```php
<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();
$email = trim(strtolower($data['email'] ?? ''));
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    sendResponse(false, 'Email and password are required', null, 400);
}

try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, name, email, password, role, contact, address, email_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        sendResponse(false, 'Invalid email or password', null, 401);
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $user['password'])) {
        $conn->close();
        sendResponse(false, 'Invalid email or password', null, 401);
    }

    if ($user['role'] === 'user' && !$user['email_verified']) {
        $conn->close();
        sendResponse(false, 'Please verify your email before logging in', null, 403);
    }

    $conn->close();
} catch (Exception $e) {
    error_log("Login Error: " . $e->getMessage());
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}

unset($user['password']);
require_once '../session.php';
setUserSession($user);

if ($user['role'] === 'admin') {
    $redirect = 'views/admin/dashboard.php';
} elseif ($user['role'] === 'manager') {
    $redirect = 'views/admin/dashboard.php';
} elseif ($user['role'] === 'trainer') {
    $redirect = 'views/trainer/dashboard.php';
} else {
    $redirect = 'views/user/dashboard.php';
}

sendResponse(true, 'Login successful', [
    'user' => $user,
    'redirect' => $redirect
]);
```

---

## 6. Booking Creation API

File: `api/bookings/create.php`

```php
<?php
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendResponse(false, 'Invalid request data', null, 400);
}

$package_name = $data['package'] ?? null;
$booking_date = $data['date'] ?? null;
$contact = $data['contact'] ?? null;
$notes = $data['notes'] ?? null;
$receipt_url = $data['receipt'] ?? null;
$user_id = $_SESSION['user_id'];

$userQuery = "SELECT name, email FROM users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

if (!$user) {
    sendResponse(false, 'User not found', null, 404);
}

$packageQuery = "SELECT id, price, duration FROM packages WHERE name = ? AND is_active = 1";
$packageStmt = $conn->prepare($packageQuery);
$packageStmt->bind_param("s", $package_name);
$packageStmt->execute();
$packageResult = $packageStmt->get_result();
$package = $packageResult->fetch_assoc();

if (!$package) {
    sendResponse(false, 'Package not found or inactive', null, 404);
}

if (!$package_name || !$booking_date || !$contact) {
    sendResponse(false, 'Missing required fields', null, 400);
}

$expiresAt = null;
$duration = $package['duration'] ?? '';
$days = 0;

if (stripos($duration, 'Day') !== false) {
    $days = (int)$duration;
} elseif (stripos($duration, 'Week') !== false) {
    $days = (int)$duration * 7;
} elseif (stripos($duration, 'Month') !== false) {
    $days = (int)$duration * 30;
} elseif (stripos($duration, 'Year') !== false) {
    $days = (int)$duration * 365;
} else {
    $days = (int) $duration;
}

if ($days > 0) {
    $expiresAt = date('Y-m-d H:i:s', strtotime($booking_date . " + $days days"));
}

$sql = "INSERT INTO bookings (user_id, name, email, contact, package_id, package_name, amount, booking_date, expires_at, notes, receipt_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssisdssss", 
    $user_id,
    $user['name'],
    $user['email'],
    $contact,
    $package['id'],
    $package_name,
    $package['price'],
    $booking_date,
    $expiresAt,
    $notes,
    $receipt_url
);

$result = $stmt->execute();

if (!$result) {
    sendResponse(false, 'Failed to create booking: ' . $conn->error, null, 500);
}

$booking_id = $conn->insert_id;
sendResponse(true, 'Booking created successfully', ['id' => $booking_id]);
```

---

## 7. Package Creation API

File: `api/packages/create.php`

```php
<?php
ob_start();
require_once '../config.php';
require_once '../email.php';
ob_end_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = getRequestData();

$name = trim($data['name'] ?? '');
$duration = trim($data['duration'] ?? '');
$price = trim($data['price'] ?? '');
$tag = trim($data['tag'] ?? '');
$description = trim($data['description'] ?? '');
$goal = trim($data['goal'] ?? 'General Fitness');
$dietInfo = trim($data['diet_info'] ?? '');
$guidanceInfo = trim($data['guidance_info'] ?? '');
$isTrainerAssisted = isset($data['is_trainer_assisted']) ? (bool)$data['is_trainer_assisted'] : false;
$trainerIds = $data['trainer_ids'] ?? [];

if (empty($name) || empty($duration) || empty($price)) {
    sendResponse(false, 'Package name, duration, and price are required', null, 400);
}

$cleanPrice = preg_replace('/[₱,]/', '', $price);
if (!is_numeric($cleanPrice)) {
    sendResponse(false, 'Invalid price format', null, 400);
}

$priceValue = floatval($cleanPrice);

if ($priceValue <= 0) {
    sendResponse(false, 'Price must be greater than zero', null, 400);
}

$conn = getDBConnection();
$conn->begin_transaction();

try {
    $checkDiet = $conn->query("SHOW COLUMNS FROM packages LIKE 'diet_info'");
    $hasDiet = ($checkDiet && $checkDiet->num_rows > 0);
    $checkGuidance = $conn->query("SHOW COLUMNS FROM packages LIKE 'guidance_info'");
    $hasGuidance = ($checkGuidance && $checkGuidance->num_rows > 0);

    $fields = ["name", "duration", "price", "tag", "description", "is_trainer_assisted", "goal"];
    if ($hasDiet) $fields[] = "diet_info";
    if ($hasGuidance) $fields[] = "guidance_info";

    $placeholders = array_fill(0, count($fields), "?");
    $sql = "INSERT INTO packages (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";

    $stmt = $conn->prepare($sql);
    $isTrainerAssistedInt = $isTrainerAssisted ? 1 : 0;

    if ($hasDiet && $hasGuidance) {
        $stmt->bind_param("ssdssisss", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal, $dietInfo, $guidanceInfo);
    } elseif ($hasDiet) {
        $stmt->bind_param("ssdssiss", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal, $dietInfo);
    } elseif ($hasGuidance) {
        $stmt->bind_param("ssdssiss", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal, $guidanceInfo);
    } else {
        $stmt->bind_param("ssdssis", $name, $duration, $priceValue, $tag, $description, $isTrainerAssistedInt, $goal);
    }

    if ($stmt->execute()) {
        $packageId = $conn->insert_id;

        if (!empty($trainerIds) && $isTrainerAssisted) {
            $trainerStmt = $conn->prepare("INSERT INTO package_trainers (package_id, trainer_id) VALUES (?, ?)");
            foreach ($trainerIds as $trainerId) {
                $tId = (int)$trainerId;
                $trainerStmt->bind_param("ii", $packageId, $tId);
                $trainerStmt->execute();
            }
            $trainerStmt->close();
        }

        $conn->commit();
        $stmt->close();
        $conn->close();
        sendResponse(true, 'Package created successfully', ['id' => $packageId]);
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, 'Failed to create package: ' . $e->getMessage());
}
```

---

## 8. Trainer Customized Exercise Plan

File: `api/trainers/save-member-plan.php`

```php
<?php
require_once '../config.php';
require_once '../session.php';
requireTrainer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['booking_id']) || !isset($data['exercises'])) {
    sendResponse(false, 'Booking ID and exercises are required', null, 400);
}

$booking_id = (int)$data['booking_id'];
$exercises = $data['exercises'];
$user = getCurrentUser();

$conn = getDBConnection();
$conn->begin_transaction();

try {
    $trainerStmt = $conn->prepare("SELECT id FROM trainers WHERE user_id = ?");
    $trainerStmt->bind_param("i", $user['id']);
    $trainerStmt->execute();
    $trainer = $trainerStmt->get_result()->fetch_assoc();
    $trainerId = $trainer['id'];

    $checkStmt = $conn->prepare("
        SELECT b.id, p.is_trainer_assisted 
        FROM bookings b 
        JOIN packages p ON b.package_id = p.id 
        WHERE b.id = ? AND b.trainer_id = ?
    ");
    $checkStmt->bind_param("ii", $booking_id, $trainerId);
    $checkStmt->execute();
    $booking = $checkStmt->get_result()->fetch_assoc();

    if (!$booking) {
        throw new Exception('Unauthorized or booking not found');
    }

    if (!$booking['is_trainer_assisted']) {
        throw new Exception('This package does not allow trainer-customized exercise plans.');
    }

    $conn->query("DELETE FROM member_exercise_plans WHERE booking_id = $booking_id");

    $stmt = $conn->prepare("INSERT INTO member_exercise_plans (booking_id, exercise_id, sets, reps, notes) VALUES (?, ?, ?, ?, ?)");
    foreach ($exercises as $ex) {
        $ex_id = (int)$ex['id'];
        $sets = (int)($ex['sets'] ?? 3);
        $reps = $ex['reps'] ?? '10-12';
        $notes = $ex['notes'] ?? '';

        $stmt->bind_param("iiiss", $booking_id, $ex_id, $sets, $reps, $notes);
        if (!$stmt->execute()) {
            throw new Exception('Failed to save exercise plan: ' . $stmt->error);
        }
    }

    $conn->commit();
    $conn->close();
    sendResponse(true, 'Exercise plan customized successfully');

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    sendResponse(false, $e->getMessage());
}
```

---

## 9. Email System

File: `api/email.php`

```php
<?php
if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/config.php';
}

function getEmailConfig() {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM email_configs WHERE is_active = 1 AND is_default = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $config = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            if (!empty($config['smtp_username']) && !empty($config['smtp_password'])) {
                return [
                    'smtp_host'     => $config['smtp_host'],
                    'smtp_port'     => (int)$config['smtp_port'],
                    'smtp_username' => $config['smtp_username'],
                    'smtp_password' => $config['smtp_password'],
                    'from_email'    => $config['from_email'],
                    'from_name'     => $config['from_name']
                ];
            }
        }
    } catch (Exception $e) {
        // Fall back to .env
    }

    return [
        'smtp_host'     => $_ENV['SMTP_HOST']       ?? 'smtp.gmail.com',
        'smtp_port'     => (int)($_ENV['SMTP_PORT'] ?? 587),
        'smtp_username' => $_ENV['SMTP_USERNAME']   ?? '',
        'smtp_password' => $_ENV['SMTP_PASSWORD']   ?? '',
        'from_email'    => $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@martinezfitness.com',
        'from_name'     => $_ENV['SMTP_FROM_NAME']  ?? 'Martinez Fitness'
    ];
}

function sendOTPEmail($email, $otp, $name = '') {
    global $phpmailerInstalled;

    if ($phpmailerInstalled) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $config = getEmailConfig();

            if (!empty($config['smtp_username']) && !empty($config['smtp_password'])) {
                $mail->isSMTP();
                $mail->Host       = $config['smtp_host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $config['smtp_username'];
                $mail->Password   = $config['smtp_password'];
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $config['smtp_port'];
                $mail->setFrom($config['from_email'], $config['from_name']);
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Email - Martinez Fitness';
                $mail->Body = '<p>Your OTP code is: ' . $otp . '</p>';
                $mail->send();
                return true;
            }
        } catch (Exception $e) {
            error_log("PHPMailer failed: " . $e->getMessage());
        }
    }

    return sendOTPEmailSimple($email, $otp, $name);
}
```

---

## 10. User Profile Update

File: `api/users/update-profile.php`

```php
<?php
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed', null, 405);
}

if (!isset($_SESSION['user_id'])) {
    sendResponse(false, 'Unauthorized access', null, 401);
}

$data = getRequestData();
$userId = $_SESSION['user_id'];
$name = trim($data['name'] ?? '');
$contact = trim($data['contact'] ?? '');
$address = trim($data['address'] ?? '');
$weight = $data['weight'] ?? null;
$height = $data['height'] ?? null;

if (empty($name)) {
    sendResponse(false, 'Name is required', null, 400);
}

try {
    $conn = getDBConnection();

    $sql = "UPDATE users SET name = ?, contact = ?, address = ?";
    $types = "sss";
    $params = [$name, $contact, $address];

    if ($weight !== null) {
        $sql .= ", weight = ?";
        $types .= "d";
        $params[] = $weight;
    }

    if ($height !== null) {
        $sql .= ", height = ?";
        $types .= "d";
        $params[] = $height;
    }

    $sql .= " WHERE id = ?";
    $types .= "i";
    $params[] = $userId;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['user_name'] = $name;
        $_SESSION['user_contact'] = $contact;
        $_SESSION['user_address'] = $address;

        sendResponse(true, 'Profile updated successfully', [
            'id' => $userId,
            'name' => $name,
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role']
        ]);
    } else {
        sendResponse(false, 'Failed to update profile');
    }
} catch (Exception $e) {
    error_log("Profile Update Error: " . $e->getMessage());
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}
```

---

## 11. Notes

The code above represents the most important application logic for this project. It includes:
- app bootstrap and entry point
- database connection and environment setup
- login and session management
- access control and role permissions
- booking and package creation
- trainer plan customization
- user profile updates
- email sending utilities

This appendix intentionally omits unrelated helper scripts and test files.
