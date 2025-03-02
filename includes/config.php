<?php
/**
 * Restoran Menü Sistemi Konfigürasyon Dosyası
 */

// Hata raporlama (canlı sistemde kapatılmalı)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_NAME', 'restaurant_menu');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Site URL yapılandırması
define('BASE_URL', 'http://localhost/restaurant-menu-system');
define('ADMIN_URL', BASE_URL . '/admin');
define('ASSETS_URL', BASE_URL . '/assets');
define('ADMIN_ASSETS_URL', BASE_URL . '/admin/assets');

// Klasör yolları
define('ROOT_DIR', dirname(__DIR__));
define('INCLUDES_DIR', ROOT_DIR . '/includes');
define('TEMPLATES_DIR', ROOT_DIR . '/templates');
define('UPLOADS_DIR', ROOT_DIR . '/assets/uploads');
define('QR_CODES_DIR', UPLOADS_DIR . '/qrcodes');

// Güvenlik
define('SALT', 'c9oNHS9xMs7LBJwxmNjuJQhOk0');
define('SESSION_NAME', 'RESTAURANT_SESSION');
define('TOKEN_TIMEOUT', 3600); // 1 saat

// Kullanıcı rolleri
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_WAITER', 'waiter');
define('ROLE_KITCHEN', 'kitchen');

// QR kod ayarları
define('QR_SIZE', 300);
define('QR_MARGIN', 10);
define('QR_ERROR_CORRECTION', 'L'); // L, M, Q, H

// Sayfalama ayarları
define('ITEMS_PER_PAGE', 10);

// Timezone ayarı
date_default_timezone_set('Europe/Istanbul');

// Session başlatma
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Site ayarları
$settings = []; // Bu dizi veritabanından yüklenecek