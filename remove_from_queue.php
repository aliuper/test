<?php
/**
 * Yazdırma Kuyruğundan Kaldırma API
 * Bu dosya, yazdırma kuyruğundan bir işi kaldırır
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

    // Begin transaction
    $conn->beginTransaction();

    // Get print job details for logging
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

    // Remove job from queue
    $deleteStmt = $conn->prepare("DELETE FROM print_queue WHERE id = ?");
    $deleteResult = $deleteStmt->execute([$queue_id]);

    if ($deleteResult) {
        // Log the activity for audit
        $userId = $_SESSION['user_id'] ?? 0;
        $userIp = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $logStmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, activity_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $activityDetails = json_encode([
            'action' => 'remove_print_job',
            'table_id' => $printJob['masa_id'],
            'table_name' => $printJob['masa_adi'],
            'receipt_type' => $printJob['receipt_type'],
            'queue_id' => $queue_id
        ]);
        $logStmt->execute([$userId, 'print_removed', $activityDetails, $userIp, $userAgent]);

        // Commit transaction
        $conn->commit();
        
        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Yazdırma işi kuyruktan kaldırıldı'
        ]);
    } else {
        // Rollback transaction
        $conn->rollBack();
        
        echo json_encode([
            'success' => false,
            'message' => 'Yazdırma işi kaldırılırken bir hata oluştu'
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