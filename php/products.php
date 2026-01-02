<?php

header('Content-Type: application/json');
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle multipart form data for image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $action = $_POST['action'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'list':
        listProducts();
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
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
/**
 * LIST ALL ACTIVE PRODUCTS
 */
function listProducts() {
    global $conn;
    
    $query = "SELECT * FROM products WHERE status = 'active' ORDER BY created_at DESC";
    $result = $conn->query($query);
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Set default image if none
        if (empty($row['image_url'])) {
            $row['image_url'] = 'assets/default-product.png';
        }
        $products[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'data' => $products]);
}


function handleImageUpload($file) {
    $uploadDir = '../uploads/products/';
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\-]/', '_', $file['name']);
    $uploadPath = $uploadDir . $fileName;
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return '';
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return '';
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return 'uploads/products/' . $fileName;
    }
    
    return '';
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
    
    // Handle image upload
    $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageUrl = handleImageUpload($_FILES['image']);
    }
    
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    
    if (empty($name) || $price <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Product name and price are required']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO products (name, category, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssdis', $name, $category, $description, $price, $stock, $imageUrl);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Product created successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create product']);
    }
    
    $stmt->close();
}
/**
 * UPDATE EXISTING PRODUCT
 */
function updateProduct() {
    global $conn;
    
    $id = intval($_POST['id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    
    if ($id <= 0 || empty($name) || $price <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product data']);
        return;
    }
    
    // Handle image upload if new image provided
    $imageUrl = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageUrl = handleImageUpload($_FILES['image']);
    }
    
    if ($imageUrl) {
        $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ?, image_url = ? WHERE id = ?");
        $stmt->bind_param('sssdisi', $name, $category, $description, $price, $stock, $imageUrl, $id);
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ? WHERE id = ?");
        $stmt->bind_param('sssdii', $name, $category, $description, $price, $stock, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update product']);
    }
    
    $stmt->close();
}

/**
 * DELETE PRODUCT (SOFT DELETE - SET TO INACTIVE)
 */
function deleteProduct() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete product']);
    }
    
    $stmt->close();
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