<?php
// File: php/products.php
// COMPLETE FIX - ERROR LOADING PRODUCTS RESOLVED

header('Content-Type: application/json');
require_once 'config.php';

// LOG HELPER FUNCTION - para makita mo kung ano ang nangyayari
function logProductAction($message) {
    $logFile = __DIR__ . '/products_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    error_log($message);
}

// Simulan ang logging
logProductAction("=== PRODUCTS.PHP ACCESSED ===");
logProductAction("Method: " . $_SERVER['REQUEST_METHOD']);

// Determine action
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Para sa form data with files
    if (!empty($_FILES)) {
        $action = $_POST['action'] ?? '';
        logProductAction("POST with FILES detected, action: $action");
    } 
    // Para sa JSON data
    else {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $action = $input['action'] ?? '';
            $_POST = $input; // Para sa backward compatibility
            logProductAction("JSON POST detected, action: $action");
        }
    }
} else {
    // GET request
    $action = $_GET['action'] ?? '';
    logProductAction("GET request, action: $action");
}

logProductAction("Final action to process: " . $action);

// ROUTE THE ACTION
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
        logProductAction("⚠️ INVALID ACTION: " . $action);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Invalid action: ' . $action
        ]);
        break;
}

/**
 * LIST ALL PRODUCTS
 * UPDATED: Mas secure at may error handling
 */
function listProducts() {
    global $conn;
    
    logProductAction("📦 LISTING PRODUCTS");
    
    // Check connection first
    if (!$conn) {
        logProductAction("❌ Database connection failed");
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed'
        ]);
        return;
    }
    
    $query = "SELECT id, name, category, description, price, stock, image_url, status, created_at 
              FROM products 
              WHERE status = 'active' 
              ORDER BY created_at DESC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        logProductAction("❌ Database query error: " . $conn->error);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $conn->error
        ]);
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
    
    logProductAction("✅ Found " . count($products) . " products");
    
    echo json_encode([
        'status' => 'success',
        'count' => count($products),
        'data' => $products
    ]);
}

/**
 * CREATE PRODUCT
 * UPDATED: Better validation at image handling
 */
function createProduct() {
    global $conn;
    
    logProductAction("➕ CREATING PRODUCT");
    
    // Get values from POST
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    
    logProductAction("Product: Name=$name, Cat=$category, Price=$price, Stock=$stock");
    
    // Validation
    if (empty($name)) {
        logProductAction("⚠️ VALIDATION: Name is required");
        echo json_encode(['status' => 'error', 'message' => 'Product name is required']);
        return;
    }
    
    if ($price <= 0) {
        logProductAction("⚠️ VALIDATION: Price must be > 0");
        echo json_encode(['status' => 'error', 'message' => 'Price must be greater than 0']);
        return;
    }
    
    if ($stock < 0) {
        logProductAction("⚠️ VALIDATION: Stock cannot be negative");
        echo json_encode(['status' => 'error', 'message' => 'Stock cannot be negative']);
        return;
    }
    
    // Handle image upload
    $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        logProductAction("📸 Image upload detected");
        $imageUrl = handleImageUpload($_FILES['image']);
    }
    
    // Insert product
    $stmt = $conn->prepare("INSERT INTO products (name, category, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        logProductAction("❌ Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param('sssdis', $name, $category, $description, $price, $stock, $imageUrl);
    
    if ($stmt->execute()) {
        $productId = $stmt->insert_id;
        logProductAction("✅ Product created successfully with ID: $productId");
        echo json_encode([
            'status' => 'success',
            'message' => 'Product created successfully',
            'id' => $productId
        ]);
    } else {
        logProductAction("❌ Execute failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create product: ' . $stmt->error]);
    }
    
    $stmt->close();
}

/**
 * GET SINGLE PRODUCT
 * UPDATED: Better error handling
 */
function getProduct() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    logProductAction("🔍 GET PRODUCT ID: $id");
    
    if (!$id) {
        logProductAction("⚠️ No product ID provided");
        echo json_encode(['status' => 'error', 'message' => 'Product ID is required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id, name, category, description, price, stock, image_url, status FROM products WHERE id = ? LIMIT 1");
    
    if (!$stmt) {
        logProductAction("❌ Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logProductAction(" Product not found with ID: $id");
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        $stmt->close();
        return;
    }
    
    $product = $result->fetch_assoc();
    $stmt->close();
    
    logProductAction(" Product found: " . $product['name']);
    echo json_encode(['status' => 'success', 'data' => $product]);
}

/**
 * UPDATE PRODUCT
 * UPDATED: Complete rewrite para mas stable
 */
function updateProduct() {
    global $conn;
    
    logProductAction("✏️ UPDATING PRODUCT");
    
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    
    logProductAction("Update ID=$id, Name=$name, Price=$price");
    
    // Validation
    if ($id <= 0) {
        logProductAction(" Invalid product ID");
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
        return;
    }
    
    if (empty($name) || $price <= 0) {
        logProductAction(" Validation failed");
        echo json_encode(['status' => 'error', 'message' => 'Invalid product data']);
        return;
    }
    
    // Handle image upload
    $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        logProductAction(" New image provided");
        $imageUrl = handleImageUpload($_FILES['image']);
    }
    
    // Update query
    if (!empty($imageUrl)) {
        // WITH image update
        logProductAction("Updating with new image");
        $stmt = $conn->prepare("UPDATE products SET name=?, category=?, description=?, price=?, stock=?, image_url=? WHERE id=?");
        $stmt->bind_param('sssdisi', $name, $category, $description, $price, $stock, $imageUrl, $id);
    } else {
        // WITHOUT image update
        logProductAction("Updating without image change");
        $stmt = $conn->prepare("UPDATE products SET name=?, category=?, description=?, price=?, stock=? WHERE id=?");
        $stmt->bind_param('sssdii', $name, $category, $description, $price, $stock, $id);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            logProductAction(" Product updated successfully");
            echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
        } else {
            logProductAction(" No rows affected - product not found?");
            echo json_encode(['status' => 'error', 'message' => 'Product not found or no changes made']);
        }
    } else {
        logProductAction(" Execute failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update: ' . $stmt->error]);
    }
    
    $stmt->close();
}

/**
 * DELETE PRODUCT (Soft delete - set status to inactive)
 * UPDATED: Better error handling
 */
function deleteProduct() {
    global $conn;
    
    logProductAction(" DELETING PRODUCT");
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    logProductAction("Delete ID: $id");
    
    if ($id <= 0) {
        logProductAction(" Invalid product ID");
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE products SET status='inactive' WHERE id=?");
    
    if (!$stmt) {
        logProductAction(" Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
        return;
    }
    
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            logProductAction(" Product deleted (soft delete) successfully");
            echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
        } else {
            logProductAction(" Product not found");
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        }
    } else {
        logProductAction(" Execute failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete: ' . $stmt->error]);
    }
    
    $stmt->close();
}


function getProductsByCategory() {
    global $conn;
    
    $category = trim($_GET['category'] ?? '');
    
    if (!$category) {
        echo json_encode(['status' => 'error', 'message' => 'Category is required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id, name, category, description, price, stock, image_url FROM products WHERE category=? AND status='active' ORDER BY name ASC");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        if (empty($row['image_url'])) {
            $row['image_url'] = 'assets/default-product.png';
        }
        $products[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'count' => count($products),
        'data' => $products
    ]);
}

/**
 * HANDLE IMAGE UPLOAD
 * UPDATED: Better file validation
 */
function handleImageUpload($file) {
    logProductAction(" Processing image upload");
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        logProductAction(" Upload error code: " . $file['error']);
        return '';
    }
    
    $uploadDir = __DIR__ . '/../uploads/products/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            logProductAction(" Failed to create upload directory");
            return '';
        }
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        logProductAction(" Invalid file type: " . $file['type']);
        return '';
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        logProductAction(" File too large: " . $file['size']);
        return '';
    }
    
    // Generate unique filename
    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', basename($file['name']));
    $uploadPath = $uploadDir . $fileName;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        logProductAction(" File uploaded: $fileName");
        return 'uploads/products/' . $fileName;
    } else {
        logProductAction(" Failed to move uploaded file");
        return '';
    }
}

?>