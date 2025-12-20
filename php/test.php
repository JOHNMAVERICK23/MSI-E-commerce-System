<?php
// File: php/test.php
// DATABASE CONNECTION TEST - DEBUG FILE

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Test 1: Check if MySQLi is available
echo "=== TEST 1: MySQLi Extension ===\n";
if (extension_loaded('mysqli')) {
    echo "✓ MySQLi extension is loaded\n";
} else {
    echo "✗ MySQLi extension is NOT loaded\n";
}

// Test 2: Try to connect to database
echo "\n=== TEST 2: Database Connection ===\n";

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'msi_ecommerce';

echo "Connecting to: $db_host\n";
echo "Database: $db_name\n";
echo "User: $db_user\n";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    echo "✗ Connection failed: " . $conn->connect_error . "\n";
    echo "\nPossible issues:\n";
    echo "1. XAMPP MySQL is not running\n";
    echo "2. Database 'msi_ecommerce' doesn't exist\n";
    echo "3. Wrong credentials in config.php\n";
} else {
    echo "✓ Connected successfully!\n";
    
    // Test 3: Check tables
    echo "\n=== TEST 3: Database Tables ===\n";
    
    $tables = ['users', 'products', 'orders', 'order_items'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' NOT found\n";
        }
    }
    
    // Test 4: Check users
    echo "\n=== TEST 4: Users in Database ===\n";
    
    $result = $conn->query("SELECT id, username, role FROM users");
    if ($result && $result->num_rows > 0) {
        echo "✓ Found " . $result->num_rows . " users:\n";
        while ($row = $result->fetch_assoc()) {
            echo "  - " . $row['username'] . " (Role: " . $row['role'] . ")\n";
        }
    } else {
        echo "✗ No users found in database\n";
    }
    
    // Test 5: Try login with admin
    echo "\n=== TEST 5: Test Login ===\n";
    
    $username = 'admin';
    $password = 'admin';
    
    $query = "SELECT id, username, email, role, status FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo "✗ Prepare failed: " . $conn->error . "\n";
    } else {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            echo "✓ User 'admin' found\n";
            echo "  - ID: " . $user['id'] . "\n";
            echo "  - Email: " . $user['email'] . "\n";
            echo "  - Role: " . $user['role'] . "\n";
            echo "  - Status: " . $user['status'] . "\n";
        } else {
            echo "✗ User 'admin' NOT found\n";
        }
        
        $stmt->close();
    }
    
    $conn->close();
    echo "\n✓ All tests completed!\n";
}

?>