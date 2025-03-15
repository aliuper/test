<?php
/**
 * Ayar Kaydetme API
 * Bu dosya, sistem ayarlarını veritabanında günceller
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

// Check if required parameters are set
if (!isset($_POST['setting_key']) || !isset($_POST['setting_value'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Ayar anahtarı ve değeri gerekli'
    ]);
    exit;
}

// Get POST data
$setting_key = $_POST['setting_key'];
$setting_value = $_POST['setting_value'];

// Allowed settings keys for security
$allowedKeys = [
    'refresh_interval', 'sound_enabled', 'printer_name', 
    'company_name', 'company_address', 'company_phone', 
    'company_tax_id', 'support_whatsapp', 'receipt_logo',
    'display_popular_products'
];

if (!in_array($setting_key, $allowedKeys)) {
    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz ayar anahtarı'
    ]);
    exit;
}

try {
    // Create database connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Begin transaction
    $conn->beginTransaction();

    // Check if setting exists
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM system_settings WHERE setting_key = ?");
    $checkStmt->execute([$setting_key]);
    $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

    if ($exists) {
        // Update existing setting
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        $result = $stmt->execute([$setting_value, $setting_key]);
    } else {
        // Insert new setting
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $result = $stmt->execute([$setting_key, $setting_value]);
    }

    if ($result) {
        // Log the activity for audit
        $userId = $_SESSION['user_id'] ?? 0;
        $userIp = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $logStmt = $conn->prepare("INSERT INTO user_activity_log (user_id, activity_type, activity_details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $activityDetails = json_encode([
            'action' => 'update_setting',
            'setting_key' => $setting_key,
            'setting_value' => $setting_value,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        $logStmt->execute([$userId, 'setting_update', $activityDetails, $userIp, $userAgent]);

        // Commit transaction
        $conn->commit();
        
        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Ayar başarıyla kaydedildi',
            'setting_key' => $setting_key,
            'setting_value' => $setting_value
        ]);
    } else {
        // Rollback transaction
        $conn->rollBack();
        
        echo json_encode([
            'success' => false,
            'message' => 'Ayar kaydedilirken bir hata oluştu'
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