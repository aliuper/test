<?php
/**
 * Yazdırma Kuyruğuna Ekleme API
 * Bu dosya, uzaktan yazdırma taleplerini veritabanındaki yazdırma kuyruğuna ekler
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

// Check if all required fields are present
if (!isset($_POST['masa_id']) || !isset($_POST['masa_adi']) || !isset($_POST['receipt_type']) || !isset($_POST['receipt_data'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Eksik parametreler'
    ]);
    exit;
}

// Get POST data
$masa_id = intval($_POST['masa_id']);
$masa_adi = $_POST['masa_adi'];
$receipt_type = $_POST['receipt_type'];
$receipt_data = $_POST['receipt_data'];

// Validate receipt type
$validTypes = ['full', 'split', 'personal'];
if (!in_array($receipt_type, $validTypes)) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz fiş türü'
    ]);
    exit;
}

// Validate receipt data (make sure it's valid JSON)
$decodedData = json_decode($receipt_data);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz fiş verisi: ' . json_last_error_msg()
    ]);
    exit;
}

try {
    // Create database connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Current timestamp
    $currentTime = date('Y-m-d H:i:s');

    // Insert print job into queue
    $stmt = $conn->prepare("INSERT INTO print_queue (masa_id, masa_adi, receipt_type, receipt_data, created_at) VALUES (?, ?, ?, ?, ?)");
    $result = $stmt->execute([$masa_id, $masa_adi, $receipt_type, $receipt_data, $currentTime]);

    if ($result) {
        // Log the activity for audit
        $userId = $_SESSION['user_id'] ?? 0;
        $userIp = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $logStmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, activity_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $activityDetails = json_encode([
            'action' => 'add_print_job',
            'table_id' => $masa_id,
            'table_name' => $masa_adi,
            'receipt_type' => $receipt_type
        ]);
        $logStmt->execute([$userId, 'print_request', $activityDetails, $userIp, $userAgent]);
        
        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Yazdırma işi kuyruğa eklendi',
            'queue_id' => $conn->lastInsertId()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Yazdırma işi eklenirken bir hata oluştu'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
}
?>