<?php
/**
 * Sepete Ürün Ekleme AJAX İşlemi
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// AJAX isteğini kontrol et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// JSON verilerini al
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Gerekli alanları kontrol et
if (!isset($input['product_id']) || !isset($input['quantity'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Değerleri al
$productId = (int)$input['product_id'];
$quantity = (int)$input['quantity'];
$note = isset($input['note']) ? sanitizeInput($input['note']) : '';
$options = isset($input['options']) ? sanitizeInput($input['options']) : '';
$tableId = isset($input['table_id']) ? (int)$input['table_id'] : 0;

// Geçerlilik kontrolü
if ($productId <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity']);
    exit;
}

// Ürün bilgilerini getir
$product = dbQuerySingle("SELECT * FROM products WHERE id = ? AND status = 1", [$productId]);

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Fiyatı belirle
$price = $product['discount_price'] && $product['discount_price'] < $product['price'] 
    ? $product['discount_price'] 
    : $product['price'];

// Session'da sepet var mı kontrol et
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Benzersiz ürün anahtarı oluştur (ürün ID + ek seçenekler + not)
$itemKey = md5($productId . $options . $note);

// Ürün zaten sepette var mı kontrol et
if (isset($_SESSION['cart'][$itemKey])) {
    // Miktarı güncelle
    $_SESSION['cart'][$itemKey]['quantity'] += $quantity;
} else {
    // Yeni ürün ekle
    $_SESSION['cart'][$itemKey] = [
        'product_id' => $productId,
        'price' => $price,
        'quantity' => $quantity,
        'note' => $note,
        'options' => $options,
    ];
}

// Masayı kaydet (isteğe bağlı)
if ($tableId > 0) {
    $_SESSION['table_id'] = $tableId;
}

// Başarılı yanıt
echo json_encode([
    'success' => true, 
    'message' => 'Product added to cart successfully',
    'item' => $_SESSION['cart'][$itemKey],
    'count' => count($_SESSION['cart'])
]);