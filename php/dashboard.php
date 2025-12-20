<?php
// File: php/dashboard.php
// ADMIN DASHBOARD STATISTICS & DATA

header('Content-Type: application/json');
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_stats':
        getDashboardStats();
        break;
    case 'get_sales_data':
        getSalesData();
        break;
    case 'get_recent_orders':
        getRecentOrders();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * GET DASHBOARD STATISTICS
 */
function getDashboardStats() {
    global $conn;
    
    $stats = [
        'totalProducts' => getTotalProducts(),
        'totalOrders' => getTotalOrders(),
        'totalRevenue' => getTotalRevenue(),
        'activeStaff' => getActiveStaff(),
        'ordersPending' => getOrdersByStatus('pending'),
        'ordersProcessing' => getOrdersByStatus('processing'),
        'ordersCompleted' => getOrdersByStatus('completed'),
        'ordersCancelled' => getOrdersByStatus('cancelled'),
        'recentActivity' => getRecentActivity(),
        'topProducts' => getTopProducts(),
        'monthlyRevenue' => getMonthlyRevenue()
    ];
    
    echo json_encode(['status' => 'success', 'data' => $stats]);
}

/**
 * GET TOTAL ACTIVE PRODUCTS
 */
function getTotalProducts() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
    $row = $result->fetch_assoc();
    
    return $row['count'] ?? 0;
}

/**
 * GET TOTAL ORDERS COUNT
 */
function getTotalOrders() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    $row = $result->fetch_assoc();
    
    return $row['count'] ?? 0;
}

/**
 * GET TOTAL REVENUE FROM COMPLETED ORDERS
 */
function getTotalRevenue() {
    global $conn;
    
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status = 'completed'");
    $row = $result->fetch_assoc();
    
    return floatval($row['total']) ?? 0;
}

/**
 * GET COUNT OF ACTIVE STAFF MEMBERS
 */
function getActiveStaff() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff'");
    $row = $result->fetch_assoc();
    
    return $row['count'] ?? 0;
}

/**
 * GET ORDERS COUNT BY STATUS
 */
function getOrdersByStatus($status) {
    global $conn;
    
    $status = $conn->real_escape_string($status);
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = '$status'");
    $row = $result->fetch_assoc();
    
    return $row['count'] ?? 0;
}

/**
 * GET RECENT ACTIVITY LOG
 */
function getRecentActivity() {
    // Mock activity data - In production, create activity_log table
    $activities = [
        [
            'type' => 'add',
            'text' => 'New product added: MSI RTX 4090',
            'timestamp' => date('Y-m-d H:i:s')
        ],
        [
            'type' => 'order',
            'text' => 'New order received: Order #' . rand(1000, 9999),
            'timestamp' => date('Y-m-d H:i:s', time() - 3600)
        ],
        [
            'type' => 'edit',
            'text' => 'Product updated: MSI B650 Motherboard',
            'timestamp' => date('Y-m-d H:i:s', time() - 7200)
        ],
        [
            'type' => 'staff',
            'text' => 'New staff account created: John Doe',
            'timestamp' => date('Y-m-d H:i:s', time() - 10800)
        ],
        [
            'type' => 'delete',
            'text' => 'Product deleted: Old Stock Item',
            'timestamp' => date('Y-m-d H:i:s', time() - 14400)
        ]
    ];
    
    return array_slice($activities, 0, 10);
}

/**
 * GET TOP SELLING PRODUCTS
 */
function getTopProducts() {
    global $conn;
    
    $query = "SELECT p.id, p.name, p.price, COUNT(oi.id) as sales_count, SUM(oi.quantity) as total_quantity
              FROM products p
              LEFT JOIN order_items oi ON p.id = oi.product_id
              WHERE p.status = 'active'
              GROUP BY p.id
              ORDER BY total_quantity DESC
              LIMIT 5";
    
    $result = $conn->query($query);
    
    $products = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    return $products;
}

/**
 * GET MONTHLY REVENUE DATA
 */
function getMonthlyRevenue() {
    global $conn;
    
    $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
                     SUM(total_amount) as revenue, 
                     COUNT(*) as orders
              FROM orders
              WHERE status = 'completed'
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month DESC
              LIMIT 12";
    
    $result = $conn->query($query);
    
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    return array_reverse($data);
}

/**
 * GET SALES DATA FOR CHART
 */
function getSalesData() {
    global $conn;
    
    $query = "SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue
              FROM orders
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND status IN ('completed', 'processing')
              GROUP BY DATE(created_at)
              ORDER BY date ASC";
    
    $result = $conn->query($query);
    
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'date' => $row['date'],
                'orders' => intval($row['orders']),
                'revenue' => floatval($row['revenue'])
            ];
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $data]);
}

/**
 * GET RECENT ORDERS
 */
function getRecentOrders() {
    global $conn;
    
    $query = "SELECT o.id, o.order_number, o.customer_id, o.total_amount, o.status, o.created_at,
                     COUNT(oi.id) as item_count
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              GROUP BY o.id
              ORDER BY o.created_at DESC
              LIMIT 20";
    
    $result = $conn->query($query);
    
    $orders = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $orders]);
}

/**
 * GET CUSTOMER STATISTICS
 */
function getCustomerStats() {
    global $conn;
    
    $total_customers = 0;
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_customers = $row['count'] ?? 0;
    }
    
    $returning_customers = 0;
    $result = $conn->query("SELECT COUNT(DISTINCT customer_id) as count FROM orders WHERE customer_id > 1");
    if ($result) {
        $row = $result->fetch_assoc();
        $returning_customers = $row['count'] ?? 0;
    }
    
    return [
        'total' => $total_customers,
        'returning' => $returning_customers
    ];
}

/**
 * GET ORDER STATUS DISTRIBUTION
 */
function getOrderDistribution() {
    global $conn;
    
    $query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
    $result = $conn->query($query);
    
    $distribution = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $distribution[$row['status']] = intval($row['count']);
        }
    }
    
    return $distribution;
}

?>