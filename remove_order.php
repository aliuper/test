<?php
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

// Veritabanı bağlantısı
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// MySQL bağlantısını oluştur
$db = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);

// Ürün ID'sini al
$productId = $_POST['productId'];

// Masa ID'sini al veya boşsa hata mesajı göster
if (isset($_POST['masaId']) && !empty($_POST['masaId'])) {
    $masaId = $_POST['masaId'];
} else {
    echo "Hata: Lütfen bir masa seçiniz.";
    exit; // Hata durumunda işlemi sonlandır
}

try {
    // Ürün miktarını sorgula
    $stmt = $db->prepare("SELECT quantity FROM adisyon WHERE product_id = ? AND masa_id = ?");
    $stmt->execute([$productId, $masaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row['quantity'] > 1) {
        // Ürün miktarı 1'den fazlaysa, miktarından 1 eksilt
        $stmt = $db->prepare("UPDATE adisyon SET quantity = quantity - 1 WHERE product_id = ? AND masa_id = ?");
        $stmt->execute([$productId, $masaId]);
        echo "Ürün miktarı başarıyla güncellendi.";
    } else {
        // Ürün miktarı 1 ise, adisyonu sil
        $stmt = $db->prepare("DELETE FROM adisyon WHERE product_id = ? AND masa_id = ?");
        $stmt->execute([$productId, $masaId]);
        echo "Ürün başarıyla silindi.";
    }
} catch (PDOException $e) {
    // Hata durumunda hata mesajını döndür
    echo "Hata: " . $e->getMessage();
}
?>
