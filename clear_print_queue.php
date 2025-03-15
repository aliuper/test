<?php
/**
 * Yazdırma Kuyruğunu Temizleme API
 * Bu dosya, yazdırma kuyruğundaki tüm bekleyen işleri temizler
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

try {
    // Create database connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Begin transaction
    $conn->beginTransaction();
    
    // Count items before deletion for reporting
    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM print_queue WHERE printed = 0");
    $countStmt->execute();
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Delete all unprinted jobs from queue
    $stmt = $conn->prepare("DELETE FROM print_queue WHERE printed = 0");
    $result = $stmt->execute();

    if ($result) {
        // Log the activity for audit
        $userId = $_SESSION['user_id'] ?? 0;
        $userIp = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $logStmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, activity_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $activityDetails = json_encode([
            'action' => 'clear_print_queue',
            'count' => $count,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $logStmt->execute([$userId, 'print_queue_cleared', $activityDetails, $userIp, $userAgent]);

        // Commit transaction
        $conn->commit();
        
        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Yazdırma kuyruğu temizlendi',
            'count' => $count
        ]);
    } else {
        // Rollback transaction
        $conn->rollBack();
        
        echo json_encode([
            'success' => false,
            'message' => 'Yazdırma kuyruğu temizlenirken bir hata oluştu'
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