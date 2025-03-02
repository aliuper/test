<?php
/**
 * Sipariş Durumu Güncelleme AJAX İşlemi
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Giriş yapılmamışsa yanıt ver
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Sadece garson ve süper admin erişebilir
if (!isWaiter() && !isSuperAdmin() && !isKitchen()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

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

// CSRF kontrolü
if (!isset($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

// Gerekli alanları kontrol et
if (!isset($input['order_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Değerleri al
$orderId = (int)$input['order_id'];
$status = sanitizeInput($input['status']);

// Geçerli durumlar
$validStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'completed', 'cancelled'];

if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Sipariş bilgilerini getir
$order = dbQuerySingle("SELECT * FROM orders WHERE id = ?", [$orderId]);

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Durum metinleri
$statusTexts = [
    'pending' => 'Beklemede',
    'confirmed' => 'Onaylandı',
    'preparing' => 'Hazırlanıyor',
    'ready' => 'Hazır',
    'delivered' => 'Teslim Edildi',
    'completed' => 'Tamamlandı',
    'cancelled' => 'İptal Edildi'
];

// Durumu güncelle
$result = dbExecute("UPDATE orders SET status = ? WHERE id = ?", [$status, $orderId]);

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
    exit;
}

// Eğer sipariş hazır veya teslim edildi ise, tüm sipariş ürünlerini de güncelle
if ($status === 'ready') {
    dbExecute("UPDATE order_items SET status = 'ready' WHERE order_id = ? AND status IN ('pending', 'preparing')", [$orderId]);
} elseif ($status === 'delivered') {
    dbExecute("UPDATE order_items SET status = 'delivered' WHERE order_id = ? AND status IN ('pending', 'preparing', 'ready')", [$orderId]);
} elseif ($status === 'cancelled') {
    dbExecute("UPDATE order_items SET status = 'cancelled' WHERE order_id = ?", [$orderId]);
}

// Olay kaydı oluştur
$userId = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];
dbInsert("
    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
", [$userId, 'update_status', 'order', $orderId, "Sipariş durumu değiştirildi: {$statusTexts[$status]}"]);

// Başarılı yanıt
echo json_encode([
    'success' => true, 
    'message' => 'Order status updated successfully',
    'order_id' => $orderId,
    'status' => $status,
    'status_text' => $statusTexts[$status],
    'updated_by' => $userName
]);