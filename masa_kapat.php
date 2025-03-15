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

// JSON formatında cevap döndür
header('Content-Type: application/json');

// Veritabanı bağlantısı için gerekli bilgileri buraya ekleyin
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// POST isteğinden gelen masaId'yi al
$masaId = $_POST['masaId'];

// Veritabanına bağlan
$conn = new mysqli($servername, $username, $password, $dbname);

// Bağlantıyı kontrol et
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => "Veritabanına bağlanılamadı: " . $conn->connect_error
    ]);
    exit;
}

// Masadaki ürünleri temizle ve masa durumunu 0 yap
$sql = "DELETE FROM adisyon WHERE masa_id = $masaId";
$result = $conn->query($sql);

if ($result === TRUE) {
    // Masanın durumunu 0 yap
    $sql = "UPDATE masa SET durum = 0 WHERE table_id = $masaId";
    $result = $conn->query($sql);
    
    if ($result === TRUE) {
        echo json_encode([
            'success' => true,
            'message' => "Masa başarıyla kapatıldı"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Masa durumu güncellenirken hata: " . $conn->error
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => "Siparişler silinirken hata: " . $conn->error
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>