<?php
define('SECURITY', true);
if (!defined('SECURITY')) die('İzin Yok..!');
if (count($_POST) === 0) {
    $_SESSION['_csrf_token_admin'] = md5(time() . rand(0, 999999));
}
require_once '../../config.php';
require_once '../../function.php';
require_once '../includes/settings.php';
if (!isAuth()) header('Location: ../login.php');
$defaultLang = $setting['setting_default_lang'];

// Masa numarasını POST isteğinden al
$masaId = $_POST['masaId'];

// Veritabanı bağlantısı
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// POST isteğinden gelen verileri al
$productId = $_POST['productId'];
$newQuantity = $_POST['newQuantity'];

try {
    // Veritabanı bağlantısını oluştur
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    // Hata modunu etkinleştir (isteğe bağlı)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ürün adetini güncelle, masa numarasını da dikkate al
$stmt = $conn->prepare("UPDATE adisyon SET quantity = quantity + 1 WHERE product_id = ? AND masa_id = ?");
$stmt->execute([$productId, $masaId]);


    echo "Ürün adeti başarıyla güncellendi.";
} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
}

$conn = null;
?>
