<?php
// File: php/auth.php
// FIXED & SECURED AUTHENTICATION LOGIC
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
        handleUniversalLogin(); // Gagamitin na natin itong isa para sa lahat
        break;
    case 'register_customer':
        registerCustomer();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified']);
        break;
}

/**
 * UNIVERSAL LOGIN - Para sa Admin, Staff, at Customer
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

    // Subukang hanapin ang user sa 'users' table
    $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
        return;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user['status'] === 'inactive') {
        echo json_encode(['status' => 'error', 'message' => 'Account is inactive']);
        return;
    }

    // I-verify ang password gamit ang hash
    if (password_verify($password, $user['password'])) {
        // Alisin ang password bago ipadala pabalik
        unset($user['password']);
        
        // Kung customer, kunin ang first_name at last_name
        if ($user['role'] === 'customer') {
            $stmt_customer = $conn->prepare("SELECT first_name, last_name FROM customers WHERE user_id = ?");
            $stmt_customer->bind_param('i', $user['id']);
            $stmt_customer->execute();
            $customer_result = $stmt_customer->get_result();
            if ($customer_result->num_rows > 0) {
                $customer_data = $customer_result->fetch_assoc();
                $user['first_name'] = $customer_data['first_name'];
                $user['last_name'] = $customer_data['last_name'];
            }
            $stmt_customer->close();
        }

        $token = bin2hex(random_bytes(32));
        echo json_encode(['status' => 'success', 'user' => $user, 'token' => $token]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid username or password']);
    }
}

/**
 * CUSTOMER REGISTRATION (Walang pinagbago dito, tama na ito)
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

    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Kailangan punan ang lahat ng fields.']);
        return;
    }

    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt_check->bind_param('ss', $username, $email);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Ang username o email ay nagamit na.']);
        $stmt_check->close();
        return;
    }
    $stmt_check->close();

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    $conn->begin_transaction();
    try {
        $stmt_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'customer')");
        $stmt_user->bind_param('sss', $username, $email, $hashed_password);
        $stmt_user->execute();
        $user_id = $conn->insert_id;
        $stmt_user->close();

        $stmt_customer = $conn->prepare("INSERT INTO customers (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)");
        $stmt_customer->bind_param('isss', $user_id, $firstName, $lastName, $phone);
        $stmt_customer->execute();
        $stmt_customer->close();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Account ay matagumpay na nagawa!']);

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $exception->getMessage()]);
    }
}
/**
 * CREATE NEW STAFF ACCOUNT
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