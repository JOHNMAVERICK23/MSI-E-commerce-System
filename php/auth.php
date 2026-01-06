<?php
header('Content-Type: application/json');
require_once 'config.php';

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $action = $_GET['action'] ?? '';
}

switch ($action) {
    case 'login':
        handleUniversalLogin();
        break;
    case 'register_customer':
        registerCustomer();
        break;
    case 'create_staff':
        createStaff();
        break;
    case 'list_staff':
        listStaff();
        break;
    case 'toggle_staff_status':
        toggleStaffStatus();
        break;
    case 'delete_staff':
        deleteStaff();
        break;
    case 'validate_password':
        validatePasswordStrength();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * IMPROVED LOGIN WITH COOLDOWN & ATTEMPT TRACKING
 */
function handleUniversalLogin() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Username and password required']);
        return;
    }
    
    // Check login attempts
    $attemptKey = 'login_attempts_' . $username;
    $cooldownKey = 'login_cooldown_' . $username;
    
    // Check if user is in cooldown
    if (isset($_SESSION[$cooldownKey]) && time() < $_SESSION[$cooldownKey]) {
        $remainingTime = ceil(($_SESSION[$cooldownKey] - time()) / 60);
        echo json_encode([
            'status' => 'error',
            'message' => "Too many failed attempts. Please try again in $remainingTime minutes.",
            'cooldown' => true
        ]);
        return;
    }
    
    // Try to find user
    $stmt = $conn->prepare("SELECT id, username, email, password, role, status FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        trackFailedAttempt($username, $attemptKey, $cooldownKey);
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        $stmt->close();
        return;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user['status'] !== 'active') {
        trackFailedAttempt($username, $attemptKey, $cooldownKey);
        echo json_encode(['status' => 'error', 'message' => 'Account is inactive']);
        return;
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Clear failed attempts on success
        unset($_SESSION[$attemptKey]);
        unset($_SESSION[$cooldownKey]);
        
        // Remove password from response
        unset($user['password']);
        
        // If customer, get additional info
        if ($user['role'] === 'customer') {
            $stmt = $conn->prepare("SELECT first_name, last_name, phone FROM customers WHERE user_id = ?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $customerResult = $stmt->get_result();
            
            if ($customerResult->num_rows > 0) {
                $customerData = $customerResult->fetch_assoc();
                $user = array_merge($user, $customerData);
            }
            $stmt->close();
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        
        // Log login activity
        logActivity($user['id'], 'LOGIN', 'User logged in successfully');
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    } else {
        trackFailedAttempt($username, $attemptKey, $cooldownKey);
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
    }
}

/**
 * TRACK FAILED LOGIN ATTEMPTS
 */
function trackFailedAttempt($username, $attemptKey, $cooldownKey) {
    // Get current attempts
    $attempts = isset($_SESSION[$attemptKey]) ? intval($_SESSION[$attemptKey]) : 0;
    $attempts++;
    
    $_SESSION[$attemptKey] = $attempts;
    
    // After 3 failed attempts, set cooldown (3 minutes = 180 seconds)
    if ($attempts >= 3) {
       $_SESSION[$cooldownKey] = time() + (3 * 60); 
      // $_SESSION[$cooldownKey] = time() + 30;
    }
}

/**
 * REGISTER CUSTOMER WITH PASSWORD VALIDATION
 */
function registerCustomer() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $firstName = trim($input['firstName'] ?? '');
    $lastName = trim($input['lastName'] ?? '');
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $phone = trim($input['phone'] ?? '');
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($username) || 
        empty($email) || empty($password) || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        return;
    }
    
    // Validate password strength
    $passwordValidation = validatePasswordStrengthInternal($password);
    if (!$passwordValidation['valid']) {
        echo json_encode(['status' => 'error', 'message' => $passwordValidation['message']]);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
        return;
    }
    
    // Validate phone (basic)
    if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone) || strlen($phone) < 10) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid phone number']);
        return;
    }
    
    // Check if username/email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username or email already exists']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Hash password with BCRYPT
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')");
        $stmt->bind_param('sss', $username, $email, $hashedPassword);
        $stmt->execute();
        $userId = $conn->insert_id;
        $stmt->close();
        
        // Insert into customers table
        $stmt = $conn->prepare("INSERT INTO customers (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $userId, $firstName, $lastName, $phone);
        $stmt->execute();
        $stmt->close();
        
        // Log activity
        logActivity($userId, 'REGISTER', 'New customer account created');
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Registration successful! You can now login.'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $e->getMessage()]);
    }
}

/**
 * CREATE STAFF WITH PASSWORD VALIDATION
 */
function createStaff() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);

    $username = $input['username'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields required']);
        return;
    }

    // Validate password strength
    $passwordValidation = validatePasswordStrengthInternal($password);
    if (!$passwordValidation['valid']) {
        echo json_encode(['status' => 'error', 'message' => $passwordValidation['message']]);
        return;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username or email already exists']);
        $stmt->close();
        return;
    }
    $stmt->close();

    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'staff')");
    $stmt->bind_param('sss', $username, $email, $hashed_password);

    if ($stmt->execute()) {
        $staffId = $conn->insert_id;
        logActivity($staffId, 'STAFF_CREATE', 'New staff account created');
        echo json_encode(['status' => 'success', 'message' => 'Staff account created']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error creating staff account']);
    }
    $stmt->close();
}

/**
 * VALIDATE PASSWORD STRENGTH (8-15 chars, uppercase, lowercase, number, symbol)
 */
function validatePasswordStrengthInternal($password) {
    $minLength = 8;
    $maxLength = 15;
    
    // Check length
    if (strlen($password) < $minLength || strlen($password) > $maxLength) {
        return [
            'valid' => false,
            'message' => "Password must be between $minLength and $maxLength characters"
        ];
    }
    
    // Check for uppercase
    if (!preg_match('/[A-Z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one uppercase letter (A-Z)'
        ];
    }
    
    // Check for lowercase
    if (!preg_match('/[a-z]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one lowercase letter (a-z)'
        ];
    }
    
    // Check for number
    if (!preg_match('/[0-9]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one number (0-9)'
        ];
    }
    
    // Check for special character
    if (!preg_match('/[!@#$%^&*()_\-+=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        return [
            'valid' => false,
            'message' => 'Password must contain at least one special character (!@#$%^&*...)'
        ];
    }
    
    return ['valid' => true, 'message' => 'Password is strong'];
}

/**
 * VALIDATE PASSWORD STRENGTH (API ENDPOINT)
 */
function validatePasswordStrength() {
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    
    $result = validatePasswordStrengthInternal($password);
    echo json_encode($result);
}

/**
 * LIST ALL STAFF MEMBERS
 */
function listStaff() {
    global $conn;
    $result = $conn->query("SELECT id, username, email, status, created_at FROM users WHERE role = 'staff' ORDER BY created_at DESC");
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $staff]);
}

/**
 * TOGGLE STAFF STATUS
 */
function toggleStaffStatus() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    $staffId = intval($input['id'] ?? 0);

    if ($staffId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid staff ID']);
        return;
    }

    $stmt = $conn->prepare("UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id = ? AND role = 'staff'");
    $stmt->bind_param('i', $staffId);
    if ($stmt->execute()) {
        logActivity($staffId, 'STATUS_TOGGLE', 'Staff status toggled');
        echo json_encode(['status' => 'success', 'message' => 'Status updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error updating status']);
    }
    $stmt->close();
}

/**
 * DELETE STAFF ACCOUNT
 */
function deleteStaff() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    $staffId = intval($input['id'] ?? 0);

    if ($staffId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid staff ID']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
    $stmt->bind_param('i', $staffId);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        logActivity($staffId, 'STAFF_DELETE', 'Staff account deleted');
        echo json_encode(['status' => 'success', 'message' => 'Staff deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Staff not found or error deleting']);
    }
    $stmt->close();
}

/**
 * LOG ACTIVITY
 */
function logActivity($userId, $action, $details = '') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
    $fullAction = $action . ($details ? ': ' . $details : '');
    $stmt->bind_param('is', $userId, $fullAction);
    $stmt->execute();
    $stmt->close();
}

?>