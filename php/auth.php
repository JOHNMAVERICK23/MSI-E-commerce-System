<?php
// File: php/auth.php
// AUTHENTICATION - DEMO MODE (SIMPLIFIED)

header('Content-Type: application/json');
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'login_customer':
        handleCustomerLogin();
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
        break;
}

/**
 * HANDLE LOGIN - ADMIN & STAFF ONLY
 */
function handleLogin() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? trim($input['password']) : '';

    echo json_encode(['debug' => "Login attempt: $username / $password"]);
    
    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Username and password required']);
        return;
    }

    $query = "SELECT id, username, email, role, status FROM users WHERE username = ? AND role IN ('admin', 'staff')";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        return;
    }

    $user = $result->fetch_assoc();

    if ($user['status'] === 'inactive') {
        echo json_encode(['status' => 'error', 'message' => 'Account is inactive']);
        return;
    }

    // DEMO MODE - Accept "admin" as password for all users
    if ($password === 'admin') {
        $token = bin2hex(random_bytes(32));
        $conn->query("UPDATE users SET updated_at = NOW() WHERE id = {$user['id']}");

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => [
                'id' => intval($user['id']),
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'token' => $token
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
    }
    
    $stmt->close();
}

/**
 * HANDLE CUSTOMER LOGIN
 */
function handleCustomerLogin() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? trim($input['password']) : '';

    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Username and password required']);
        return;
    }

    $query = "SELECT id, username, email, role, status, password FROM users WHERE username = ? AND role = 'customer'";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        return;
    }

    $user = $result->fetch_assoc();

    if ($user['status'] === 'inactive') {
        echo json_encode(['status' => 'error', 'message' => 'Account is inactive']);
        return;
    }

    // DEMO MODE - Compare plain text password
    if ($password === $user['password']) {
        $token = bin2hex(random_bytes(32));
        $conn->query("UPDATE users SET updated_at = NOW() WHERE id = {$user['id']}");

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => [
                'id' => intval($user['id']),
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'token' => $token
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
    }
    
    $stmt->close();
}
function registerCustomer() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $firstName = isset($input['firstName']) ? trim($input['firstName']) : '';
    $lastName = isset($input['lastName']) ? trim($input['lastName']) : '';
    $username = isset($input['username']) ? trim($input['username']) : '';
    $email = isset($input['email']) ? trim($input['email']) : '';
    $password = isset($input['password']) ? trim($input['password']) : '';
    $phone = isset($input['phone']) ? trim($input['phone']) : '';

    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields required']);
        return;
    }

    if (strlen($password) < 3) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 3 characters']);
        return;
    }

    // Check if username exists
    $check = $conn->query("SELECT id FROM users WHERE username = '$username' LIMIT 1");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        return;
    }

    // Check if email exists
    $check = $conn->query("SELECT id FROM users WHERE email = '$email' LIMIT 1");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
        return;
    }

    // DEMO MODE - Store password as plain text (NOT for production!)
    $username = $conn->real_escape_string($username);
    $email = $conn->real_escape_string($email);
    $password = $conn->real_escape_string($password);
    
    $query = "INSERT INTO users (username, email, password, role, status) 
              VALUES ('$username', '$email', '$password', 'customer', 'active')";
    
    if ($conn->query($query)) {
        $customerId = $conn->insert_id;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Account created successfully',
            'customer_id' => $customerId
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error creating account']);
    }
}

/**
 * CREATE NEW STAFF ACCOUNT
 */
function createStaff() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = isset($input['username']) ? trim($input['username']) : '';
    $email = isset($input['email']) ? trim($input['email']) : '';
    $password = isset($input['password']) ? trim($input['password']) : '';

    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields required']);
        return;
    }

    $check = $conn->query("SELECT id FROM users WHERE username = '$username' LIMIT 1");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username already exists']);
        return;
    }

    $check = $conn->query("SELECT id FROM users WHERE email = '$email' LIMIT 1");
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        return;
    }

    $username = $conn->real_escape_string($username);
    $email = $conn->real_escape_string($email);
    $password = $conn->real_escape_string($password);
    
    $query = "INSERT INTO users (username, email, password, role, status) 
              VALUES ('$username', '$email', '$password', 'staff', 'active')";
    
    if ($conn->query($query)) {
        $staffId = $conn->insert_id;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Staff account created successfully',
            'staff_id' => $staffId
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error']);
    }
}

/**
 * LIST ALL STAFF MEMBERS
 */
function listStaff() {
    global $conn;
    
    $query = "SELECT id, username, email, status, created_at 
              FROM users 
              WHERE role = 'staff' 
              ORDER BY created_at DESC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        return;
    }
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = [
            'id' => intval($row['id']),
            'username' => $row['username'],
            'email' => $row['email'],
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode(['status' => 'success', 'count' => count($staff), 'data' => $staff]);
}

/**
 * TOGGLE STAFF STATUS
 */
function toggleStaffStatus() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $staffId = isset($input['id']) ? intval($input['id']) : 0;

    if ($staffId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid staff ID']);
        return;
    }

    $result = $conn->query("SELECT status FROM users WHERE id = $staffId AND role = 'staff'");
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Staff not found']);
        return;
    }

    $row = $result->fetch_assoc();
    $newStatus = ($row['status'] === 'active') ? 'inactive' : 'active';

    $query = "UPDATE users SET status = '$newStatus', updated_at = NOW() WHERE id = $staffId";
    
    if ($conn->query($query)) {
        echo json_encode(['status' => 'success', 'message' => 'Status updated', 'new_status' => $newStatus]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error']);
    }
}

/**
 * DELETE STAFF ACCOUNT
 */
function deleteStaff() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $staffId = isset($input['id']) ? intval($input['id']) : 0;

    if ($staffId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid staff ID']);
        return;
    }

    $query = "DELETE FROM users WHERE id = $staffId AND role = 'staff'";
    
    if ($conn->query($query)) {
        if ($conn->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Staff deleted']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Staff not found']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error']);
    }
}

?>