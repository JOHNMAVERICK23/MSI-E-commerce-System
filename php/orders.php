<?php
// File: php/orders.php
// COMPLETE ORDER MANAGEMENT

header('Content-Type: application/json');
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

switch ($action) {
    case 'list':
        listOrders();
        break;
    case 'get_staff_activity':
        getStaffActivity();
        break;
    case 'create_order':
        createOrder();
        break;
    case 'update_status':
        updateOrderStatus();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * LIST ORDERS
 */
function listOrders() {
    global $conn;
    
    $result = $conn->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 50");
    
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $orders]);
}

/**
 * CREATE ORDER
 */
function createOrder() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log('Create order input: ' . json_encode($input));
    
    // Generate order number
    $orderNumber = 'ORD' . date('YmdHis') . rand(100, 999);
    $customerId = 1; // Default customer ID for demo
    $total = isset($input['totals']['total']) ? floatval($input['totals']['total']) : 0;
    
    if ($total <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid order total']);
        return;
    }
    
    $items = isset($input['items']) ? $input['items'] : [];
    
    if (empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'No items in order']);
        return;
    }
    
    // Insert order
    $query = "INSERT INTO orders (order_number, customer_id, total_amount, status) 
              VALUES ('$orderNumber', $customerId, $total, 'pending')";
    
    if (!$conn->query($query)) {
        echo json_encode(['status' => 'error', 'message' => 'Error creating order: ' . $conn->error]);
        return;
    }
    
    $orderId = $conn->insert_id;
    
    // Insert order items
    foreach ($items as $item) {
        $productId = intval($item['id']);
        $qty = intval($item['quantity']);
        $price = floatval($item['price']);
        
        $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, unit_price) 
                     VALUES ($orderId, $productId, $qty, $price)";
        
        if (!$conn->query($itemQuery)) {
            error_log('Error inserting order item: ' . $conn->error);
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Order created successfully',
        'orderId' => $orderId,
        'orderNumber' => $orderNumber
    ]);
}

/**
 * UPDATE ORDER STATUS
 */
function updateOrderStatus() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = intval($input['order_id'] ?? 0);
    $status = $conn->real_escape_string($input['status'] ?? '');
    
    if ($orderId <= 0 || !$status) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        return;
    }
    
    $query = "UPDATE orders SET status = '$status', updated_at = NOW() WHERE id = $orderId";
    
    if ($conn->query($query)) {
        echo json_encode(['status' => 'success', 'message' => 'Order status updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $conn->error]);
    }
}

/**
 * GET STAFF ACTIVITY
 */
function getStaffActivity() {
    $activities = [
        [
            'type' => 'updated_order',
            'title' => 'Updated Order #ORD123456',
            'description' => 'Changed status from pending to processing',
            'timestamp' => date('Y-m-d H:i:s')
        ],
        [
            'type' => 'viewed_order',
            'title' => 'Viewed Order #ORD123455',
            'description' => 'Customer order details reviewed',
            'timestamp' => date('Y-m-d H:i:s', time() - 1800)
        ]
    ];
    
    echo json_encode(['status' => 'success', 'data' => $activities]);
}

?>