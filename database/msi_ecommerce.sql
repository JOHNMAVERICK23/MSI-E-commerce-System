-- File: database/msi_ecommerce.sql
-- MSI E-COMMERCE COMPLETE DATABASE SCHEMA

-- Create Database
CREATE DATABASE IF NOT EXISTS msi_ecommerce;
USE msi_ecommerce;

-- ==========================================
-- USERS TABLE (Admin, Staff, Customers)
-- ==========================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin', 'staff') DEFAULT 'customer',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email)
);

-- ==========================================
-- PRODUCTS TABLE
-- ==========================================
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_price (price)
);

-- ==========================================
-- ORDERS TABLE
-- ==========================================
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_customer (customer_id),
    INDEX idx_created_at (created_at)
);

-- ==========================================
-- ORDER ITEMS TABLE
-- ==========================================
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order (order_id)
);

-- ==========================================
-- ACTIVITY LOG TABLE
-- ==========================================
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    entity_type VARCHAR(50),
    entity_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- ==========================================
-- SAMPLE DATA - USERS
-- ==========================================
INSERT INTO users (username, email, password, role, status) VALUES
('admin', 'admin@msi.com', '$2y$10$06SsNSeJT8eLjlJ1ttjMteG4D.3j84iyRcit4yFT56CBEFax/I2gy', 'admin', 'active'),
('staff', 'staff@msi.com', '$2y$10$06SsNSeJT8eLjlJ1ttjMteG4D.3j84iyRcit4yFT56CBEFax/I2gy', 'staff', 'active'),
('customer', 'customer@msi.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/TVm', 'customer', 'active'),
('john_doe', 'john@msi.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/TVm', 'customer', 'active'),
('jane_smith', 'jane@msi.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/TVm', 'customer', 'active');

-- ==========================================
-- SAMPLE DATA - PRODUCTS
-- ==========================================
INSERT INTO products (name, category, description, price, stock, status, created_by) VALUES
('MSI RTX 4090', 'Graphics Card', 'High-end gaming GPU with 24GB GDDR6X, PCIe 4.0, perfect for 4K gaming and content creation', 1599.99, 10, 'active', 1),
('MSI B650 Motherboard', 'Motherboard', 'AM5 Socket High-performance motherboard with PCIe 5.0 support, ideal for Ryzen 7000 series', 249.99, 15, 'active', 1),
('MSI DDR5 32GB', 'RAM', 'High-speed DDR5 memory kit 32GB (2x16GB) 6000MHz, low latency CAS 30, perfect for gaming and workstations', 299.99, 20, 'active', 1),
('MSI 2TB NVMe SSD', 'Storage', 'High-speed M.2 NVMe SSD 2TB with read/write speeds up to 7000MB/s, Gen 4 storage solution', 199.99, 25, 'active', 1),
('MSI RTX 4070', 'Graphics Card', 'Mid-range gaming GPU with 12GB GDDR6, excellent for 1440p gaming at high settings', 599.99, 18, 'active', 1),
('MSI B550 Pro', 'Motherboard', 'AM4 Socket Professional grade motherboard with PCIe 4.0 support, great value for money', 149.99, 22, 'active', 1),
('MSI DDR4 16GB', 'RAM', 'DDR4 memory 16GB (2x8GB) 3600MHz, CAS 18, compatible with AM4 and Intel platforms', 89.99, 30, 'active', 1),
('MSI 1TB NVMe SSD', 'Storage', 'M.2 NVMe SSD 1TB with 5500MB/s read speed, budget-friendly high-performance storage', 99.99, 35, 'active', 1),
('MSI RTX 4060', 'Graphics Card', 'Entry-level gaming GPU 8GB GDDR6, perfect for 1080p and light 1440p gaming', 299.99, 20, 'active', 1),
('MSI X870 Motherboard', 'Motherboard', 'Premium AM5 motherboard with latest features, PCIe 5.0, DDR5 support, best in class', 399.99, 8, 'active', 1);

-- ==========================================
-- SAMPLE DATA - ORDERS
-- ==========================================
INSERT INTO orders (order_number, customer_id, total_amount, status) VALUES
('ORD001', 3, 1899.98, 'completed'),
('ORD002', 4, 899.98, 'processing'),
('ORD003', 5, 499.98, 'pending'),
('ORD004', 3, 1299.99, 'completed'),
('ORD005', 4, 2099.97, 'completed');

-- ==========================================
-- SAMPLE DATA - ORDER ITEMS
-- ==========================================
INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES
(1, 1, 1, 1599.99),
(1, 4, 1, 299.99),
(2, 5, 1, 599.99),
(2, 7, 1, 299.99),
(3, 9, 1, 299.99),
(3, 8, 1, 199.99),
(4, 1, 1, 1599.99),
(4, 2, 1, 249.99),
(4, 3, 1, 299.99),
(5, 5, 1, 599.99),
(5, 6, 1, 149.99),
(5, 7, 1, 89.99),
(5, 8, 1, 99.99),
(5, 3, 1, 299.99),
(5, 9, 1, 299.99);

-- ==========================================
-- SAMPLE DATA - ACTIVITY LOG
-- ==========================================
INSERT INTO activity_log (user_id, action, description, entity_type, entity_id) VALUES
(1, 'create_product', 'Created new product: MSI RTX 4090', 'product', 1),
(1, 'create_staff', 'Created staff account for John Staff', 'user', 2),
(2, 'update_order', 'Updated order status to processing', 'order', 2),
(1, 'edit_product', 'Updated product: MSI B650 Motherboard', 'product', 2),
(2, 'view_order', 'Viewed order details for Order #ORD001', 'order', 1);

-- ==========================================
-- CREATE VIEWS FOR REPORTING
-- ==========================================
CREATE VIEW order_summary AS
SELECT 
    o.id,
    o.order_number,
    o.customer_id,
    u.username as customer_name,
    o.total_amount,
    o.status,
    COUNT(oi.id) as item_count,
    o.created_at
FROM orders o
LEFT JOIN users u ON o.customer_id = u.id
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id;

CREATE VIEW product_sales AS
SELECT 
    p.id,
    p.name,
    p.category,
    p.price,
    COUNT(oi.id) as times_sold,
    SUM(oi.quantity) as total_quantity_sold,
    SUM(oi.quantity * oi.unit_price) as total_revenue
FROM products p
LEFT JOIN order_items oi ON p.id = oi.product_id
WHERE p.status = 'active'
GROUP BY p.id
ORDER BY total_revenue DESC;

CREATE VIEW staff_activity AS
SELECT 
    a.id,
    u.username as staff_name,
    a.action,
    a.description,
    a.entity_type,
    a.created_at
FROM activity_log a
LEFT JOIN users u ON a.user_id = u.id
WHERE u.role = 'staff'
ORDER BY a.created_at DESC;