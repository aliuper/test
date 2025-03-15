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

// Get current date and time
$currentDateTime = date('Y-m-d H:i:s');

// Database connection
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// Get POST data
$masaId = $_POST['masaId'];
$personName = $_POST['personName'];
$amount = $_POST['amount']; // Remove ₺ if present
$amount = str_replace('₺', '', $amount);
$paymentMethod = $_POST['paymentMethod'];

// Format table name with person info
$masaAdi = "Masa " . $masaId . " (" . $personName . ")";

try {
    // Create database connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die(json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $conn->connect_error
        ]));
    }

    // Set character encoding
    if (!$conn->set_charset("utf8mb4")) {
        die(json_encode([
            'success' => false,
            'message' => 'Character set could not be set: ' . $conn->error
        ]));
    }

    // Insert payment record into gelir table
    $sql = "INSERT INTO gelir (masa_adi, toplam_fiyat, odeme_yontemi, tarih_saat) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $masaAdi, $amount, $paymentMethod, $currentDateTime);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $stmt->error
        ]);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>