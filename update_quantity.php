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

// Database connection
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// Get POST data
$masaId = $_POST['masaId'];
$productId = $_POST['productId'];
$quantity = $_POST['quantity'];

try {
    // Create database connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    // Set error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Update quantity directly
    $stmt = $conn->prepare("UPDATE adisyon SET quantity = ? WHERE masa_id = ? AND product_id = ?");
    $stmt->execute([$quantity, $masaId, $productId]);

    // Check if any rows were affected
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Quantity updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No records found to update'
        ]);
    }
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn = null;
?>