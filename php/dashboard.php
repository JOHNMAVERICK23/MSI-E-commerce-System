<?php

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
 * UPDATED: Now includes real chart data
 */
function getDashboardStats() {
    global $conn;
    
    error_log('=== GETTING DASHBOARD STATS ===');
    
    // Get all the stats
    $totalProducts = getTotalProducts();
    $totalOrders = getTotalOrders();
    $totalRevenue = getTotalRevenue();
    $activeStaff = getActiveStaff();
    
    // Get chart data
    $salesData = getSalesChartData();
    $orderStatusData = getOrderStatusData();
    
    $stats = [
        'totalProducts' => intval($totalProducts),
        'totalOrders' => intval($totalOrders),
        'totalRevenue' => floatval($totalRevenue),
        'activeStaff' => intval($activeStaff),
        'salesData' => $salesData,
        'orderStatusData' => $orderStatusData,
        'recentActivity' => getRecentActivity(),
        'topProducts' => getTopProducts(),
        'monthlyRevenue' => getMonthlyRevenue()
    ];
    
    error_log('Final Stats: ' . json_encode($stats));
    
    echo json_encode(['status' => 'success', 'data' => $stats]);
}

/**
 * GET TOTAL ACTIVE PRODUCTS
 */
function getTotalProducts() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
    $row = $result->fetch_assoc();
    
    return intval($row['count'] ?? 0);
}

/**
 * GET TOTAL ORDERS COUNT
 */
function getTotalOrders() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    $row = $result->fetch_assoc();
    
    return intval($row['count'] ?? 0);
}

/**
 * GET TOTAL REVENUE FROM COMPLETED ORDERS
 */
function getTotalRevenue() {
    global $conn;
    
    $result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status IN ('completed', 'processing', 'pending')");
    
    if ($result) {
        $row = $result->fetch_assoc();
        return floatval($row['total']) ?? 0;
    }
    
    return 0;
}

/**
 * GET COUNT OF ACTIVE STAFF MEMBERS
 */
function getActiveStaff() {
    global $conn;
    
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff' AND status = 'active'");
    $row = $result->fetch_assoc();
    
    return intval($row['count'] ?? 0);
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
 * GET SALES CHART DATA - REAL DATA FROM LAST 7 DAYS
 * UPDATED: Returns actual sales data per day
 */
function getSalesChartData() {
    global $conn;
    
    // Get last 7 days of sales data
    $query = "SELECT 
                DATE_FORMAT(created_at, '%a') as day_name,
                DATE(created_at) as date,
                COALESCE(SUM(total_amount), 0) as revenue
              FROM orders
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND status IN ('completed', 'processing', 'pending')
              GROUP BY DATE(created_at)
              ORDER BY DATE(created_at) ASC";
    
    $result = $conn->query($query);
    
    error_log('Sales Chart Query Result Rows: ' . ($result ? $result->num_rows : 'NULL'));
    
    $labels = [];
    $revenues = [];
    
    // Create an array for all 7 days with defaults
    $daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $defaultData = array_fill_keys($daysOfWeek, 0);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            error_log('Row: ' . json_encode($row));
            $dayName = $row['day_name'];
            $revenue = floatval($row['revenue']);
            
            // Store in associative array
            $defaultData[$dayName] = $revenue;
        }
    } else {
        error_log('No sales data found for last 7 days');
    }
    
    // Build final arrays
    foreach ($daysOfWeek as $day) {
        $labels[] = $day;
        $revenues[] = floatval($defaultData[$day]);
    }
    
    error_log('Sales Chart Final Data: ' . json_encode([
        'labels' => $labels,
        'revenues' => $revenues
    ]));
    
    return [
        'labels' => $labels,
        'revenues' => $revenues
    ];
}


/**
 * GET ORDER STATUS DISTRIBUTION DATA
 * UPDATED: Real data from database
 */
function getOrderStatusData() {
    global $conn;
    
    $query = "SELECT 
                status,
                COUNT(*) as count
              FROM orders
              GROUP BY status";
    
    $result = $conn->query($query);
    
    error_log('Order Status Query Result: ' . ($result ? $result->num_rows : 'NULL') . ' rows');
    
    $statusData = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            error_log('Status Row: ' . json_encode($row));
            
            $status = strtolower(trim($row['status']));
            $count = intval($row['count']);
            
            if (array_key_exists($status, $statusData)) {
                $statusData[$status] = $count;
            }
        }
    } else {
        error_log('No order status data found');
    }
    
    error_log('Order Status Final Data: ' . json_encode($statusData));
    
    return $statusData;
}

/**
 * GET RECENT ACTIVITY LOG
 */
function getRecentActivity() {
    // Mock data - in production use actual activity log
    return [
        [
            'type' => 'add',
            'text' => 'New product added to catalog',
            'timestamp' => date('Y-m-d H:i:s')
        ],
        [
            'type' => 'order',
            'text' => 'New customer order received',
            'timestamp' => date('Y-m-d H:i:s', time() - 3600)
        ],
        [
            'type' => 'edit',
            'text' => 'Product information updated',
            'timestamp' => date('Y-m-d H:i:s', time() - 7200)
        ]
    ];
}


/**
 * GET TOP SELLING PRODUCTS
 */
function getTopProducts() {
    global $conn;
    
    $query = "SELECT p.id, p.name, p.price, COUNT(oi.id) as sales_count, COALESCE(SUM(oi.quantity), 0) as total_quantity
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
              WHERE status IN ('completed', 'processing', 'pending')
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
    $result = $conn->query("SELECT COUNT(DISTINCT customer_id) as count FROM orders WHERE customer_id > 0");
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