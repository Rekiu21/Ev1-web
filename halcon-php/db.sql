-- Halcón - Sistema de Órdenes de Compra
-- Script SQL para Hostinger MySQL

-- Crear tabla de Usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Sales', 'Purchasing', 'Warehouse', 'Route') NOT NULL DEFAULT 'Sales',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de Órdenes
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(255) UNIQUE NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_number VARCHAR(255) NOT NULL,
    fiscal_data TEXT,
    delivery_address TEXT NOT NULL,
    notes TEXT,
    status ENUM('Ordered', 'In process', 'In route', 'Delivered') NOT NULL DEFAULT 'Ordered',
    photo_loaded_path VARCHAR(255),
    photo_delivered_path VARCHAR(255),
    deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_customer_number (customer_number),
    INDEX idx_public_lookup (deleted, customer_number, invoice_number),
    INDEX idx_status (status),
    INDEX idx_deleted (deleted),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar usuario admin por defecto
INSERT INTO users (username, password_hash, role, is_active) VALUES (
    'admin',
    '$2y$10$zg2c0oHJKhf.aP8UdeukR.VXu7/yp1FuXQBZPli0XMLOhMD6unyY.', -- contraseña: admin123
    'Admin',
    TRUE
) ON DUPLICATE KEY UPDATE id=id;

-- Insertar usuarios de ejemplo
INSERT INTO users (username, password_hash, role, is_active) VALUES
('ventas', '$2y$10$zg2c0oHJKhf.aP8UdeukR.VXu7/yp1FuXQBZPli0XMLOhMD6unyY.', 'Sales', TRUE),
('compras', '$2y$10$zg2c0oHJKhf.aP8UdeukR.VXu7/yp1FuXQBZPli0XMLOhMD6unyY.', 'Purchasing', TRUE),
('almacen', '$2y$10$zg2c0oHJKhf.aP8UdeukR.VXu7/yp1FuXQBZPli0XMLOhMD6unyY.', 'Warehouse', TRUE)
ON DUPLICATE KEY UPDATE id=id;

-- Insertar órdenes de ejemplo
INSERT INTO orders (invoice_number, customer_name, customer_number, fiscal_data, delivery_address, notes, status, created_at) VALUES
('INV-001', 'Empresa XYZ', 'CUST001', '{"tax_id": "123456789"}', 'Calle Principal 123, Ciudad', 'Primera entrega', 'Ordered', NOW()),
('INV-002', 'Comercial ABC', 'CUST002', '{"tax_id": "987654321"}', 'Avenida Central 456, Pueblo', 'Entrega urgente', 'In process', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('INV-003', 'Distribuidora 123', 'CUST003', '{"tax_id": "555444333"}', 'Boulevard Periférico 789, Zona', 'En ruta', 'In route', DATE_SUB(NOW(), INTERVAL 2 DAY)),
('INV-004', 'Minorista S.A.', 'CUST004', '{"tax_id": "777888999"}', 'Paseo Marítimo 321, Costa', 'Entrega completada', 'Delivered', DATE_SUB(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE id=id;
