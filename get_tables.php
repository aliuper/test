<?php
/**
 * Masaları Getir API
 * Bu dosya, tüm masaların durumlarını ve açık olma sürelerini döndürür
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

    // Get all tables with their status and opened_at time
    $stmt = $conn->prepare("SELECT * FROM masa ORDER BY table_id ASC");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each table, calculate time since opened if it's busy
    foreach ($tables as &$table) {
        if ($table['durum'] == 1 && !empty($table['opened_at'])) {
            $openedTime = new DateTime($table['opened_at']);
            $currentTime = new DateTime();
            $interval = $currentTime->diff($openedTime);
            
            if ($interval->h > 0) {
                $table['open_duration'] = $interval->h . ' saat ' . $interval->i . ' dakika';
            } elseif ($interval->i > 0) {
                $table['open_duration'] = $interval->i . ' dakika';
            } else {
                $table['open_duration'] = 'Yeni açıldı';
            }
        } else {
            $table['open_duration'] = '';
        }
    }

    // Return tables data
    echo json_encode([
        'success' => true,
        'tables' => $tables,
        'count' => count($tables),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
}
?>