<?php
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
    case 'list_pending':
        listPendingOrders();
        break;
    case 'customer_orders':
        getCustomerOrders();
        break;
    case 'get_receipt':
        getReceipt();
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
    case 'approve_order':
        approveOrder();
        break;
    case 'reject_order':
        rejectOrder();
        break;
    case 'get_order_details':
        getOrderDetails();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * LIST ALL ORDERS (ADMIN)
 */
function listOrders() {
    global $conn;
    
    $result = $conn->query("
        SELECT 
            o.id, o.order_number, o.customer_id, o.total_amount, o.status, 
            o.approval_status, o.created_at,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC 
        LIMIT 100
    ");
    
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $orders]);
}

/**
 * LIST PENDING ORDERS (FOR STAFF)
 * Ipinapakita lang ang orders na pending approval
 */
function listPendingOrders() {
    global $conn;
    
    $result = $conn->query("
        SELECT 
            o.id, 
            o.order_number, 
            o.customer_id, 
            o.total_amount, 
            o.status, 
            o.approval_status,
            o.payment_method,
            o.created_at,
            COUNT(oi.id) as item_count,
            c.first_name,
            c.last_name,
            u.email
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE o.approval_status = 'pending' OR o.approval_status IS NULL
        GROUP BY o.id
        ORDER BY o.created_at ASC
    ");
    
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => count($orders),
        'data' => $orders
    ]);
}

/**
 * GET ORDER DETAILS WITH ITEMS (FOR STAFF REVIEW)
 */
function getOrderDetails() {
    global $conn;
    
    $orderId = intval($_GET['orderId'] ?? 0);
    
    if ($orderId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Order ID required']);
        return;
    }
    
    // Get order with customer info
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            o.total_amount,
            o.status,
            o.approval_status,
            o.payment_method,
            o.payment_status,
            o.created_at,
            c.first_name,
            c.last_name,
            u.email,
            c.phone
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE o.id = ?
    ");
    
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        $stmt->close();
        return;
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Get order items
    $stmt = $conn->prepare("
        SELECT 
            oi.product_id,
            p.name as product_name,
            p.category,
            oi.quantity,
            oi.unit_price
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = $item;
    }
    $stmt->close();
    
    $order['items'] = $items;
    $order['customer_name'] = $order['first_name'] . ' ' . $order['last_name'];
    
    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    
    $order['subtotal'] = $subtotal;
    $order['shipping'] = 10.00;
    $order['tax'] = $subtotal * 0.10;
    
    echo json_encode(['status' => 'success', 'data' => $order]);
}

/**
 * APPROVE ORDER (STAFF APPROVES)
 * Status: pending -> processing
 */
function approveOrder() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = intval($input['order_id'] ?? 0);
    $notes = $conn->real_escape_string($input['notes'] ?? '');
    
    if ($orderId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update order status to processing
        $stmt = $conn->prepare("
            UPDATE orders 
            SET approval_status = 'approved', 
                status = 'processing',
                approved_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
        
        // Log the approval
        $stmt = $conn->prepare("
            INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, change_reason)
            VALUES (?, 'pending', 'processing', ?, 'Staff Approved')
        ");
        
        $staffId = 1; // Dapat from session, pero for now use 1
        $stmt->bind_param('ii', $orderId, $staffId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Order approved and moved to processing',
            'new_status' => 'processing'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Failed to approve order: ' . $e->getMessage()]);
    }
}

/**
 * REJECT ORDER (STAFF REJECTS)
 * Status: pending -> cancelled
 */
function rejectOrder() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = intval($input['order_id'] ?? 0);
    $reason = $conn->real_escape_string($input['reason'] ?? 'No reason provided');
    
    if ($orderId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update order status to cancelled
        $stmt = $conn->prepare("
            UPDATE orders 
            SET approval_status = 'rejected', 
                status = 'cancelled',
                approved_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
        
        // Log the rejection
        $stmt = $conn->prepare("
            INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, change_reason)
            VALUES (?, 'pending', 'cancelled', ?, ?)
        ");
        
        $staffId = 1; // From session ideally
        $stmt->bind_param('iis', $orderId, $staffId, $reason);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Order rejected and cancelled',
            'new_status' => 'cancelled'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Failed to reject order: ' . $e->getMessage()]);
    }
}

/**
 * GET CUSTOMER ORDERS WITH ITEMS
 */
function getCustomerOrders() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 1;
    
    $query = "
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            o.total_amount,
            o.status,
            o.created_at,
            o.payment_method,
            o.payment_status,
            COUNT(oi.id) as item_count,
            GROUP_CONCAT(
                JSON_OBJECT(
                    'product_id', oi.product_id,
                    'product_name', p.name,
                    'quantity', oi.quantity,
                    'unit_price', oi.unit_price
                )
            ) as items_json
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.customer_id = $customerId
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ";
    
    $result = $conn->query($query);
    
    $orders = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Parse items JSON
            if ($row['items_json']) {
                $items_array = json_decode('[' . $row['items_json'] . ']', true);
                $row['items'] = $items_array;
            } else {
                $row['items'] = [];
            }
            unset($row['items_json']);
            
            $orders[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $orders]);
}

/**
 * GET ORDER RECEIPT WITH FULL DETAILS
 */
function getReceipt() {
    global $conn;
    
    $orderId = intval($_GET['orderId'] ?? 0);
    
    if ($orderId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Order ID required']);
        return;
    }
    
    // Get order details
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.customer_id,
            o.total_amount,
            o.status,
            o.created_at,
            o.payment_method,
            o.payment_status,
            c.first_name,
            c.last_name,
            u.email as customer_email,
            c.phone as customer_phone
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN users u ON c.user_id = u.id
        WHERE o.id = ?
    ");
    
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        $stmt->close();
        return;
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Get order items
    $stmt = $conn->prepare("
        SELECT 
            oi.product_id,
            p.name as product_name,
            oi.quantity,
            oi.unit_price
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = $item;
    }
    $stmt->close();
    
    $order['items'] = $items;
    
    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    
    $order['subtotal'] = $subtotal;
    $order['shipping'] = 10.00;
    $order['tax'] = $subtotal * 0.10;
    $order['customer_name'] = $order['first_name'] . ' ' . $order['last_name'];
    
    // Get shipping address from order (if stored)
    $order['shipping_address'] = '123 Gaming Street';
    $order['shipping_city'] = 'San Francisco';
    $order['shipping_postal'] = '94102';
    
    // Default payment status
    if (!isset($order['payment_status'])) {
        $order['payment_status'] = 'pending';
    }
    
    echo json_encode(['status' => 'success', 'data' => $order]);
}

/**
 * CREATE ORDER
 */
function createOrder() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Generate order number
    $orderNumber = 'ORD' . date('YmdHis') . rand(100, 999);
    $customerId = 1; // Default customer ID
    $total = isset($input['totals']['total']) ? floatval($input['totals']['total']) : 0;
    $paymentMethod = $input['payment']['method'] ?? 'not specified';
    
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
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO orders (order_number, customer_id, total_amount, status, payment_method, payment_status) 
            VALUES (?, ?, ?, 'pending', ?, 'pending')
        ");
        
        $stmt->bind_param('sids', $orderNumber, $customerId, $total, $paymentMethod);
        $stmt->execute();
        
        $orderId = $conn->insert_id;
        $stmt->close();
        
        // Insert order items
        foreach ($items as $item) {
            $productId = intval($item['id']);
            $qty = intval($item['quantity']);
            $price = floatval($item['price']);
            
            $itemStmt = $conn->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, unit_price) 
                VALUES (?, ?, ?, ?)
            ");
            
            $itemStmt->bind_param('iiii', $orderId, $productId, $qty, $price);
            $itemStmt->execute();
            $itemStmt->close();
        }
        
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Order created successfully',
            'orderId' => $orderId,
            'orderNumber' => $orderNumber
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Order creation failed: ' . $e->getMessage()]);
    }
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
    
    $query = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $status, $orderId);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Order status updated']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $conn->error]);
    }
    $stmt->close();
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