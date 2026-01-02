<?php
// File: php/products.php
// UPDATED WITH BETTER DEBUGGING

header('Content-Type: application/json');
require_once 'config.php';

// Start session for debugging
session_start();

// Log the request for debugging
error_log("=== PRODUCTS.PH ACCESSED ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET params: " . print_r($_GET, true));
error_log("POST params: " . print_r($_POST, true));
error_log("FILES: " . print_r($_FILES, true));

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Processing POST request");
    
    // Check if it's form data with file upload
    if (!empty($_FILES)) {
        error_log("File upload detected");
        $action = $_POST['action'] ?? $action;
    } 
    // Check if it's JSON data
    else if (empty($_POST) && empty($_FILES)) {
        error_log("JSON data detected");
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $action = $input['action'] ?? $action;
            // Also populate $_POST with JSON data for backward compatibility
            $_POST = $input;
        }
    }
    // Regular form data without file
    else {
        error_log("Regular form data detected");
        $action = $_POST['action'] ?? $action;
    }
}

error_log("Final action: " . $action);

switch ($action) {
    case 'list':
        listProducts();
        break;
    case 'get':
        getProduct();
        break;
    case 'create':
        createProduct();
        break;
    case 'update':
        updateProduct();
        break;
    case 'delete':
        deleteProduct();
        break;
    case 'get_by_category':
        getProductsByCategory();
        break;
    default:
        error_log("Invalid action requested: " . $action);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action: ' . $action]);
        break;
}

function listProducts() {
    global $conn;
    
    error_log("Listing products");
    
    $query = "SELECT id, name, category, description, price, stock, image_url, status, created_at 
              FROM products 
              WHERE status = 'active' 
              ORDER BY created_at DESC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("Database error: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Set default image if none
        if (empty($row['image_url'])) {
            $row['image_url'] = 'assets/default-product.png';
        }
        $products[] = $row;
    }
    
    error_log("Found " . count($products) . " products");
    
    echo json_encode([
        'status' => 'success',
        'count' => count($products),
        'data' => $products
    ]);
}

function createProduct() {
    global $conn;
    
    error_log("Creating product");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    // Handle image upload
    $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        error_log("Processing image upload");
        $imageUrl = handleImageUpload($_FILES['image']);
        error_log("Image URL: " . $imageUrl);
    }
    
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    
    error_log("Product data - Name: $name, Category: $category, Price: $price, Stock: $stock");
    
    // Validation
    if (empty($name)) {
        error_log("Validation failed: Name is required");
        echo json_encode(['status' => 'error', 'message' => 'Product name is required']);
        return;
    }
    
    if ($price <= 0) {
        error_log("Validation failed: Invalid price");
        echo json_encode(['status' => 'error', 'message' => 'Price must be greater than 0']);
        return;
    }
    
    if ($stock < 0) {
        error_log("Validation failed: Invalid stock");
        echo json_encode(['status' => 'error', 'message' => 'Stock cannot be negative']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO products (name, category, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param('sssdis', $name, $category, $description, $price, $stock, $imageUrl);
    
    if ($stmt->execute()) {
        $productId = $stmt->insert_id;
        error_log("Product created successfully with ID: " . $productId);
        echo json_encode([
            'status' => 'success', 
            'message' => 'Product created successfully',
            'id' => $productId
        ]);
    } else {
        error_log("Execute failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create product: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function handleImageUpload($file) {
    error_log("Handling image upload");
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error: " . $file['error']);
        return '';
    }
    
    $uploadDir = '../uploads/products/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            error_log("Failed to create directory: " . $uploadDir);
            return '';
        }
    }
    
    // Generate unique filename
    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\-]/', '_', basename($file['name']));
    $uploadPath = $uploadDir . $fileName;
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        error_log("Invalid file type: " . $file['type']);
        return '';
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        error_log("File too large: " . $file['size']);
        return '';
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        error_log("File uploaded successfully: " . $fileName);
        return 'uploads/products/' . $fileName;
    } else {
        error_log("Failed to move uploaded file");
        return '';
    }
}

function getProduct() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    
    error_log("Getting product with ID: " . $id);
    
    if (!$id) {
        error_log("Invalid product ID");
        echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("Product not found with ID: " . $id);
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        $stmt->close();
        return;
    }
    
    $product = $result->fetch_assoc();
    $stmt->close();
    
    error_log("Product found: " . $product['name']);
    
    echo json_encode(['status' => 'success', 'data' => $product]);
}

function updateProduct() {
    global $conn;
    
    error_log("Updating product");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    $id = intval($_POST['id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    
    error_log("Update data - ID: $id, Name: $name, Price: $price");
    
    if ($id <= 0 || empty($name) || $price <= 0) {
        error_log("Validation failed for update");
        echo json_encode(['status' => 'error', 'message' => 'Invalid product data']);
        return;
    }
    
    // Handle image upload if new image provided
    $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        error_log("New image provided for update");
        $imageUrl = handleImageUpload($_FILES['image']);
    }
    
    if (!empty($imageUrl)) {
        // Update with new image
        error_log("Updating with new image: " . $imageUrl);
        $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ?, image_url = ? WHERE id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            return;
        }
        $stmt->bind_param('sssdisi', $name, $category, $description, $price, $stock, $imageUrl, $id);
    } else {
        // Update without changing image
        error_log("Updating without changing image");
        $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ? WHERE id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
            return;
        }
        $stmt->bind_param('sssdii', $name, $category, $description, $price, $stock, $id);
    }
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        error_log("Update executed. Affected rows: " . $affectedRows);
        
        if ($affectedRows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product not found or no changes made']);
        }
    } else {
        error_log("Execute failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update product: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function deleteProduct() {
    global $conn;
    
    error_log("Deleting product (soft delete)");
    
    // Get input from JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    error_log("Product ID to delete: " . $id);
    
    if ($id <= 0) {
        error_log("Invalid product ID for deletion");
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        error_log("Delete executed. Affected rows: " . $affectedRows);
        
        if ($affectedRows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        }
    } else {
        error_log("Execute failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete product: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function getProductsByCategory() {
    global $conn;
    
    $category = $conn->real_escape_string($_GET['category'] ?? '');
    
    if (!$category) {
        echo json_encode(['status' => 'error', 'message' => 'Category required']);
        return;
    }
    
    $query = "SELECT * FROM products WHERE category = '$category' AND status = 'active' ORDER BY name ASC";
    $result = $conn->query($query);
    
    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => count($products),
        'data' => $products
    ]);
}
?>