<?php
/**
 * Yazdırma Kuyruğu Kontrol API
 * Bu dosya, veritabanındaki yazdırma kuyruğunu kontrol eder ve yazdırılmamış işleri döndürür
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

    // Get unprinted jobs from queue
    $stmt = $conn->prepare("SELECT * FROM print_queue WHERE printed = 0 ORDER BY created_at ASC");
    $stmt->execute();
    $printQueue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return print queue
    echo json_encode([
        'success' => true,
        'print_queue' => $printQueue,
        'count' => count($printQueue)
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
}
?>