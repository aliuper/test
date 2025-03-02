<?php
/**
 * Sepet Ürün Sayısını Getirme AJAX İşlemi
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

// Session'da sepet var mı kontrol et
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Sepetteki ürün sayısını hesapla
$cartCount = count($_SESSION['cart']);
$cartTotal = 0;

foreach ($_SESSION['cart'] as $item) {
    $cartTotal += $item['price'] * $item['quantity'];
}

// Başarılı yanıt
echo json_encode([
    'success' => true,
    'count' => $cartCount,
    'total' => $cartTotal
]);