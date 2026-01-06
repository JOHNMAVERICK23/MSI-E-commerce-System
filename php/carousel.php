<?php
// File: php/carousel.php
// CAROUSEL & SHIPPING METHODS MANAGEMENT

header('Content-Type: application/json');
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    // CAROUSEL ACTIONS
    case 'get_slides':
        getCarouselSlides();
        break;
    case 'create_slide':
        createCarouselSlide();
        break;
    case 'update_slide':
        updateCarouselSlide();
        break;
    case 'delete_slide':
        deleteCarouselSlide();
        break;
    case 'reorder_slides':
        reorderSlides();
        break;
    
    // SHIPPING METHODS ACTIONS
    case 'get_shipping_methods':
        getShippingMethods();
        break;
    case 'create_shipping_method':
        createShippingMethod();
        break;
    case 'update_shipping_method':
        updateShippingMethod();
        break;
    case 'delete_shipping_method':
        deleteShippingMethod();
        break;
    
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

// ==========================================
// CAROUSEL FUNCTIONS
// ==========================================

/**
 * GET ALL CAROUSEL SLIDES
 */
function getCarouselSlides() {
    global $conn;
    
    $result = $conn->query("
        SELECT id, title, description, image_url, link, is_active, display_order
        FROM carousel
        WHERE is_active = 'yes'
        ORDER BY display_order ASC
    ");
    
    $slides = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $slides[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => count($slides),
        'data' => $slides
    ]);
}

/**
 * CREATE NEW CAROUSEL SLIDE
 */
function createCarouselSlide() {
    global $conn;
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $imageUrl = '';
    
    if (empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'Title is required']);
        return;
    }
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageUrl = handleImageUpload($_FILES['image']);
    }
    
    if (empty($imageUrl)) {
        echo json_encode(['status' => 'error', 'message' => 'Image is required']);
        return;
    }
    
    // Get next display order
    $result = $conn->query("SELECT MAX(display_order) as max_order FROM carousel");
    $row = $result->fetch_assoc();
    $displayOrder = intval($row['max_order'] ?? 0) + 1;
    
    $stmt = $conn->prepare("
        INSERT INTO carousel (title, description, image_url, link, display_order)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('ssssi', $title, $description, $imageUrl, $link, $displayOrder);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Carousel slide created successfully',
            'id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create slide']);
    }
    
    $stmt->close();
}

/**
 * UPDATE CAROUSEL SLIDE
 */
function updateCarouselSlide() {
    global $conn;
    
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $link = trim($_POST['link'] ?? '');
    
    if ($id <= 0 || empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        return;
    }
    
    $imageUrl = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageUrl = handleImageUpload($_FILES['image']);
    }
    
    if (!empty($imageUrl)) {
        $stmt = $conn->prepare("
            UPDATE carousel
            SET title=?, description=?, link=?, image_url=?
            WHERE id=?
        ");
        $stmt->bind_param('ssssi', $title, $description, $link, $imageUrl, $id);
    } else {
        $stmt = $conn->prepare("
            UPDATE carousel
            SET title=?, description=?, link=?
            WHERE id=?
        ");
        $stmt->bind_param('sssi', $title, $description, $link, $id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Slide updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update slide']);
    }
    
    $stmt->close();
}

/**
 * DELETE CAROUSEL SLIDE
 */
function deleteCarouselSlide() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid slide ID']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM carousel WHERE id=?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Slide deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete slide']);
    }
    
    $stmt->close();
}

/**
 * REORDER CAROUSEL SLIDES
 */
function reorderSlides() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $slides = $input['slides'] ?? [];
    
    if (empty($slides)) {
        echo json_encode(['status' => 'error', 'message' => 'No slides provided']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        foreach ($slides as $order => $slideId) {
            $displayOrder = $order + 1;
            $stmt = $conn->prepare("UPDATE carousel SET display_order=? WHERE id=?");
            $stmt->bind_param('ii', $displayOrder, $slideId);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Slides reordered successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Failed to reorder slides']);
    }
}

// ==========================================
// SHIPPING METHODS FUNCTIONS
// ==========================================

/**
 * GET ALL SHIPPING METHODS
 */
function getShippingMethods() {
    global $conn;
    
    $result = $conn->query("
        SELECT id, courier_name, display_name, description, base_price, estimated_days, icon_class, is_active
        FROM shipping_methods
        WHERE is_active = 'yes'
        ORDER BY base_price ASC
    ");
    
    $methods = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => count($methods),
        'data' => $methods
    ]);
}

/**
 * CREATE SHIPPING METHOD
 */
function createShippingMethod() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $courierName = trim($input['courier_name'] ?? '');
    $displayName = trim($input['display_name'] ?? '');
    $description = trim($input['description'] ?? '');
    $basePrice = floatval($input['base_price'] ?? 0);
    $estimatedDays = intval($input['estimated_days'] ?? 0);
    $iconClass = trim($input['icon_class'] ?? '');
    
    if (empty($courierName) || empty($displayName)) {
        echo json_encode(['status' => 'error', 'message' => 'Courier name and display name are required']);
        return;
    }
    
    if ($basePrice < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Price cannot be negative']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO shipping_methods 
        (courier_name, display_name, description, base_price, estimated_days, icon_class)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('sssidi', $courierName, $displayName, $description, $basePrice, $estimatedDays, $iconClass);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Shipping method created successfully',
            'id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create shipping method']);
    }
    
    $stmt->close();
}

/**
 * UPDATE SHIPPING METHOD
 */
function updateShippingMethod() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = intval($input['id'] ?? 0);
    $courierName = trim($input['courier_name'] ?? '');
    $displayName = trim($input['display_name'] ?? '');
    $description = trim($input['description'] ?? '');
    $basePrice = floatval($input['base_price'] ?? 0);
    $estimatedDays = intval($input['estimated_days'] ?? 0);
    $iconClass = trim($input['icon_class'] ?? '');
    
    if ($id <= 0 || empty($courierName)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE shipping_methods
        SET courier_name=?, display_name=?, description=?, base_price=?, estimated_days=?, icon_class=?
        WHERE id=?
    ");
    
    $stmt->bind_param('sssidii', $courierName, $displayName, $description, $basePrice, $estimatedDays, $iconClass, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Shipping method updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update shipping method']);
    }
    
    $stmt->close();
}

/**
 * DELETE SHIPPING METHOD
 */
function deleteShippingMethod() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid shipping method ID']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE shipping_methods SET is_active='no' WHERE id=?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Shipping method deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete shipping method']);
    }
    
    $stmt->close();
}

/**
 * HANDLE IMAGE UPLOAD (CAROUSEL)
 */
function handleImageUpload($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    
    $uploadDir = __DIR__ . '/../uploads/carousel/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return '';
    }
    
    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return '';
    }
    
    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', basename($file['name']));
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return 'uploads/carousel/' . $fileName;
    }
    
    return '';
}

?>