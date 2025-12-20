<?php
// File: php/products.php
// PRODUCT MANAGEMENT - CRUD OPERATIONS

header('Content-Type: application/json');
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle JSON POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

// Route actions
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
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * LIST ALL ACTIVE PRODUCTS
 */
function listProducts() {
    global $conn;
    
    $query = "SELECT id, name, category, description, price, stock, image_url, status, created_at 
              FROM products 
              WHERE status = 'active' 
              ORDER BY created_at DESC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
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

/**
 * GET SINGLE PRODUCT BY ID
 */
function getProduct() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
        return;
    }
    
    $query = "SELECT * FROM products WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        return;
    }
    
    $product = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'data' => $product]);
}

/**
 * CREATE NEW PRODUCT WITH IMAGE UPLOAD
 */
function createProduct() {
    global $conn;
    
    // Ang code na ito ay para sa JSON request na galing sa admin.js mo
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation: Siguraduhin na ang mga kailangan na fields ay may laman
    if (!isset($input['name']) || !isset($input['price']) || !isset($input['stock'])) {
        echo json_encode(['status' => 'error', 'message' => 'Name, price, at stock ay kailangan.']);
        return;
    }
    
    // Linisin ang input para iwas SQL injection
    $name = $conn->real_escape_string($input['name']);
    $category = $conn->real_escape_string($input['category'] ?? 'Uncategorized');
    $description = $conn->real_escape_string($input['description'] ?? '');
    $price = floatval($input['price']);
    $stock = intval($input['stock']);
    
    // Karagdagang validation
    if (empty($name) || $price <= 0 || $stock < 0) {
        echo json_encode(['status' => 'error', 'message' => 'May maling data. Siguraduhin na ang price ay higit sa 0 at ang stock ay hindi negatibo.']);
        return;
    }
    
    // Ito na ang bagong INSERT query, wala nang 'created_by'
    $query = "INSERT INTO products (name, category, description, price, stock, status) 
              VALUES (?, ?, ?, ?, ?, 'active')";
    
    $stmt = $conn->prepare($query);
    // 'sssdi' - s: string, d: double, i: integer
    $stmt->bind_param('sssdi', $name, $category, $description, $price, $stock);
    
    if ($stmt->execute()) {
        $productId = $conn->insert_id;
        echo json_encode(['status' => 'success', 'message' => 'Produkto ay matagumpay na naidagdag!', 'product_id' => $productId]);
    } else {
        // Ipakita ang totoong error mula sa database para malaman natin kung may iba pang problema
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
    }
    $stmt->close();
}
/**
 * UPDATE EXISTING PRODUCT
 */
function updateProduct() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
        return;
    }
    
    $id = intval($input['id']);
    $name = $conn->real_escape_string($input['name'] ?? '');
    $category = $conn->real_escape_string($input['category'] ?? '');
    $description = $conn->real_escape_string($input['description'] ?? '');
    $price = floatval($input['price'] ?? 0);
    $stock = intval($input['stock'] ?? 0);
    $image_url = $conn->real_escape_string($input['image_url'] ?? '');
    
    // Validate
    if ($price <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Price must be greater than 0']);
        return;
    }
    
    if ($stock < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Stock cannot be negative']);
        return;
    }
    
    $query = "UPDATE products 
              SET name = '$name', 
                  category = '$category', 
                  description = '$description', 
                  price = $price, 
                  stock = $stock, 
                  image_url = '$image_url',
                  updated_at = NOW() 
              WHERE id = $id";
    
    if ($conn->query($query)) {
        if ($conn->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product not found or no changes made']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error updating product: ' . $conn->error]);
    }
}

/**
 * DELETE PRODUCT (SOFT DELETE - SET TO INACTIVE)
 */
function deleteProduct() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
        return;
    }
    
    $id = intval($input['id']);
    
    // Soft delete - set status to inactive
    $query = "UPDATE products SET status = 'inactive', updated_at = NOW() WHERE id = $id";
    
    if ($conn->query($query)) {
        if ($conn->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Product not found']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error deleting product: ' . $conn->error]);
    }
}

/**
 * GET PRODUCTS BY CATEGORY
 */
function getProductsByCategory() {
    global $conn;
    $input = json_decode(file_get_contents('php://input'), true);
    $category = $conn->real_escape_string($input['category'] ?? '');
    
    if (!$category) {
        echo json_encode(['status' => 'error', 'message' => 'Category required']);
        return;
    }
    
    $query = "SELECT * FROM products WHERE category = '$category' AND status = 'active' ORDER BY name ASC";
    $result = $conn->query($query);
    
    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
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