<?php
/**
 * Fiş Verisi Getirme API
 * Bu dosya, yazdırma kuyruğundaki bir fişin verilerini döndürür (yazdırmadan)
 */

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

// Set header for JSON response
header('Content-Type: application/json');

// Database connection
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// Check if queue_id is provided
if (!isset($_POST['queue_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Kuyruk ID gerekli'
    ]);
    exit;
}

// Get POST data
$queue_id = intval($_POST['queue_id']);

try {
    // Create database connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get print job details
    $stmt = $conn->prepare("SELECT * FROM print_queue WHERE id = ?");
    $stmt->execute([$queue_id]);
    $printJob = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$printJob) {
        echo json_encode([
            'success' => false,
            'message' => 'Yazdırma işi bulunamadı'
        ]);
        exit;
    }

    // Return the receipt data without marking as printed
    echo json_encode([
        'success' => true,
        'message' => 'Fiş verisi alındı',
        'receipt_data' => $printJob['receipt_data'],
        'receipt_type' => $printJob['receipt_type'],
        'masa_id' => $printJob['masa_id'],
        'masa_adi' => $printJob['masa_adi'],
        'created_at' => $printJob['created_at'],
        'printed' => $printJob['printed'],
        'printed_at' => $printJob['printed_at']
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
}
?>