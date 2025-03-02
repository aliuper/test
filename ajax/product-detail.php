<?php
/**
 * Ürün Detaylarını Getirme AJAX İşlemi
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// Ürün ID'sini kontrol et
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Ürün bilgilerini getir
$product = dbQuerySingle("SELECT * FROM products WHERE id = ? AND status = 1", [$productId]);

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Kategorisini getir
$category = dbQuerySingle("SELECT * FROM categories WHERE id = ?", [$product['category_id']]);

// Ürün seçeneklerini getir
$options = dbQuery("SELECT * FROM product_options WHERE product_id = ? ORDER BY id", [$productId]);

// Ürün verilerini hazırla
$productData = [
    'id' => $product['id'],
    'name' => $product['name'],
    'description' => $product['description'],
    'price' => $product['price'],
    'discount_price' => $product['discount_price'],
    'image' => $product['image'],
    'category_id' => $product['category_id'],
    'category_name' => $category ? $category['name'] : '',
    'preparation_time' => $product['preparation_time'],
    'allergens' => $product['allergens'],
    'ingredients' => $product['ingredients'],
    'calories' => $product['calories'],
    'options' => $options
];

// Başarılı yanıt
echo json_encode([
    'success' => true,
    'product' => $productData
]);