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

// Güncel tarih ve saat bilgisini al
$guncelTarihSaat = date('Y-m-d H:i:s');

// Veritabanı bağlantısı için gerekli bilgileri buraya ekleyin
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// POST isteğinden gelen verileri al
$masaAdi = $_POST['masaAdi'];
$toplamFiyat = $_POST['toplamFiyat'];
$odemeYontemi = $_POST['odemeYontemi'];

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

// Karakter kümesini ayarla
if (!$conn->set_charset("utf8mb4")) {
    echo json_encode([
        'success' => false,
        'message' => "Karakter kümesi ayarlanamadı: " . $conn->error
    ]);
    exit;
}

// Veritabanına ödeme bilgilerini ekle
$sql = "INSERT INTO gelir (masa_adi, toplam_fiyat, odeme_yontemi, tarih_saat) VALUES ('$masaAdi', '$toplamFiyat', '$odemeYontemi', '$guncelTarihSaat')";

if ($conn->query($sql) === TRUE) {
    echo json_encode([
        'success' => true,
        'message' => "Ödeme bilgileri başarıyla kaydedildi."
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => "Hata: " . $sql . "<br>" . $conn->error
    ]);
}

// Veritabanı bağlantısını kapat
$conn->close();
?>