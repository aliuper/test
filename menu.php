<?php
/**
 * Menü ana sayfası - QR kod taramasıyla açılır
 */

// Gerekli dosyaları dahil et
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Sayfa bilgileri
$currentPage = 'menu';
$pageTitle = 'Menü';

// Masa ID'si kontrol
$tableId = isset($_GET['table']) ? (int)$_GET['table'] : 0;
$tableInfo = null;

if ($tableId > 0) {
    // Masa bilgilerini getir
    $tableInfo = dbQuerySingle("SELECT * FROM tables WHERE id = ?", [$tableId]);
    
    if (!$tableInfo) {
        // Geçersiz masa, hata sayfasına yönlendir
        redirect(BASE_URL . '/error.php?type=table');
    }
    
    // Eğer masa bakımda ise bilgi ver
    if ($tableInfo['status'] === 'maintenance') {
        redirect(BASE_URL . '/maintenance.php?table=' . $tableId);
    }
    
    // Masa şablonunu getir
    $qrTemplate = $tableInfo['qr_template'];
} else {
    // Varsayılan şablonu kullan
    $qrTemplate = dbQuerySingle("SELECT id FROM qr_templates WHERE is_default = 1")['id'] ?? 1;
}

// Şablon ID'si URL'den geliyorsa onu kullan (Önizleme için)
if (isset($_GET['template'])) {
    $qrTemplate = (int)$_GET['template'];
}

// Şablon bilgilerini getir
$template = dbQuerySingle("SELECT * FROM qr_templates WHERE id = ?", [$qrTemplate]);

if (!$template) {
    // Varsayılan şablonu kullan
    $template = dbQuerySingle("SELECT * FROM qr_templates WHERE is_default = 1");
    
    if (!$template) {
        // Şablon bulunamadı, hata sayfasına yönlendir
        redirect(BASE_URL . '/error.php?type=template');
    }
}

// Site ayarlarını getir
$siteTitle = getSetting('site_title', 'Restoran Menü Sistemi');
$siteDescription = getSetting('site_description', 'Modern Restoran QR Menü Sistemi');
$restaurantLogo = getSetting('restaurant_logo', '');
$restaurantSlogan = getSetting('restaurant_slogan', '');

// SEO ayarlarını getir
$seoInfo = dbQuerySingle("SELECT * FROM seo WHERE page_identifier = ?", ['menu']);

if ($seoInfo) {
    $pageTitle = !empty($seoInfo['title']) ? $seoInfo['title'] : $pageTitle;
    $pageMetaDescription = !empty($seoInfo['meta_description']) ? $seoInfo['meta_description'] : $siteDescription;
    $pageMetaKeywords = !empty($seoInfo['meta_keywords']) ? $seoInfo['meta_keywords'] : '';
    $ogTitle = !empty($seoInfo['og_title']) ? $seoInfo['og_title'] : $pageTitle;
    $ogDescription = !empty($seoInfo['og_description']) ? $seoInfo['og_description'] : $pageMetaDescription;
    $ogImage = !empty($seoInfo['og_image']) ? BASE_URL . '/assets/uploads/' . $seoInfo['og_image'] : '';
}

// Şu anki URL'yi al
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Kategorileri getir
$categories = dbQuery("SELECT * FROM categories WHERE status = 1 AND parent_id IS NULL ORDER BY sort_order");

// Ürünleri getir
$products = [];

foreach ($categories as $category) {
    // Kategori ürünlerini getir
    $categoryProducts = dbQuery("
        SELECT * FROM products 
        WHERE category_id = ? AND status = 1
        ORDER BY featured DESC, name
    ", [$category['id']]);
    
    if (!empty($categoryProducts)) {
        $products[$category['id']] = $categoryProducts;
    }
}

// İçerik oluştur
ob_start();

// Ürün detay modalı
include 'templates/shared/product-detail-modal.php';

// Tasarıma göre menü içeriği
if ($template['id'] == 1) {
    // Modern Tasarım - Yatay kategoriler ve ürün kartları
    include 'templates/template1/menu-content.php';
} elseif ($template['id'] == 2) {
    // Klasik Tasarım - Liste görünümü
    include 'templates/template2/menu-content.php';
} elseif ($template['id'] == 3) {
    // Minimalist Tasarım - Sol kategori menüsü
    include 'templates/template3/menu-content.php';
} else {
    // Varsayılan
    include 'templates/template1/menu-content.php';
}

$content = ob_get_clean();

// Şablonu dahil et
include 'templates/template' . $template['id'] . '/index.php';