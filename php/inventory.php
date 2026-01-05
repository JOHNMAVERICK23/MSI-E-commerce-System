<?php
// File: php/inventory.php
// INVENTORY & STOCK MANAGEMENT SYSTEM

header('Content-Type: application/json');
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'get_inventory':
        getInventory();
        break;
    case 'update_stock':
        updateStock();
        break;
    case 'stock_in':
        stockIn();
        break;
    case 'stock_out':
        stockOut();
        break;
    case 'get_stock_history':
        getStockHistory();
        break;
    case 'low_stock_alert':
        lowStockAlert();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * GET COMPLETE INVENTORY
 */
function getInventory() {
    global $conn;
    
    $result = $conn->query("SELECT id, name, category, price, stock, status FROM products ORDER BY name ASC");
    
    $inventory = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['status_display'] = $row['stock'] <= 10 ? 'Low Stock' : 'In Stock';
            $row['status_color'] = $row['stock'] <= 10 ? 'warning' : 'success';
            $inventory[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $inventory]);
}

/**
 * UPDATE STOCK DIRECTLY
 */
function updateStock() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = intval($input['product_id'] ?? 0);
    $newStock = intval($input['stock'] ?? 0);
    $reason = $input['reason'] ?? 'Manual adjustment';
    
    if ($productId <= 0 || $newStock < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        return;
    }
    
    // Get old stock
    $result = $conn->query("SELECT stock FROM products WHERE id = $productId");
    $product = $result->fetch_assoc();
    $oldStock = $product['stock'] ?? 0;
    
    // Update stock
    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->bind_param('ii', $newStock, $productId);
    
    if ($stmt->execute()) {
        // Log the change
        logStockChange($productId, $oldStock, $newStock, $reason);
        echo json_encode(['status' => 'success', 'message' => 'Stock updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update stock']);
    }
    
    $stmt->close();
}

/**
 * STOCK IN - RECEIVE NEW STOCK
 */
function stockIn() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = intval($input['product_id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 0);
    $supplier = $input['supplier'] ?? 'Not specified';
    $reference = $input['reference'] ?? '';
    
    if ($productId <= 0 || $quantity <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid quantity']);
        return;
    }
    
    // Get current stock
    $result = $conn->query("SELECT stock FROM products WHERE id = $productId");
    $product = $result->fetch_assoc();
    $currentStock = $product['stock'] ?? 0;
    
    $newStock = $currentStock + $quantity;
    
    // Update stock
    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->bind_param('ii', $newStock, $productId);
    
    if ($stmt->execute()) {
        logStockChange($productId, $currentStock, $newStock, "Stock In - Supplier: $supplier, Ref: $reference");
        
        echo json_encode([
            'status' => 'success',
            'message' => "Added $quantity units to stock",
            'old_stock' => $currentStock,
            'new_stock' => $newStock
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update stock']);
    }
    
    $stmt->close();
}

/**
 * STOCK OUT - REDUCE STOCK
 */
function stockOut() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = intval($input['product_id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 0);
    $reason = $input['reason'] ?? 'Stock reduction';
    
    if ($productId <= 0 || $quantity <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid quantity']);
        return;
    }
    
    // Get current stock
    $result = $conn->query("SELECT stock FROM products WHERE id = $productId");
    $product = $result->fetch_assoc();
    $currentStock = $product['stock'] ?? 0;
    
    if ($currentStock < $quantity) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient stock']);
        return;
    }
    
    $newStock = $currentStock - $quantity;
    
    // Update stock
    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $stmt->bind_param('ii', $newStock, $productId);
    
    if ($stmt->execute()) {
        logStockChange($productId, $currentStock, $newStock, "Stock Out - Reason: $reason");
        
        echo json_encode([
            'status' => 'success',
            'message' => "Removed $quantity units from stock",
            'old_stock' => $currentStock,
            'new_stock' => $newStock
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update stock']);
    }
    
    $stmt->close();
}

/**
 * GET STOCK CHANGE HISTORY
 */
function getStockHistory() {
    global $conn;
    
    $productId = intval($_GET['product_id'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
        return;
    }
    
    $result = $conn->query("
        SELECT * FROM stock_movements 
        WHERE product_id = $productId 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    
    $history = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $history]);
}

/**
 * GET LOW STOCK ALERT
 */
function lowStockAlert() {
    global $conn;
    
    $threshold = intval($_GET['threshold'] ?? 10);
    
    $result = $conn->query("
        SELECT id, name, stock, category 
        FROM products 
        WHERE stock <= $threshold AND status = 'active'
        ORDER BY stock ASC
    ");
    
    $lowStock = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $lowStock[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => count($lowStock),
        'data' => $lowStock
    ]);
}

/**
 * LOG STOCK CHANGES
 */
function logStockChange($productId, $oldStock, $newStock, $reason) {
    global $conn;
    
    $changeType = $newStock > $oldStock ? 'INCREASE' : 'DECREASE';
    $difference = abs($newStock - $oldStock);
    
    $stmt = $conn->prepare("
        INSERT INTO stock_movements (product_id, old_stock, new_stock, change_type, difference, reason)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt) {
        $stmt->bind_param('iiiis', $productId, $oldStock, $newStock, $changeType, $difference, $reason);
        $stmt->execute();
        $stmt->close();
    }
}

?>