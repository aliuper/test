-- Kullanıcılar tablosu
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('super_admin', 'waiter', 'kitchen') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status TINYINT(1) DEFAULT 1
);

-- Kategoriler tablosu
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Ürünler tablosu
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    discount_price DECIMAL(10, 2) NULL,
    image VARCHAR(255),
    preparation_time INT COMMENT 'Hazırlama süresi (dakika)',
    allergens TEXT COMMENT 'Alerjen bilgileri',
    ingredients TEXT COMMENT 'İçindekiler',
    calories INT,
    featured TINYINT(1) DEFAULT 0 COMMENT 'Öne çıkan ürün',
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Ürün ek seçenekleri tablosu
CREATE TABLE product_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    price_adjustment DECIMAL(10, 2) NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Masalar tablosu
CREATE TABLE tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    location VARCHAR(100),
    qr_code VARCHAR(255) COMMENT 'QR kod resim yolu',
    qr_template INT DEFAULT 1 COMMENT 'QR menü şablon ID',
    status ENUM('available', 'occupied', 'reserved', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Siparişler tablosu
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_id INT NOT NULL,
    order_code VARCHAR(20) NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'completed', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10, 2) NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES tables(id)
);

-- Sipariş Detayları tablosu
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    options TEXT COMMENT 'Seçilen ek seçenekler',
    note TEXT,
    status ENUM('pending', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Site Ayarları tablosu
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- QR Menü Şablonları tablosu
CREATE TABLE qr_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    template_path VARCHAR(255) NOT NULL,
    thumbnail VARCHAR(255),
    is_default TINYINT(1) DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- SEO Ayarları tablosu
CREATE TABLE seo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_identifier VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    og_title VARCHAR(255),
    og_description TEXT,
    og_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Örnek Süper Admin Kullanıcı Ekleme
INSERT INTO users (username, password, email, role, full_name, status) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'super_admin', 'Süper Admin', 1);

-- Örnek Garson Kullanıcı Ekleme
INSERT INTO users (username, password, email, role, full_name, status) 
VALUES ('garson', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'garson@example.com', 'waiter', 'Garson Kullanıcı', 1);

-- Örnek Mutfak Kullanıcı Ekleme
INSERT INTO users (username, password, email, role, full_name, status) 
VALUES ('mutfak', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mutfak@example.com', 'kitchen', 'Mutfak Kullanıcı', 1);

-- Örnek Kategoriler Ekleme
INSERT INTO categories (name, description, sort_order) 
VALUES ('Ana Yemekler', 'Restoranımızın özel ana yemekleri', 1);

INSERT INTO categories (name, description, sort_order) 
VALUES ('İçecekler', 'Soğuk ve sıcak içecekler', 2);

INSERT INTO categories (name, description, sort_order) 
VALUES ('Tatlılar', 'Ev yapımı tatlılar', 3);

-- Örnek Masalar Ekleme
INSERT INTO tables (name, capacity, location) 
VALUES ('Masa 1', 4, 'Giriş Kat');

INSERT INTO tables (name, capacity, location) 
VALUES ('Masa 2', 2, 'Giriş Kat');

INSERT INTO tables (name, capacity, location) 
VALUES ('Masa 3', 6, 'Teras');

-- Varsayılan Site Ayarları
INSERT INTO settings (setting_key, setting_value, setting_group) 
VALUES ('site_title', 'Restoran Menü Sistemi', 'general');

INSERT INTO settings (setting_key, setting_value, setting_group) 
VALUES ('site_description', 'Modern Restoran QR Menü Sistemi', 'general');

INSERT INTO settings (setting_key, setting_value, setting_group) 
VALUES ('restaurant_phone', '+90 (555) 123 4567', 'contact');

INSERT INTO settings (setting_key, setting_value, setting_group) 
VALUES ('restaurant_email', 'info@restaurant.com', 'contact');

INSERT INTO settings (setting_key, setting_value, setting_group) 
VALUES ('restaurant_address', 'Örnek Mahallesi, Örnek Sokak No:123, İstanbul/Türkiye', 'contact');

-- QR Menü Şablonları
INSERT INTO qr_templates (name, template_path, is_default, status) 
VALUES ('Modern Tasarım', 'templates/template1/', 1, 1);

INSERT INTO qr_templates (name, template_path, status) 
VALUES ('Klasik Tasarım', 'templates/template2/', 1);

INSERT INTO qr_templates (name, template_path, status) 
VALUES ('Minimalist Tasarım', 'templates/template3/', 1);