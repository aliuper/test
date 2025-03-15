<?php
/**
 * Masa Durumu Güncelleme API
 * Bu dosya, bir masanın durumunu (boş/dolu) günceller ve açılış zamanını kaydeder
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

// Check if masa_id is provided
if (!isset($_POST['masa_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Masa ID gerekli'
    ]);
    exit;
}

// Get POST data
$masa_id = intval($_POST['masa_id']);
$status = isset($_POST['status']) ? intval($_POST['status']) : 1; // Default to "busy"

try {
    // Create database connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Begin transaction
    $conn->beginTransaction();

    // Current timestamp
    $currentTime = date('Y-m-d H:i:s');

    // Update table status and opened_at time if status is 1 (busy)
    if ($status == 1) {
        $stmt = $conn->prepare("UPDATE masa SET durum = ?, opened_at = ? WHERE table_id = ?");
        $result = $stmt->execute([$status, $currentTime, $masa_id]);
    } else {
        $stmt = $conn->prepare("UPDATE masa SET durum = ?, opened_at = NULL WHERE table_id = ?");
        $result = $stmt->execute([$status, $masa_id]);
    }

    if ($result) {
        // Log the activity for audit
        $userId = $_SESSION['user_id'] ?? 0;
        $userIp = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $logStmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, activity_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $activityDetails = json_encode([
            'action' => $status == 1 ? 'open_table' : 'close_table',
            'table_id' => $masa_id,
            'timestamp' => $currentTime
        ]);
        $logStmt->execute([$userId, 'table_status_update', $activityDetails, $userIp, $userAgent]);

        // Commit transaction
        $conn->commit();
        
        // Return success
        echo json_encode([
            'success' => true,
            'message' => $status == 1 ? 'Masa açıldı' : 'Masa kapatıldı',
            'table_id' => $masa_id,
            'status' => $status,
            'opened_at' => $status == 1 ? $currentTime : null
        ]);
    } else {
        // Rollback transaction
        $conn->rollBack();
        
        echo json_encode([
            'success' => false,
            'message' => 'Masa durumu güncellenirken bir hata oluştu'
        ]);
    }
} catch (PDOException $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
}
?>