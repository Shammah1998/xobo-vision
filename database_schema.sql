-- Multi-Tenant E-Commerce & Delivery Platform Database Schema

-- Companies/Tenants
CREATE TABLE companies (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(255) UNIQUE NOT NULL,
  status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL
);

-- Users (super_admin, company_admin, user)
CREATE TABLE users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  company_id   INT NULL,
  email        VARCHAR(255) UNIQUE NOT NULL,
  password     VARCHAR(255) NOT NULL,
  role         ENUM('super_admin','admin','admin_user','user') NOT NULL,
  created_at   DATETIME NOT NULL,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
);

-- Products per company
CREATE TABLE products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  company_id   INT NOT NULL,
  name         VARCHAR(255) NOT NULL,
  sku          VARCHAR(100) NOT NULL,
  weight_kg    DECIMAL(5,2) NOT NULL,
  rate_ksh     DECIMAL(10,2) NOT NULL,
  created_at   DATETIME NOT NULL,
  FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- Orders
CREATE TABLE orders (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NULL,
  company_id   INT NULL,
  total_ksh    DECIMAL(12,2) NOT NULL,
  address      TEXT,
  status       ENUM('pending','confirmed') NOT NULL DEFAULT 'pending',
  created_at   DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
);

CREATE TABLE order_items (
  order_id     INT NOT NULL,
  product_id   INT NOT NULL,
  quantity     INT NOT NULL,
  line_total   DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (order_id, product_id),
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Cart items (optional - alternative to session storage)
CREATE TABLE cart_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  product_id   INT NOT NULL,
  quantity     INT NOT NULL,
  created_at   DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY unique_user_product (user_id, product_id)
);

-- Delivery details for cart items and orders
CREATE TABLE delivery_details (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL,
  product_id        INT NOT NULL,
  session_id        VARCHAR(255),
  destination       VARCHAR(500) NULL,
  company_name      VARCHAR(255) NULL,
  company_address   TEXT NULL,
  recipient_name    VARCHAR(255) NULL,
  recipient_phone   VARCHAR(20) NULL,
  created_at        DATETIME NOT NULL,
  updated_at        DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY unique_user_product_session (user_id, product_id, session_id)
);

-- Order delivery details (for completed orders)
CREATE TABLE order_delivery_details (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  order_id          INT NOT NULL,
  product_id        INT NOT NULL,
  destination       VARCHAR(500) NULL,
  company_name      VARCHAR(255) NULL,
  company_address   TEXT NULL,
  recipient_name    VARCHAR(255) NULL,
  recipient_phone   VARCHAR(20) NULL,
  created_at        DATETIME NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY unique_order_product (order_id, product_id)
);

-- Drivers assigned to orders
CREATE TABLE drivers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  driver_name VARCHAR(255) NOT NULL,
  assigned_at DATETIME NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Order vehicle types
CREATE TABLE order_vehicle_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  vehicle_type VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Order accessories (for storing accessories information with orders)
CREATE TABLE order_accessories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  main_product_id INT NOT NULL,
  accessory_product_id INT NULL,
  accessory_name VARCHAR(255) NOT NULL,
  accessory_sku VARCHAR(100) NOT NULL,
  accessory_weight DECIMAL(5,2) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (main_product_id) REFERENCES products(id),
  FOREIGN KEY (accessory_product_id) REFERENCES products(id)
);

-- Insert default super admin user
INSERT INTO users (email, password, role) VALUES 
('support@xobo.co.ke', '$2y$10$7v7GGrwENRTFL850XyVl4.TYByBqqr2xmTVQPO/wBJV/dr/9RG33C', 'super_admin');
-- Default password is 'Xobo@2025' - change this in production! 

-- ALTER TABLE statements to rename columns in delivery_details:
ALTER TABLE delivery_details CHANGE destination pick_up VARCHAR(500) NULL;
ALTER TABLE delivery_details CHANGE company_name drop_off VARCHAR(255) NULL;
ALTER TABLE delivery_details CHANGE company_address additional_notes TEXT NULL;

-- ALTER TABLE statements to rename columns in order_delivery_details:
ALTER TABLE order_delivery_details CHANGE destination pick_up VARCHAR(500) NULL;
ALTER TABLE order_delivery_details CHANGE company_name drop_off VARCHAR(255) NULL;
ALTER TABLE order_delivery_details CHANGE company_address additional_notes TEXT NULL;

-- Add status column to orders table for existing databases
ALTER TABLE orders ADD COLUMN status ENUM('pending','confirmed') NOT NULL DEFAULT 'pending' AFTER address;

ALTER TABLE users MODIFY role ENUM('super_admin','admin','admin_user','user') NOT NULL;
ALTER TABLE users ADD COLUMN name VARCHAR(255) NULL AFTER email;
ALTER TABLE users ADD COLUMN phone VARCHAR(32) NULL AFTER name;

-- Backfill name for existing users (set to part before @ in email, capitalized)
UPDATE users SET name = CONCAT(UPPER(LEFT(SUBSTRING_INDEX(email, '@', 1), 1)), LOWER(SUBSTRING(SUBSTRING_INDEX(email, '@', 1), 2))) WHERE name IS NULL OR name = '';
-- Set phone to NULL for all users (if not already)
UPDATE users SET phone = NULL WHERE phone IS NULL OR phone = '';

