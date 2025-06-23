-- Multi-Tenant E-Commerce & Delivery Platform Database Schema

-- Companies/Tenants
CREATE TABLE companies (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(255) UNIQUE NOT NULL,
  status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users (super_admin, company_admin, user)
CREATE TABLE users (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  company_id   INT NULL,
  email        VARCHAR(255) UNIQUE NOT NULL,
  password     VARCHAR(255) NOT NULL,
  role         ENUM('super_admin','company_admin','user') NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- Products per company
CREATE TABLE products (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  company_id   INT NOT NULL,
  name         VARCHAR(255) NOT NULL,
  sku          VARCHAR(100) NOT NULL,
  weight_kg    DECIMAL(5,2) NOT NULL,
  rate_ksh     DECIMAL(10,2) NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id)
);

-- Orders
CREATE TABLE orders (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  company_id   INT NOT NULL,
  total_ksh    DECIMAL(12,2) NOT NULL,
  address      TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (company_id) REFERENCES companies(id)
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
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  UNIQUE KEY unique_user_product (user_id, product_id)
);

-- Insert default super admin user
INSERT INTO users (email, password, role) VALUES 
('admin@xobo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');
-- Default password is 'password' - change this in production! 