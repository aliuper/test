<?php
/**
 * Sipariş Kaydetme API
 * Bu dosya, bir ürünü sipariş listesine ekler
 */

define('SECURITY', true);
if(!defined('SECURITY')) die('İzin Yok..!');
if (count($_POST) === 0) {
    $_SESSION['_csrf_token_admin'] = md5(time() . rand(0, 999999));
}
require_once '../../config.php';
require_once '../../function.php';
require_once '../includes/settings.php';
if(!isAuth())header('Location: ../login.php');
$defaultLang = $setting['setting_default_lang'];

// Set header for JSON response
header('Content-Type: application/json');

// Veritabanı bağlantısı
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// POST isteğinden gelen verileri al
$productId = isset($_POST['productId']) ? $_POST['productId'] : null;
$productName = isset($_POST['productName']) ? $_POST['productName'] : null;
$productPrice = isset($_POST['productPrice']) ? $_POST['productPrice'] : null;
$masaId = isset($_POST['masaId']) ? $_POST['masaId'] : null;

// Validate inputs
if (!$productId || !$productName || !$productPrice || !$masaId) {
    echo json_encode([
        'success' => false,
        'message' => 'Eksik parametreler'
    ]);
    exit;
}

try {
    // Veritabanı bağlantısını oluştur
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    // Hata modunu etkinleştir
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Adisyon tablosunda aynı ürün ve masa için varsa adetini artır, yoksa yeni bir satır ekle
    $stmt = $conn->prepare("SELECT * FROM adisyon WHERE masa_id = ? AND product_id = ?");
    $stmt->execute([$masaId, $productId]);
    $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingItem) {
        // Mevcut ürün var, adetini artır
        $newQuantity = $existingItem['quantity'] + 1;
        $stmt = $conn->prepare("UPDATE adisyon SET quantity = ? WHERE masa_id = ? AND product_id = ?");
        $stmt->execute([$newQuantity, $masaId, $productId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Ürün adeti güncellendi',
            'quantity' => $newQuantity,
            'productId' => $productId
        ]);
    } else {
        // Mevcut ürün yok, yeni bir satır ekle
        $stmt = $conn->prepare("INSERT INTO adisyon (masa_id, product_id, product_name, product_price, quantity) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$masaId, $productId, $productName, $productPrice]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Ürün başarıyla adisyona eklendi',
            'productId' => $productId
        ]);
    }
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>