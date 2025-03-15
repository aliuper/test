<?php
/**
 * Fiş Yazdırma İşlem API
 * Bu dosya, yazdırma kuyruğundaki bir işi yazdırıldı olarak işaretler ve fiş verilerini döndürür
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

    // Get print job details
    $stmt = $conn->prepare("SELECT * FROM print_queue WHERE id = ? AND printed = 0");
    $stmt->execute([$queue_id]);
    $printJob = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$printJob) {
        echo json_encode([
            'success' => false,
            'message' => 'Yazdırma işi bulunamadı veya zaten yazdırıldı'
        ]);
        exit;
    }

    // Current timestamp
    $currentTime = date('Y-m-d H:i:s');

    // Mark job as printed
    $updateStmt = $conn->prepare("UPDATE print_queue SET printed = 1, printed_at = ? WHERE id = ?");
    $updateResult = $updateStmt->execute([$currentTime, $queue_id]);

    if ($updateResult) {
        // Log the activity for audit
        $userId = $_SESSION['user_id'] ?? 0;
        $userIp = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $logStmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, activity_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $activityDetails = json_encode([
            'action' => 'print_receipt',
            'table_id' => $printJob['masa_id'],
            'table_name' => $printJob['masa_adi'],
            'receipt_type' => $printJob['receipt_type'],
            'queue_id' => $queue_id
        ]);
        $logStmt->execute([$userId, 'print_processed', $activityDetails, $userIp, $userAgent]);

        // Commit transaction
        $conn->commit();
        
        // Return the receipt data for printing
        echo json_encode([
            'success' => true,
            'message' => 'Yazdırma işi başarıyla işlendi',
            'receipt_data' => $printJob['receipt_data'],
            'receipt_type' => $printJob['receipt_type'],
            'masa_id' => $printJob['masa_id'],
            'masa_adi' => $printJob['masa_adi']
        ]);
    } else {
        // Rollback transaction
        $conn->rollBack();
        
        echo json_encode([
            'success' => false,
            'message' => 'Yazdırma işi güncellenirken bir hata oluştu'
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