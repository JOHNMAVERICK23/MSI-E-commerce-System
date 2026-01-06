-- FILE: database/msi_ecommerce.sql
-- (FIXED) MSI E-COMMERCE COMPLETE DATABASE SCHEMA

-- Sinisigurado nito na malinis ang database bago i-create ulit ang tables
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

-- ==========================================
-- USERS TABLE (Para lang sa login credentials)
-- ==========================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin', 'staff') DEFAULT 'customer',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- CUSTOMERS TABLE (BAGO - Dito ilalagay ang info ng customer)
-- ==========================================
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- ORDERS TABLE (Inayos para kumonekta sa `customers` table)
-- ==========================================
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    shipping_info TEXT, -- Dito ilalagay ang shipping details
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
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
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ==========================================
-- ACTIVITY LOG TABLE
-- ==========================================
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ==========================================
-- SAMPLE DATA (Passwords: 'admin' para sa admin/staff, 'password' para sa customer)
-- ==========================================
INSERT INTO users (username, email, password, role, status) VALUES
('admin', 'admin@msi.com', '$2y$10$w0B1SA4dAd4Jd3M8y.wLTu.0rS4DRufS13kYsvjY4iAwaau4aTCyq', 'admin', 'active'),
('staff', 'staff@msi.com', '$2y$10$w0B1SA4dAd4Jd3M8y.wLTu.0rS4DRufS13kYsvjY4iAwaau4aTCyq', 'staff', 'active'),
('customer', 'customer@msi.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/TVm', 'customer', 'active');


-- ================================================
-- DATABASE SCHEMA UPDATES FOR MISSING FEATURES
-- ================================================
-- Run these queries to add missing tables and columns

-- ================================================
-- 1. ADD MISSING COLUMNS TO ORDERS TABLE
-- ================================================

ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'not specified' AFTER total_amount;
ALTER TABLE orders ADD COLUMN payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending' AFTER payment_method;
ALTER TABLE orders ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER payment_status;
ALTER TABLE orders ADD COLUMN approved_at TIMESTAMP NULL AFTER approval_status;
ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE orders ADD COLUMN shipping_address TEXT AFTER updated_at;
ALTER TABLE orders ADD COLUMN shipping_city VARCHAR(100) AFTER shipping_address;
ALTER TABLE orders ADD COLUMN shipping_postal VARCHAR(20) AFTER shipping_city;

-- ================================================
-- 2. CREATE STOCK MOVEMENTS TABLE (For Inventory Tracking)
-- ================================================

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    old_stock INT NOT NULL,
    new_stock INT NOT NULL,
    change_type VARCHAR(50) NOT NULL, -- 'INCREASE' or 'DECREASE'
    difference INT NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Create index for faster queries
CREATE INDEX idx_product_stock ON stock_movements(product_id, created_at);

-- ================================================
-- 3. CREATE SUPPLIER TABLE
-- ================================================

CREATE TABLE IF NOT EXISTS suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(100),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ================================================
-- 4. ADD SUPPLIER LINK TO PRODUCTS
-- ================================================

ALTER TABLE products ADD COLUMN supplier_id INT AFTER category;
ALTER TABLE products ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- ================================================
-- 5. CREATE AUDIT LOG TABLE
-- ================================================

CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(100), -- 'order', 'product', 'customer', etc
    entity_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create index for audit log
CREATE INDEX idx_audit_user_date ON audit_log(user_id, created_at);
CREATE INDEX idx_audit_entity ON audit_log(entity_type, entity_id);

-- ================================================
-- 6. CREATE LOGIN ATTEMPTS TABLE (For Security)
-- ================================================

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    attempt_status ENUM('success', 'failed') DEFAULT 'failed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index for login tracking
CREATE INDEX idx_login_attempts_username ON login_attempts(username, created_at);

-- ================================================
-- 7. ADD COLUMNS TO USERS TABLE FOR SECURITY
-- ================================================

ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER status;
ALTER TABLE users ADD COLUMN failed_attempts INT DEFAULT 0 AFTER last_login;
ALTER TABLE users ADD COLUMN locked_until TIMESTAMP NULL AFTER failed_attempts;

-- ================================================
-- 8. CREATE PAYMENTS TABLE
-- ================================================

CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL UNIQUE,
    payment_method VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    reference_number VARCHAR(255),
    transaction_id VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ================================================
-- 9. CREATE SHIPMENTS TABLE (For Order Tracking)
-- ================================================

CREATE TABLE IF NOT EXISTS shipments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    courier VARCHAR(100),
    waybill_number VARCHAR(255),
    tracking_url VARCHAR(500),
    status ENUM('pending', 'shipped', 'in_transit', 'delivered', 'cancelled') DEFAULT 'pending',
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ================================================
-- 10. CREATE ORDER STATUS HISTORY TABLE
-- ================================================

CREATE TABLE IF NOT EXISTS order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT,
    change_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ================================================
-- 11. SAMPLE SUPPLIERS DATA
-- ================================================

INSERT INTO suppliers (name, email, phone, address, city, country, status) VALUES
('Gaming Components Ltd', 'supplier@gaming.com', '+1-800-GAMING', '123 Tech Street', 'Silicon Valley', 'USA', 'active'),
('Performance Electronics', 'sales@perftech.com', '+1-555-PERF-01', '456 Innovation Ave', 'Austin', 'USA', 'active'),
('Global Tech Supplies', 'contact@globaltech.com', '+1-888-GLOBAL-1', '789 Supply Drive', 'Chicago', 'USA', 'active');

-- ================================================
-- 12. UPDATE EXISTING ORDERS WITH PAYMENT INFO
-- ================================================

-- Update orders table with payment method if not already set
UPDATE orders SET payment_method = 'gcash' WHERE payment_method IS NULL LIMIT 50;

