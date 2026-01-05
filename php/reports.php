<?php
// File: php/reports.php
// ADMIN REPORTS & EXPORT FUNCTIONALITY

header('Content-Type: application/json');
require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'sales_report':
        generateSalesReport();
        break;
    case 'order_report':
        generateOrderReport();
        break;
    case 'product_report':
        generateProductReport();
        break;
    case 'customer_report':
        generateCustomerReport();
        break;
    case 'export_csv':
        exportToCSV();
        break;
    case 'export_html':
        exportToHTML();
        break;
    case 'get_period_data':
        getPeriodData();
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

/**
 * GENERATE SALES REPORT
 */
function generateSalesReport() {
    global $conn;
    
    $period = $_GET['period'] ?? 'month'; // week, month, year
    $dateFilter = getDateFilter($period);
    
    $query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as order_count,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            MAX(total_amount) as max_order,
            MIN(total_amount) as min_order
        FROM orders
        WHERE created_at >= '$dateFilter'
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ";
    
    $result = $conn->query($query);
    $data = [];
    $totalRevenue = 0;
    $totalOrders = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
            $totalRevenue += floatval($row['total_revenue']);
            $totalOrders += intval($row['order_count']);
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'period' => $period,
        'summary' => [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'avg_order_value' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0
        ],
        'data' => $data
    ]);
}

/**
 * GENERATE ORDER REPORT
 */
function generateOrderReport() {
    global $conn;
    
    $status = $_GET['status'] ?? '';
    $period = $_GET['period'] ?? 'month';
    $dateFilter = getDateFilter($period);
    
    $query = "
        SELECT 
            status,
            COUNT(*) as order_count,
            SUM(total_amount) as total_amount,
            AVG(total_amount) as avg_amount,
            DATE(created_at) as date
        FROM orders
        WHERE created_at >= '$dateFilter'
    ";
    
    if (!empty($status)) {
        $status = $conn->real_escape_string($status);
        $query .= " AND status = '$status'";
    }
    
    $query .= " GROUP BY status, DATE(created_at) ORDER BY date DESC";
    
    $result = $conn->query($query);
    $data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    // Status breakdown
    $statusQuery = "
        SELECT status, COUNT(*) as count
        FROM orders
        WHERE created_at >= '$dateFilter'
        GROUP BY status
    ";
    
    $statusResult = $conn->query($statusQuery);
    $statusBreakdown = [];
    
    if ($statusResult) {
        while ($row = $statusResult->fetch_assoc()) {
            $statusBreakdown[$row['status']] = intval($row['count']);
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'period' => $period,
        'status_breakdown' => $statusBreakdown,
        'data' => $data
    ]);
}

/**
 * GENERATE PRODUCT REPORT
 */
function generateProductReport() {
    global $conn;
    
    $query = "
        SELECT 
            p.id,
            p.name,
            p.category,
            p.price,
            p.stock,
            COUNT(oi.id) as sales_count,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.unit_price) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status IN ('completed', 'processing')
        GROUP BY p.id
        ORDER BY COALESCE(revenue, 0) DESC
    ";
    
    $result = $conn->query($query);
    $data = [];
    $totalProductRevenue = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['revenue'] = floatval($row['revenue'] ?? 0);
            $data[] = $row;
            $totalProductRevenue += $row['revenue'];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'total_products' => count($data),
            'total_revenue' => $totalProductRevenue
        ],
        'data' => $data
    ]);
}

/**
 * GENERATE CUSTOMER REPORT
 */
function generateCustomerReport() {
    global $conn;
    
    $query = "
        SELECT 
            c.id,
            c.first_name,
            c.last_name,
            c.phone,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.created_at) as last_order_date
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        GROUP BY c.id
        ORDER BY total_spent DESC
    ";
    
    $result = $conn->query($query);
    $data = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'total_customers' => count($data),
        'data' => $data
    ]);
}

/**
 * EXPORT TO CSV
 */
function exportToCSV() {
    global $conn;
    
    $reportType = $_GET['type'] ?? 'sales'; // sales, orders, products, customers
    $period = $_GET['period'] ?? 'month';
    
    // Generate filename
    $filename = 'msi_' . $reportType . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    if ($reportType === 'sales') {
        exportSalesCSV($output, $period);
    } elseif ($reportType === 'orders') {
        exportOrdersCSV($output, $period);
    } elseif ($reportType === 'products') {
        exportProductsCSV($output);
    } elseif ($reportType === 'customers') {
        exportCustomersCSV($output);
    }
    
    fclose($output);
    exit;
}

/**
 * EXPORT SALES CSV
 */
function exportSalesCSV($output, $period) {
    global $conn;
    
    fputcsv($output, ['Date', 'Orders', 'Total Revenue', 'Average Order Value', 'Max Order', 'Min Order']);
    
    $dateFilter = getDateFilter($period);
    
    $query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as order_count,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            MAX(total_amount) as max_order,
            MIN(total_amount) as min_order
        FROM orders
        WHERE created_at >= '$dateFilter'
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['date'],
                $row['order_count'],
                number_format($row['total_revenue'], 2),
                number_format($row['avg_order_value'], 2),
                number_format($row['max_order'], 2),
                number_format($row['min_order'], 2)
            ]);
        }
    }
}

/**
 * EXPORT ORDERS CSV
 */
function exportOrdersCSV($output, $period) {
    global $conn;
    
    fputcsv($output, ['Order #', 'Customer ID', 'Amount', 'Status', 'Date']);
    
    $dateFilter = getDateFilter($period);
    
    $query = "
        SELECT order_number, customer_id, total_amount, status, created_at
        FROM orders
        WHERE created_at >= '$dateFilter'
        ORDER BY created_at DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['order_number'],
                $row['customer_id'],
                number_format($row['total_amount'], 2),
                strtoupper($row['status']),
                date('Y-m-d H:i', strtotime($row['created_at']))
            ]);
        }
    }
}

/**
 * EXPORT PRODUCTS CSV
 */
function exportProductsCSV($output) {
    global $conn;
    
    fputcsv($output, ['Product Name', 'Category', 'Price', 'Stock', 'Sales Count', 'Total Sold', 'Revenue']);
    
    $query = "
        SELECT 
            p.name,
            p.category,
            p.price,
            p.stock,
            COUNT(oi.id) as sales_count,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.unit_price) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        GROUP BY p.id
        ORDER BY COALESCE(revenue, 0) DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['name'],
                $row['category'],
                number_format($row['price'], 2),
                $row['stock'],
                $row['sales_count'] ?? 0,
                $row['total_sold'] ?? 0,
                number_format($row['revenue'] ?? 0, 2)
            ]);
        }
    }
}

/**
 * EXPORT CUSTOMERS CSV
 */
function exportCustomersCSV($output) {
    global $conn;
    
    fputcsv($output, ['First Name', 'Last Name', 'Phone', 'Orders', 'Total Spent', 'Last Order']);
    
    $query = "
        SELECT 
            c.first_name,
            c.last_name,
            c.phone,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.created_at) as last_order_date
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        GROUP BY c.id
        ORDER BY total_spent DESC
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['first_name'],
                $row['last_name'],
                $row['phone'],
                $row['order_count'] ?? 0,
                number_format($row['total_spent'] ?? 0, 2),
                $row['last_order_date'] ? date('Y-m-d', strtotime($row['last_order_date'])) : 'N/A'
            ]);
        }
    }
}

/**
 * EXPORT TO HTML
 */
function exportToHTML() {
    global $conn;
    
    $reportType = $_GET['type'] ?? 'sales';
    $period = $_GET['period'] ?? 'month';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.html"');
    
    echo '<!DOCTYPE html><html><head>';
    echo '<meta charset="UTF-8">';
    echo '<title>MSI Gaming - Report</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }';
    echo 'h1 { color: #ff4444; text-align: center; }';
    echo 'table { width: 100%; border-collapse: collapse; background: white; margin: 20px 0; }';
    echo 'th { background: #ff4444; color: white; padding: 12px; text-align: left; }';
    echo 'td { padding: 10px; border-bottom: 1px solid #ddd; }';
    echo 'tr:hover { background: #f9f9f9; }';
    echo '.summary { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }';
    echo '.summary-item { display: inline-block; margin-right: 30px; }';
    echo '.summary-label { font-weight: bold; }';
    echo '.summary-value { font-size: 24px; color: #ff4444; }';
    echo '</style>';
    echo '</head><body>';
    
    echo '<h1>MSI Gaming - ' . ucfirst($reportType) . ' Report</h1>';
    echo '<p style="text-align: center;">Generated on ' . date('Y-m-d H:i:s') . '</p>';
    
    if ($reportType === 'sales') {
        htmlExportSales($period);
    } elseif ($reportType === 'orders') {
        htmlExportOrders($period);
    } elseif ($reportType === 'products') {
        htmlExportProducts();
    } elseif ($reportType === 'customers') {
        htmlExportCustomers();
    }
    
    echo '</body></html>';
    exit;
}

/**
 * HTML EXPORT SALES
 */
function htmlExportSales($period) {
    global $conn;
    
    echo '<h2>Sales Report - ' . ucfirst($period) . '</h2>';
    
    $dateFilter = getDateFilter($period);
    
    // Summary
    $sumResult = $conn->query("
        SELECT COUNT(*) as orders, SUM(total_amount) as revenue
        FROM orders WHERE created_at >= '$dateFilter'
    ");
    $summary = $sumResult->fetch_assoc();
    
    echo '<div class="summary">';
    echo '<div class="summary-item"><span class="summary-label">Total Orders:</span> <span class="summary-value">' . $summary['orders'] . '</span></div>';
    echo '<div class="summary-item"><span class="summary-label">Total Revenue:</span> <span class="summary-value">$' . number_format($summary['revenue'], 2) . '</span></div>';
    echo '</div>';
    
    // Table
    echo '<table><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Avg Order</th></tr></thead><tbody>';
    
    $result = $conn->query("
        SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue, AVG(total_amount) as avg
        FROM orders WHERE created_at >= '$dateFilter'
        GROUP BY DATE(created_at) ORDER BY date DESC
    ");
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['date'] . '</td>';
        echo '<td>' . $row['orders'] . '</td>';
        echo '<td>$' . number_format($row['revenue'], 2) . '</td>';
        echo '<td>$' . number_format($row['avg'], 2) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}

/**
 * HTML EXPORT ORDERS
 */
function htmlExportOrders($period) {
    global $conn;
    
    echo '<h2>Order Report - ' . ucfirst($period) . '</h2>';
    
    $dateFilter = getDateFilter($period);
    
    echo '<table><thead><tr><th>Order #</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>';
    
    $result = $conn->query("
        SELECT order_number, customer_id, total_amount, status, created_at
        FROM orders WHERE created_at >= '$dateFilter'
        ORDER BY created_at DESC
    ");
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['order_number'] . '</td>';
        echo '<td>' . $row['customer_id'] . '</td>';
        echo '<td>$' . number_format($row['total_amount'], 2) . '</td>';
        echo '<td>' . strtoupper($row['status']) . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($row['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}

/**
 * HTML EXPORT PRODUCTS
 */
function htmlExportProducts() {
    global $conn;
    
    echo '<h2>Product Report</h2>';
    
    echo '<table><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Sales</th><th>Revenue</th></tr></thead><tbody>';
    
    $result = $conn->query("
        SELECT 
            p.name, p.category, p.price, p.stock,
            COUNT(oi.id) as sales,
            SUM(oi.quantity * oi.unit_price) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        GROUP BY p.id ORDER BY COALESCE(revenue, 0) DESC
    ");
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['name'] . '</td>';
        echo '<td>' . $row['category'] . '</td>';
        echo '<td>$' . number_format($row['price'], 2) . '</td>';
        echo '<td>' . $row['stock'] . '</td>';
        echo '<td>' . ($row['sales'] ?? 0) . '</td>';
        echo '<td>$' . number_format($row['revenue'] ?? 0, 2) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}

/**
 * HTML EXPORT CUSTOMERS
 */
function htmlExportCustomers() {
    global $conn;
    
    echo '<h2>Customer Report</h2>';
    
    echo '<table><thead><tr><th>Name</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Last Order</th></tr></thead><tbody>';
    
    $result = $conn->query("
        SELECT 
            CONCAT(c.first_name, ' ', c.last_name) as name,
            c.phone,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.created_at) as last_order
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        GROUP BY c.id ORDER BY total_spent DESC
    ");
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $row['name'] . '</td>';
        echo '<td>' . $row['phone'] . '</td>';
        echo '<td>' . ($row['order_count'] ?? 0) . '</td>';
        echo '<td>$' . number_format($row['total_spent'] ?? 0, 2) . '</td>';
        echo '<td>' . ($row['last_order'] ? date('Y-m-d', strtotime($row['last_order'])) : 'N/A') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}

/**
 * GET PERIOD DATA HELPER
 */
function getPeriodData() {
    global $conn;
    
    $period = $_GET['period'] ?? 'month';
    
    $dateFilter = getDateFilter($period);
    
    $result = $conn->query("
        SELECT COUNT(*) as orders, SUM(total_amount) as revenue
        FROM orders WHERE created_at >= '$dateFilter'
    ");
    
    $data = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'period' => $period,
        'data' => $data
    ]);
}

/**
 * GET DATE FILTER HELPER
 */
function getDateFilter($period) {
    switch ($period) {
        case 'week':
            return date('Y-m-d', strtotime('-7 days'));
        case 'month':
            return date('Y-m-d', strtotime('-30 days'));
        case 'year':
            return date('Y-m-d', strtotime('-365 days'));
        default:
            return date('Y-m-d', strtotime('-30 days'));
    }
}

?>