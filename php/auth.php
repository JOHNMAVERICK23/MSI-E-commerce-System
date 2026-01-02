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
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function handleUniversalLogin() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Username and password required']);
        return;
    }
    
    // Try to find user
    $stmt = $conn->prepare("SELECT id, username, email, password, role, status FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        return;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user['status'] !== 'active') {
        echo json_encode(['status' => 'error', 'message' => 'Account is inactive']);
        return;
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
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
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
    }
}
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
    
    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
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
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
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

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'staff')");
    $stmt->bind_param('sss', $username, $email, $hashed_password);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Staff account created']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error creating staff account']);
    }
    $stmt->close();
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
        echo json_encode(['status' => 'success', 'message' => 'Staff deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Staff not found or error deleting']);
    }
    $stmt->close();
}

?>