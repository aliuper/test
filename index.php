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

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get daily sales stats
$today = date('Y-m-d');
$dailySalesQuery = "SELECT COUNT(*) as order_count, SUM(toplam_fiyat) as total_sales FROM gelir WHERE DATE(tarih_saat) = '$today'";
$dailySalesResult = $conn->query($dailySalesQuery);
$dailySales = $dailySalesResult->fetch_assoc();

// Get payment method stats
$paymentMethodQuery = "SELECT odeme_yontemi, COUNT(*) as count, SUM(toplam_fiyat) as total FROM gelir WHERE DATE(tarih_saat) = '$today' GROUP BY odeme_yontemi";
$paymentMethodResult = $conn->query($paymentMethodQuery);
$paymentMethods = [];
while ($row = $paymentMethodResult->fetch_assoc()) {
    $paymentMethods[] = $row;
}

// Get busy tables count
$busyTablesQuery = "SELECT COUNT(*) as busy_count FROM masa WHERE durum = 1";
$busyTablesResult = $conn->query($busyTablesQuery);
$busyTables = $busyTablesResult->fetch_assoc();

// Get most ordered products
$popularProductsQuery = "SELECT product_id, COUNT(*) as order_count FROM adisyon GROUP BY product_id ORDER BY order_count DESC LIMIT 3";
$popularProductsResult = $conn->query($popularProductsQuery);
$popularProducts = [];
while ($row = $popularProductsResult->fetch_assoc()) {
    $productId = $row['product_id'];
    $productInfoQuery = "SELECT * FROM products WHERE product_id = $productId";
    $productInfoResult = $conn->query($productInfoQuery);
    $productInfo = $productInfoResult->fetch_assoc();
    
    if ($productInfo) {
        $productName = json_decode($productInfo['product_name'], true)['tr'] ?? '';
        $row['product_name'] = $productName;
        $row['product_image'] = $productInfo['product_image'];
        $popularProducts[] = $row;
    }
}

// Get system settings
try {
    $settingsQuery = "SELECT * FROM system_settings";
    $settingsResult = $conn->query($settingsQuery);
    $systemSettings = [];
    
    while ($row = $settingsResult->fetch_assoc()) {
        $systemSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Set default values if settings don't exist
    $refreshInterval = isset($systemSettings['refresh_interval']) ? intval($systemSettings['refresh_interval']) : 30;
    $soundEnabled = isset($systemSettings['sound_enabled']) ? (bool)$systemSettings['sound_enabled'] : true;
    $autoPrintEnabled = isset($systemSettings['auto_print_enabled']) ? (bool)$systemSettings['auto_print_enabled'] : true;
    
} catch (Exception $e) {
    $refreshInterval = 30;
    $soundEnabled = true;
    $autoPrintEnabled = true;
}

// Get pending print jobs
$printJobsQuery = "SELECT COUNT(*) as pending_count FROM print_queue WHERE printed = 0";
$printJobsResult = $conn->query($printJobsQuery);
$pendingPrintJobs = $printJobsResult->fetch_assoc()['pending_count'];
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ateşli Piliçler POS Sistemi</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Toastify for notifications -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    
    <style>
        :root {
            --primary-color: #3a86ff;
            --secondary-color: #ff006e;
            --success-color: #38b000;
            --warning-color: #ffbe0b;
            --danger-color: #d90429;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #adb5bd;
            --border-radius: 10px;
            --box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            min-height: 100vh;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: white;
            box-shadow: var(--box-shadow);
            z-index: 1000;
            padding: 20px;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }

        .content {
            flex: 1;
            padding: 20px;
            transition: var(--transition);
        }

        .header {
            background: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            font-size: 24px;
            color: var(--primary-color);
        }

        .logo-text {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #2a75e8;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #2a9d00;
            transform: translateY(-2px);
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .dashboard-card-title {
            font-size: 14px;
            color: var(--gray-color);
            margin-bottom: 5px;
        }

        .dashboard-card-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .dashboard-card-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .dashboard-card-footer {
            font-size: 12px;
            color: var(--gray-color);
        }

        .card-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .card-icon-wrapper.sales {
            background: rgba(58, 134, 255, 0.1);
            color: var(--primary-color);
        }

        .card-icon-wrapper.busy {
            background: rgba(255, 0, 110, 0.1);
            color: var(--secondary-color);
        }

        .card-icon-wrapper.cash {
            background: rgba(56, 176, 0, 0.1);
            color: var(--success-color);
        }

        .card-icon-wrapper.orders {
            background: rgba(255, 190, 11, 0.1);
            color: var(--warning-color);
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-icon {
            color: var(--primary-color);
        }

        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
        }

        .table-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            box-shadow: var(--box-shadow);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }

        .table-card.busy {
            border-color: var(--danger-color);
        }

        .table-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .table-icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .table-icon.busy {
            color: var(--danger-color);
        }

        .table-name {
            font-size: 16px;
            font-weight: 600;
        }

        .table-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--danger-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .table-timer {
            margin-top: 5px;
            font-size: 12px;
            color: var(--gray-color);
        }

        .popular-products {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .popular-product {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .popular-product:last-child {
            border-bottom: none;
        }

        .popular-product-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .popular-product-info {
            flex: 1;
        }

        .popular-product-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .popular-product-count {
            font-size: 12px;
            color: var(--gray-color);
        }

        .popular-product-rank {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .payment-methods {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }

        .payment-method {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .payment-method:last-child {
            border-bottom: none;
        }

        .payment-method-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-method-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .payment-method-icon.cash {
            background: var(--success-color);
        }

        .payment-method-icon.card {
            background: var(--primary-color);
        }

        .payment-method-value {
            font-weight: 600;
        }

        .modal-content {
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: none;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-title {
            font-weight: 600;
        }

        .modal-footer {
            border-top: none;
        }

        @media (max-width: 992px) {
            .wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                margin-bottom: 20px;
            }
            
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tables-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .user-actions {
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-start;
                width: 100%;
            }
            
            .print-queue-button {
                width: 100%;
                margin-top: 8px;
            }
            
            .print-queue-text {
                display: inline-block;
            }
        }
        
        @media (max-width: 576px) {
            .user-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            
            .print-queue-button {
                grid-column: span 2;
            }
        }

        /* Animation for busy tables */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .table-card.busy {
            animation: pulse 2s infinite;
        }

        /* New category styles */
        .category-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 10px;
        }

        .category-btn {
            background: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 8px 15px;
            font-weight: 500;
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            box-shadow: var(--box-shadow);
        }

        .category-btn.active, .category-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Table group heading */
        .table-group-heading {
            font-size: 16px;
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 15px;
            color: var(--gray-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Sound toggle button */
        .sound-toggle {
            background: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--box-shadow);
        }

        .sound-toggle:hover {
            background: var(--primary-color);
            color: white;
        }

        .sound-toggle.off {
            color: var(--danger-color);
        }
        
        /* Print Queue Notification */
        .print-queue-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 1000;
            cursor: pointer;
            transition: var(--transition);
            border-left: 5px solid var(--primary-color);
        }
        
        .print-queue-notification:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .print-queue-icon {
            font-size: 24px;
            color: var(--primary-color);
            animation: pulse 2s infinite;
        }
        
        .print-queue-info {
            flex: 1;
        }
        
        .print-queue-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .print-queue-count {
            font-size: 12px;
            color: var(--gray-color);
        }
        
        .print-queue-action {
            font-size: 18px;
            color: var(--gray-color);
        }
        
        .badge-notification {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Print Queue Modal */
        .print-queue-modal .modal-body {
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .print-queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }
        
        .print-queue-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .print-queue-item-info h6 {
            margin-bottom: 5px;
        }
        
        .print-queue-item-info p {
            margin-bottom: 0;
            font-size: 12px;
            color: var(--gray-color);
        }
        
        .print-queue-item-actions {
            display: flex;
            gap: 5px;
        }
        
        .print-queue-empty {
            text-align: center;
            padding: 30px;
            color: var(--gray-color);
        }
        
        /* Form Group */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .form-check-input {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }
        
        .form-check-label {
            font-weight: normal;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Main Content -->
        <div class="content">
            <!-- Header -->
            <div class="header">
                <div class="logo">
                    <div class="logo-icon"><i class="fas fa-fire-alt"></i></div>
                    <div class="logo-text">Ateşli Piliçler POS</div>
                </div>
                <div class="user-info">
                    <div class="user-actions">
                        <button class="btn btn-primary" id="refresh-btn">
                            <i class="fas fa-sync-alt me-1"></i> Yenile
                        </button>
                        <button class="btn btn-success" id="settings-btn">
                            <i class="fas fa-cog me-1"></i> Ayarlar
                        </button>
                        <button class="sound-toggle" id="sound-toggle">
                            <i class="fas fa-volume-up"></i>
                        </button>
                        <button class="btn btn-warning print-queue-button" id="print-queue-btn" style="position: relative;">
                            <i class="fas fa-print me-1"></i> <span class="print-queue-text">Yazdırma Kuyruğu</span>
                            <?php if ($pendingPrintJobs > 0): ?>
                            <span class="badge-notification"><?= $pendingPrintJobs ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <div class="card-icon-wrapper sales">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="dashboard-card-title">Günlük Satış</div>
                    <div class="dashboard-card-value"><?= $dailySales['total_sales'] ? number_format($dailySales['total_sales'], 2) . '₺' : '0.00₺' ?></div>
                    <div class="dashboard-card-footer">Bugün: <?= date('d.m.Y') ?></div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon-wrapper busy">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="dashboard-card-title">Siparişler</div>
                    <div class="dashboard-card-value"><?= $dailySales['order_count'] ? $dailySales['order_count'] : '0' ?></div>
                    <div class="dashboard-card-footer">Toplam sipariş</div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon-wrapper cash">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="dashboard-card-title">Nakit Ödeme</div>
                    <?php
                    $cashTotal = 0;
                    foreach ($paymentMethods as $method) {
                        if ($method['odeme_yontemi'] == 'Nakit') {
                            $cashTotal = $method['total'];
                            break;
                        }
                    }
                    ?>
                    <div class="dashboard-card-value"><?= number_format($cashTotal, 2) ?>₺</div>
                    <div class="dashboard-card-footer">Günlük nakit</div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon-wrapper orders">
                        <i class="fas fa-chair"></i>
                    </div>
                    <div class="dashboard-card-title">Aktif Masalar</div>
                    <div class="dashboard-card-value"><?= $busyTables['busy_count'] ?></div>
                    <div class="dashboard-card-footer">Dolu masa sayısı</div>
                </div>
            </div>

            <!-- Category Filter -->
            <div class="category-filter">
                <button class="category-btn active" data-filter="all">Tüm Masalar</button>
                <button class="category-btn" data-filter="busy">Dolu Masalar</button>
                <button class="category-btn" data-filter="free">Boş Masalar</button>
                <button class="category-btn" data-filter="regular">Normal Masalar</button>
                <button class="category-btn" data-filter="takeaway">Paket Servis</button>
            </div>

            <!-- Tables Section -->
            <h2 class="section-title"><i class="fas fa-chair section-icon"></i> Masalar</h2>
            
            <!-- Normal Tables -->
            <div class="table-group" id="regular-tables">
                <div class="table-group-heading"><i class="fas fa-utensils me-2"></i>Normal Masalar</div>
                <div class="tables-grid" id="regular-tables-container">
                    <?php
                    // Query tables
                    $sql = "SELECT * FROM masa WHERE table_id NOT IN (11, 12, 13, 14, 15) ORDER BY table_id ASC";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $masa_id = $row["table_id"];
                            $masa_adi = $row["table_name"];
                            $durum = $row["durum"];
                            $opened_at = $row["opened_at"];
                            $masa_class = $durum == 0 ? "" : "busy";
                            $icon_class = $durum == 0 ? "" : "busy";
                            $icon = $durum == 0 ? "fa-utensils" : "fa-utensils";
                            
                            echo "<div class='table-card $masa_class' onclick='openTable($masa_id, \"$masa_adi\", $durum)' data-status='$durum' data-type='regular' data-opened='$opened_at'>";
                            echo "<i class='fas $icon table-icon $icon_class'></i>";
                            echo "<div class='table-name'>$masa_adi</div>";
                            if ($durum == 1) {
                                echo "<div class='table-badge'>Dolu</div>";
                                
                                // Add timer if opened_at is available
                                if ($opened_at) {
                                    $openedTime = new DateTime($opened_at);
                                    $currentTime = new DateTime();
                                    $interval = $currentTime->diff($openedTime);
                                    
                                    $timeText = '';
                                    if ($interval->h > 0) {
                                        $timeText = $interval->h . ' saat ' . $interval->i . ' dk.';
                                    } elseif ($interval->i > 0) {
                                        $timeText = $interval->i . ' dakika';
                                    } else {
                                        $timeText = 'Yeni açıldı';
                                    }
                                    
                                    echo "<div class='table-timer' data-time='$opened_at'>$timeText</div>";
                                }
                            }
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='alert alert-info'>Hiç masa bulunamadı.</div>";
                    }
                    ?>
                </div>
            </div>
            
            <!-- Takeaway Section -->
            <div class="table-group" id="takeaway-tables">
                <div class="table-group-heading"><i class="fas fa-shopping-bag me-2"></i>Paket Servis</div>
                <div class="tables-grid" id="takeaway-tables-container">
                    <?php
                    // Query takeaway tables
                    $sql = "SELECT * FROM masa WHERE table_id IN (11, 12, 13, 14, 15) ORDER BY table_id ASC";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $masa_id = $row["table_id"];
                            $masa_adi = $row["table_name"];
                            $durum = $row["durum"];
                            $opened_at = $row["opened_at"];
                            $masa_class = $durum == 0 ? "" : "busy";
                            $icon_class = $durum == 0 ? "" : "busy";
                            $icon = "fa-shopping-bag";
                            
                            echo "<div class='table-card $masa_class' onclick='openTable($masa_id, \"$masa_adi\", $durum)' data-status='$durum' data-type='takeaway' data-opened='$opened_at'>";
                            echo "<i class='fas $icon table-icon $icon_class'></i>";
                            echo "<div class='table-name'>$masa_adi</div>";
                            if ($durum == 1) {
                                echo "<div class='table-badge'>Hazırlanıyor</div>";
                                
                                // Add timer if opened_at is available
                                if ($opened_at) {
                                    $openedTime = new DateTime($opened_at);
                                    $currentTime = new DateTime();
                                    $interval = $currentTime->diff($openedTime);
                                    
                                    $timeText = '';
                                    if ($interval->h > 0) {
                                        $timeText = $interval->h . ' saat ' . $interval->i . ' dk.';
                                    } elseif ($interval->i > 0) {
                                        $timeText = $interval->i . ' dakika';
                                    } else {
                                        $timeText = 'Yeni açıldı';
                                    }
                                    
                                    echo "<div class='table-timer' data-time='$opened_at'>$timeText</div>";
                                }
                            }
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='alert alert-info'>Hiç paket servis masası bulunamadı.</div>";
                    }
                    ?>
                </div>
            </div>
            
            <!-- Bottom Row: Popular Products & Payment Methods -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="popular-products">
                        <h3 class="section-title mb-3"><i class="fas fa-star section-icon"></i> Popüler Ürünler</h3>
                        <?php if (!empty($popularProducts)): ?>
                            <?php foreach ($popularProducts as $index => $product): ?>
                                <div class="popular-product">
                                    <img src="https://ateslipilicler.com/uploads/products/<?= $product['product_image'] ?>" alt="<?= $product['product_name'] ?>" class="popular-product-image">
                                    <div class="popular-product-info">
                                        <div class="popular-product-name"><?= $product['product_name'] ?></div>
                                        <div class="popular-product-count"><?= $product['order_count'] ?> sipariş</div>
                                    </div>
                                    <div class="popular-product-rank"><?= $index + 1 ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">Henüz sipariş verilmemiş.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="payment-methods">
                        <h3 class="section-title mb-3"><i class="fas fa-wallet section-icon"></i> Ödeme Yöntemleri</h3>
                        <?php if (!empty($paymentMethods)): ?>
                            <?php foreach ($paymentMethods as $method): ?>
                                <div class="payment-method">
                                    <div class="payment-method-name">
                                        <div class="payment-method-icon <?= strtolower($method['odeme_yontemi']) == 'nakit' ? 'cash' : 'card' ?>">
                                            <i class="fas <?= strtolower($method['odeme_yontemi']) == 'nakit' ? 'fa-money-bill-wave' : 'fa-credit-card' ?>"></i>
                                        </div>
                                        <?= $method['odeme_yontemi'] ?>
                                    </div>
                                    <div class="payment-method-value"><?= number_format($method['total'], 2) ?>₺</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">Bugün henüz ödeme alınmamış.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Modal -->
    <div class="modal fade" id="tableModal" tabindex="-1" aria-labelledby="tableModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tableModalLabel"><i class="fas fa-utensils me-2"></i>Masa İşlemleri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-utensils fa-3x mb-3" id="modal-table-icon"></i>
                        <h4 id="modal-table-name">Masa 1</h4>
                        <div id="modal-table-status" class="badge bg-success mb-3">Boş</div>
                    </div>
                    <div class="d-grid gap-3">
                        <button class="btn btn-primary btn-lg" id="openTableBtn">
                            <i class="fas fa-door-open me-2"></i>Masayı Aç
                        </button>
                        <button class="btn btn-success btn-lg" id="viewOrderBtn">
                            <i class="fas fa-clipboard-list me-2"></i>Siparişleri Görüntüle
                        </button>
                        <button class="btn btn-danger btn-lg" id="closeTableBtn">
                            <i class="fas fa-door-closed me-2"></i>Masayı Kapat
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel"><i class="fas fa-cog me-2"></i>Sistem Ayarları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Ses Efektleri</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="soundEffectsToggle" <?= $soundEnabled ? 'checked' : '' ?>>
                            <label class="form-check-label" for="soundEffectsToggle">Ses efektlerini etkinleştir</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Otomatik Yenileme</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoRefreshToggle" checked>
                            <label class="form-check-label" for="autoRefreshToggle">Otomatik yenile</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="refreshIntervalSelect" class="form-label">Yenileme Sıklığı</label>
                        <select class="form-select" id="refreshIntervalSelect">
                            <option value="10" <?= $refreshInterval == 10 ? 'selected' : '' ?>>10 saniye</option>
                            <option value="30" <?= $refreshInterval == 30 ? 'selected' : '' ?>>30 saniye</option>
                            <option value="60" <?= $refreshInterval == 60 ? 'selected' : '' ?>>1 dakika</option>
                            <option value="120" <?= $refreshInterval == 120 ? 'selected' : '' ?>>2 dakika</option>
                            <option value="300" <?= $refreshInterval == 300 ? 'selected' : '' ?>>5 dakika</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Otomatik Yazdırma</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoPrintToggle" <?= $autoPrintEnabled ? 'checked' : '' ?>>
                            <label class="form-check-label" for="autoPrintToggle">Yazdırma kuyruğundaki işleri otomatik yazdır</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="defaultCategory" class="form-label">Varsayılan Kategori</label>
                        <select class="form-select" id="defaultCategory">
                            <option value="all">Tüm Masalar</option>
                            <option value="busy">Dolu Masalar</option>
                            <option value="free">Boş Masalar</option>
                            <option value="regular">Normal Masalar</option>
                            <option value="takeaway">Paket Servis</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" id="saveSettingsBtn">Kaydet</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Queue Modal -->
    <div class="modal fade print-queue-modal" id="printQueueModal" tabindex="-1" aria-labelledby="printQueueModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printQueueModalLabel"><i class="fas fa-print me-2"></i>Yazdırma Kuyruğu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Bekleyen İşler <span id="queue-count-badge" class="badge bg-primary">0</span></h6>
                        <div>
                            <button class="btn btn-sm btn-primary" id="refreshQueueBtn">
                                <i class="fas fa-sync-alt me-1"></i>Yenile
                            </button>
                            <button class="btn btn-sm btn-danger" id="clearQueueBtn">
                                <i class="fas fa-trash me-1"></i>Tümünü Temizle
                            </button>
                        </div>
                    </div>
                    
                    <div id="print-queue-list">
                        <div class="print-queue-empty">
                            <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                            <p>Yazdırma kuyruğunda bekleyen iş yok.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="form-check me-auto">
                        <input class="form-check-input" type="checkbox" id="modalAutoPrintToggle" <?= $autoPrintEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="modalAutoPrintToggle">Otomatik yazdırmayı etkinleştir</label>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Queue Notification -->
    <?php if ($pendingPrintJobs > 0): ?>
    <div class="print-queue-notification" id="print-queue-notification" onclick="openPrintQueue()">
        <div class="print-queue-icon">
            <i class="fas fa-print"></i>
        </div>
        <div class="print-queue-info">
            <div class="print-queue-title">Yazdırma Kuyruğu</div>
            <div class="print-queue-count"><?= $pendingPrintJobs ?> bekleyen iş</div>
        </div>
        <div class="print-queue-action">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap & jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- Toastify for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- Howler for sound effects -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js"></script>
    <!-- SweetAlert2 for improved alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Global variables
        let currentTableId = null;
        let currentTableName = '';
        let currentTableStatus = 0;
        let soundEnabled = <?= $soundEnabled ? 'true' : 'false' ?>;
        let autoRefreshEnabled = true;
        let autoRefreshInterval = <?= $refreshInterval ?>;
        let autoPrintEnabled = <?= $autoPrintEnabled ? 'true' : 'false' ?>;
        let autoRefreshTimer = null;
        let printCheckTimer = null;
        
        // Sound effects
        const sounds = {
            buttonClick: new Howl({ src: ['sounds/click.mp3'], volume: 0.5 }),
            notification: new Howl({ src: ['sounds/notification.mp3'], volume: 0.7 }),
            print: new Howl({ src: ['sounds/print.mp3'], volume: 0.5 }),
            error: new Howl({ src: ['sounds/error.mp3'], volume: 0.5 }),
            success: new Howl({ src: ['sounds/success.mp3'], volume: 0.5 })
        };
        
        // Document ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize components
            initComponents();
            
            // Start auto refresh
            startAutoRefresh();
            
            // Start print queue checker
            startPrintQueueChecker();
            
            // Load saved settings
            loadSettings();
            
            // Display welcome message
            Toastify({
                text: "POS Sistemi başlatıldı! Hoş geldiniz.",
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "linear-gradient(to right, #3a86ff, #00c6ff)",
                stopOnFocus: true
            }).showToast();
        });
        
        // Initialize components
        function initComponents() {
            // Initialize category filters
            document.querySelectorAll('.category-btn').forEach(button => {
                button.addEventListener('click', function() {
                    // Play sound
                    if (soundEnabled) sounds.buttonClick.play();
                    
                    // Update active state
                    document.querySelectorAll('.category-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    // Filter tables
                    const filter = this.getAttribute('data-filter');
                    filterTables(filter);
                });
            });
            
            // Event listeners for buttons
            document.getElementById('refresh-btn').addEventListener('click', function() {
                if (soundEnabled) sounds.buttonClick.play();
                window.location.reload();
            });
            
            document.getElementById('settings-btn').addEventListener('click', function() {
                if (soundEnabled) sounds.buttonClick.play();
                $('#settingsModal').modal('show');
            });
            
            document.getElementById('print-queue-btn').addEventListener('click', function() {
                if (soundEnabled) sounds.buttonClick.play();
                openPrintQueue();
            });
            
            document.getElementById('sound-toggle').addEventListener('click', toggleSound);
            
            document.getElementById('saveSettingsBtn').addEventListener('click', saveSettings);
            
            // Modal auto print toggle
            const modalAutoPrintToggle = document.getElementById('modalAutoPrintToggle');
            if (modalAutoPrintToggle) {
                modalAutoPrintToggle.addEventListener('change', function() {
                    autoPrintEnabled = this.checked;
                    
                    // Update setting in database
                    $.ajax({
                        url: 'save_setting.php',
                        method: 'POST',
                        data: {
                            setting_key: 'auto_print_enabled',
                            setting_value: autoPrintEnabled ? '1' : '0'
                        }
                    });
                    
                    // Update in settings modal too
                    document.getElementById('autoPrintToggle').checked = autoPrintEnabled;
                    
                    // Show notification
                    Toastify({
                        text: autoPrintEnabled ? "Otomatik yazdırma etkinleştirildi" : "Otomatik yazdırma devre dışı bırakıldı",
                        duration: 3000,
                        backgroundColor: autoPrintEnabled ? "#38b000" : "#d90429",
                    }).showToast();
                });
            }
            
            // Refresh queue button
            document.getElementById('refreshQueueBtn').addEventListener('click', function() {
                if (soundEnabled) sounds.buttonClick.play();
                loadPrintQueue();
            });
            
            // Clear queue button
            document.getElementById('clearQueueBtn').addEventListener('click', function() {
                if (soundEnabled) sounds.buttonClick.play();
                clearPrintQueue();
            });
            
            // Table modal buttons
            document.getElementById('openTableBtn').addEventListener('click', function() {
                startAdisyon(currentTableId, currentTableName);
            });
            
            document.getElementById('viewOrderBtn').addEventListener('click', function() {
                startAdisyon(currentTableId, currentTableName);
            });
            
            document.getElementById('closeTableBtn').addEventListener('click', function() {
                closeTable(currentTableId);
            });
        }
        
        // Toggle sound
        function toggleSound() {
            soundEnabled = !soundEnabled;
            const soundToggle = document.getElementById('sound-toggle');
            
            if (soundEnabled) {
                soundToggle.innerHTML = '<i class="fas fa-volume-up"></i>';
                soundToggle.classList.remove('off');
                sounds.buttonClick.play();
            } else {
                soundToggle.innerHTML = '<i class="fas fa-volume-mute"></i>';
                soundToggle.classList.add('off');
            }
            
            // Save to localStorage
            localStorage.setItem('pos_sound_enabled', soundEnabled);
            
            // Save to server
            $.ajax({
                url: 'save_setting.php',
                method: 'POST',
                data: {
                    setting_key: 'sound_enabled',
                    setting_value: soundEnabled ? '1' : '0'
                }
            });
            
            // Update settings modal checkbox
            document.getElementById('soundEffectsToggle').checked = soundEnabled;
            
            Toastify({
                text: soundEnabled ? "Ses efektleri açıldı" : "Ses efektleri kapatıldı",
                duration: 2000,
                gravity: "top",
                position: "right",
                backgroundColor: soundEnabled ? "#38b000" : "#d90429",
            }).showToast();
        }
        
        // Start auto refresh
        function startAutoRefresh() {
            // Clear existing timer
            if (autoRefreshTimer) {
                clearInterval(autoRefreshTimer);
            }
            
            // Only start if enabled
            if (autoRefreshEnabled) {
                autoRefreshTimer = setInterval(function() {
                    refreshTableStatus();
                }, autoRefreshInterval * 1000);
            }
        }
        
        // Start print queue checker
        function startPrintQueueChecker() {
            // Clear existing timer
            if (printCheckTimer) {
                clearInterval(printCheckTimer);
            }
            
            // Check every 30 seconds
            printCheckTimer = setInterval(function() {
                checkPrintQueue();
            }, 30000);
            
            // Also check immediately
            checkPrintQueue();
        }
        
        // Check print queue for new items
        function checkPrintQueue() {
            $.ajax({
                url: 'check_print_queue.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        // Update notification if new items
                        if (response.count > 0) {
                            // Update badge
                            document.getElementById('print-queue-btn').innerHTML = `
                                <i class="fas fa-print me-1"></i> Yazdırma Kuyruğu
                                <span class="badge-notification">${response.count}</span>
                            `;
                            
                            // Show notification if not already visible
                            if (!document.getElementById('print-queue-notification')) {
                                // Create notification
                                const notification = document.createElement('div');
                                notification.id = 'print-queue-notification';
                                notification.className = 'print-queue-notification';
                                notification.onclick = function() { openPrintQueue(); };
                                notification.innerHTML = `
                                    <div class="print-queue-icon">
                                        <i class="fas fa-print"></i>
                                    </div>
                                    <div class="print-queue-info">
                                        <div class="print-queue-title">Yazdırma Kuyruğu</div>
                                        <div class="print-queue-count">${response.count} bekleyen iş</div>
                                    </div>
                                    <div class="print-queue-action">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                `;
                                
                                document.body.appendChild(notification);
                                
                                // Play notification sound
                                if (soundEnabled) sounds.notification.play();
                            } else {
                                // Update existing notification
                                document.querySelector('.print-queue-count').textContent = response.count + ' bekleyen iş';
                            }
                            
                            // If auto print enabled, open queue modal
                            if (autoPrintEnabled && !document.getElementById('printQueueModal').classList.contains('show')) {
                                openPrintQueue();
                            }
                        } else {
                            // Remove notification if no items
                            document.getElementById('print-queue-btn').innerHTML = `
                                <i class="fas fa-print me-1"></i> Yazdırma Kuyruğu
                            `;
                            
                            const notification = document.getElementById('print-queue-notification');
                            if (notification) {
                                notification.remove();
                            }
                        }
                    }
                }
            });
        }
        
        // Open print queue modal
        function openPrintQueue() {
            $('#printQueueModal').modal('show');
            loadPrintQueue();
        }
        
        // Load print queue
        function loadPrintQueue() {
            $.ajax({
                url: 'check_print_queue.php',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        updatePrintQueueDisplay(response.print_queue, response.count);
                        
                        // If auto print enabled and there are items, print the first one
                        if (autoPrintEnabled && response.print_queue.length > 0) {
                            printReceipt(response.print_queue[0].id);
                        }
                    } else {
                        showToast('Kuyruk yüklenirken hata: ' + response.message, 'error');
                        if (soundEnabled) sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    if (soundEnabled) sounds.error.play();
                }
            });
        }
        
        // Update print queue display
        function updatePrintQueueDisplay(queue, count) {
            const container = document.getElementById('print-queue-list');
            const countBadge = document.getElementById('queue-count-badge');
            
            // Update count badge
            countBadge.textContent = count;
            
            // Clear queue button state
            document.getElementById('clearQueueBtn').disabled = count === 0;
            
            if (queue.length > 0) {
                let html = '';
                queue.forEach(function(item) {
                    const receiptData = JSON.parse(item.receipt_data);
                    const createdTime = new Date(item.created_at);
                    const now = new Date();
                    const diffMs = now - createdTime;
                    const diffMins = Math.floor(diffMs / 60000);
                    let timeAgo;
                    
                    if (diffMins > 0) {
                        timeAgo = diffMins + ' dakika önce';
                    } else {
                        timeAgo = 'Yeni eklendi';
                    }
                    
                    const receiptTypeText = item.receipt_type === 'full' ? 'Tam Fiş' : 
                                          (item.receipt_type === 'split' ? 'Bölünmüş Fiş' : 'Kişisel Fiş');
                    
                    html += `
                        <div class="print-queue-item">
                            <div class="print-queue-item-info">
                                <h6 class="mb-0">${item.masa_adi}</h6>
                                <p>${receiptTypeText} - ${receiptData.totalAmount} - ${timeAgo}</p>
                            </div>
                            <div class="print-queue-item-actions">
                                <button class="btn btn-sm btn-success" onclick="printReceipt(${item.id})">
                                    <i class="fas fa-print"></i> Yazdır
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="removeFromQueue(${item.id})">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="print-queue-empty">
                        <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                        <p>Yazdırma kuyruğunda bekleyen iş yok.</p>
                    </div>
                `;
            }
        }
        
        // Print receipt
        function printReceipt(queueId) {
            $.ajax({
                url: 'print_receipt.php',
                method: 'POST',
                data: { queue_id: queueId },
                success: function(response) {
                    if (response.success) {
                        if (soundEnabled) sounds.print.play();
                        
                        showToast('Fiş yazdırıldı: ' + response.masa_adi, 'success');
                        
                        // Reload queue
                        loadPrintQueue();
                        
                        // Attempt to send to system printer
                        const receiptData = JSON.parse(response.receipt_data);
                        
                        // Create a hidden iframe for printing
                        const iframe = document.createElement('iframe');
                        iframe.style.display = 'none';
                        document.body.appendChild(iframe);
                        
                        // Generate receipt HTML
                        let receiptHtml = generateReceiptHtml(receiptData, response.receipt_type);
                        
                        iframe.contentDocument.write(`
                            <html>
                                <head>
                                    <title>Print Receipt</title>
                                    <style>
                                        @media print {
                                            @page {
                                                size: 80mm auto;
                                                margin: 0;
                                            }
                                            body {
                                                margin: 0;
                                                font-family: 'Arial', sans-serif;
                                                font-size: 10pt;
                                            }
                                            .receipt-container {
                                                width: 75mm;
                                                padding: 5mm;
                                            }
                                            .receipt-header {
                                                text-align: center;
                                                margin-bottom: 5mm;
                                            }
                                            .receipt-title {
                                                font-size: 12pt;
                                                font-weight: bold;
                                            }
                                            .receipt-info {
                                                margin: 3mm 0;
                                                border-top: 1px dashed #000;
                                                border-bottom: 1px dashed #000;
                                                padding: 2mm 0;
                                            }
                                            .receipt-row {
                                                display: flex;
                                                justify-content: space-between;
                                                margin-bottom: 1mm;
                                            }
                                            .receipt-table {
                                                width: 100%;
                                                border-collapse: collapse;
                                                margin: 3mm 0;
                                            }
                                            .receipt-table th {
                                                text-align: left;
                                                border-bottom: 1px solid #000;
                                                padding-bottom: 1mm;
                                            }
                                            .receipt-table td {
                                                padding: 1mm 0;
                                            }
                                            .receipt-total {
                                                border-top: 1px solid #000;
                                                padding-top: 2mm;
                                                margin-top: 2mm;
                                                font-weight: bold;
                                            }
                                            .receipt-footer {
                                                text-align: center;
                                                margin-top: 5mm;
                                                font-size: 8pt;
                                                border-top: 1px dashed #000;
                                                padding-top: 2mm;
                                            }
                                        }
                                    </style>
                                </head>
                                <body>${receiptHtml}</body>
                            </html>
                        `);
                        
                        iframe.contentDocument.close();
                        
                        // Print after a short delay
                        setTimeout(function() {
                            iframe.contentWindow.print();
                            
                            // Remove iframe after printing
                            setTimeout(function() {
                                document.body.removeChild(iframe);
                            }, 1000);
                        }, 500);
                    } else {
                        showToast('Fiş yazdırma hatası: ' + response.message, 'error');
                        if (soundEnabled) sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    if (soundEnabled) sounds.error.play();
                }
            });
        }
        
        // Remove from queue
        function removeFromQueue(queueId) {
            $.ajax({
                url: 'remove_from_queue.php',
                method: 'POST',
                data: { queue_id: queueId },
                success: function(response) {
                    if (response.success) {
                        showToast('Fiş kuyruktan kaldırıldı', 'success');
                        
                        // Reload queue
                        loadPrintQueue();
                        
                        // Also update the notification
                        checkPrintQueue();
                    } else {
                        showToast('Fiş kaldırma hatası: ' + response.message, 'error');
                        if (soundEnabled) sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    if (soundEnabled) sounds.error.play();
                }
            });
        }
        
        // Clear print queue
        function clearPrintQueue() {
            $.ajax({
                url: 'clear_print_queue.php',
                method: 'POST',
                success: function(response) {
                    if (response.success) {
                        showToast('Yazdırma kuyruğu temizlendi', 'success');
                        
                        // Reload queue
                        loadPrintQueue();
                        
                        // Also update the notification
                        checkPrintQueue();
                    } else {
                        showToast('Kuyruk temizleme hatası: ' + response.message, 'error');
                        if (soundEnabled) sounds.error.play();
                    }
                },
                error: function() {
                    showToast('Sunucu ile iletişim hatası', 'error');
                    if (soundEnabled) sounds.error.play();
                }
            });
        }
        
    // Generate receipt HTML
function generateReceiptHtml(receiptData, receiptType) {
    // CSS Styles for the receipt
    const styles = `
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                margin: 0;
                font-family: 'Segoe UI', 'Roboto', sans-serif;
                font-size: 10pt;
                line-height: 1.2;
                color: #333;
                background-color: #fff;
            }
            .receipt-container {
                width: 75mm;
                padding: 5mm;
                position: relative;
                background: #fff;
            }
            .receipt-brand {
                text-align: center;
                margin-bottom: 5mm;
                position: relative;
            }
            .receipt-logo {
                font-size: 20pt;
                font-weight: bold;
                letter-spacing: 1px;
                color: #e63946;
                text-transform: uppercase;
                margin-bottom: 2mm;
            }
            .receipt-logo-image {
                margin-bottom: 2mm;
                display: flex;
                justify-content: center;
            }
            .receipt-logo-image img {
                max-width: 70mm;
                max-height: 40mm;
            }
            .receipt-slogan {
                font-style: italic;
                font-size: 9pt;
                color: #666;
                margin-bottom: 1mm;
            }
            .receipt-accent {
                height: 4px;
                background: linear-gradient(to right, #e63946, #f1faee);
                margin: 3mm 0;
                border-radius: 2px;
            }
            .receipt-info {
                margin: 4mm 0;
                padding: 3mm 0;
                border-top: 1px dashed #ccc;
                border-bottom: 1px dashed #ccc;
                background-color: #f9f9f9;
                border-radius: 2px;
            }
            .receipt-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 1.5mm;
                font-size: 9pt;
            }
            .receipt-label {
                font-weight: 600;
                color: #444;
            }
            .receipt-value {
                text-align: right;
            }
            .receipt-table {
                width: 100%;
                border-collapse: collapse;
                margin: 3mm 0;
            }
            .receipt-table th {
                text-align: left;
                border-bottom: 1px solid #ddd;
                padding: 2mm 1mm;
                font-size: 9pt;
                color: #555;
                text-transform: uppercase;
                font-weight: 600;
            }
            .receipt-table td {
                padding: 2mm 1mm;
                border-bottom: 1px dotted #eee;
                font-size: 9pt;
            }
            .receipt-item-name {
                font-weight: 400;
                 font-size: 6pt;
            }
            .receipt-item-price {
                color: #666;
            }
            .receipt-totals {
                margin: 3mm 0;
                background-color: #f9f9f9;
                padding: 2mm;
                border-radius: 2px;
            }
            .receipt-total {
                border-top: 2px solid #ddd;
                padding-top: 2mm;
                margin-top: 2mm;
                font-weight: bold;
                color: #e63946;
                font-size: 11pt;
            }
            .receipt-total .receipt-value {
                font-size: 12pt;
            }
            .receipt-footer {
                text-align: center;
                margin-top: 5mm;
                font-size: 8pt;
                color: #777;
                border-top: 1px dashed #ccc;
                padding-top: 3mm;
            }
            .receipt-special-box {
                text-align: center;
                padding: 3mm;
                border: 1px solid #ccc;
                margin: 4mm 0;
                background-color: #f9f9f9;
                border-radius: 3px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .receipt-special-title {
                font-size: 11pt;
                font-weight: bold;
                margin-bottom: 2mm;
                color: #333;
            }
            .receipt-special-amount {
                font-size: 14pt;
                font-weight: bold;
                margin: 3mm 0;
                color: #e63946;
            }
            .receipt-personal-title {
                color: #2a9d8f;
                border-bottom: 2px solid #2a9d8f;
                padding-bottom: 1mm;
            }
            .receipt-qr {
                text-align: center;
                margin-top: 3mm;
            }
            .receipt-qr-code {
                width: 20mm;
                height: 20mm;
                background-color: #f5f5f5;
                margin: 0 auto;
                border: 1px solid #ddd;
            }
            .receipt-qr-text {
                font-size: 7pt;
                color: #999;
                margin-top: 1mm;
            }
        }
    `;
    
    let html = `
    <html>
        <head>
            <title>Fiş</title>
            <style>${styles}</style>
        </head>
        <body>
            <div class="receipt-container">
                <!-- Header -->
                <div class="receipt-brand">
                    <!-- Logo görsel olarak -->
                    <div class="receipt-logo-image">
                        <img src="fislogo.svg" alt="Ateşli Piliçler Logo" style="max-width: 70mm; max-height: 40mm;">
                    </div>
                    <div class="receipt-slogan">Lezzetin Ateşli Hali</div>
                </div>
                
                <div class="receipt-accent"></div>
                
                <!-- Info -->
                <div class="receipt-info">
                    <div class="receipt-row">
                        <div class="receipt-label">Tarih:</div>
                        <div class="receipt-value">${receiptData.dateTime ? receiptData.dateTime.split(' ')[0] : ''}</div>
                    </div>
                    <div class="receipt-row">
                        <div class="receipt-label">Saat:</div>
                        <div class="receipt-value">${receiptData.dateTime ? receiptData.dateTime.split(' ')[1] : ''}</div>
                    </div>
                    <div class="receipt-row">
                        <div class="receipt-label">Fiş No:</div>
                        <div class="receipt-value">#${receiptData.receiptNumber}</div>
                    </div>
                    <div class="receipt-row">
                        <div class="receipt-label">Masa:</div>
                        <div class="receipt-value">${receiptData.tableName}</div>
                    </div>
                    
                </div>
    `;
    
    if (receiptType === 'full') {
        // Items
        html += `
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th align="center">Adet</th>
                        <th align="right">Fiyat</th>
                        <th align="right">Tutar</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        // Add items
        if (receiptData.items && receiptData.items.length > 0) {
            receiptData.items.forEach(function(item) {
                const price = parseFloat(item.price.replace("₺", ""));
                const quantity = parseInt(item.quantity);
                const total = price * quantity;
                
                html += `
                    <tr>
                        <td class="receipt-item-name">${item.name}</td>
                        <td align="center">${quantity}</td>
                        <td align="right" class="receipt-item-price">${price.toFixed(2)}₺</td>
                        <td align="right" class="receipt-item-total">${total.toFixed(2)}₺</td>
                    </tr>
                `;
            });
        }
        
        html += `
                </tbody>
            </table>
        `;
        
        // Totals
        html += `
            <div class="receipt-totals">
                <div class="receipt-row">
                    <div class="receipt-label">Ara Toplam:</div>
                    <div class="receipt-value">${receiptData.subtotal}</div>
                </div>
                <div class="receipt-row">
                    <div class="receipt-label">KDV (${receiptData.taxRate}%):</div>
                    <div class="receipt-value">${receiptData.taxAmount}</div>
                </div>
                <div class="receipt-row receipt-total">
                    <div class="receipt-label">TOPLAM:</div>
                    <div class="receipt-value">${receiptData.totalAmount}</div>
                </div>
            </div>
        `;
    } else if (receiptType === 'split') {
        // Split receipt
        html += `
            <div class="receipt-special-box">
                <div class="receipt-special-title">Hesap Bölüşümü</div>
                <p>Bu fiş, hesabın ${receiptData.peopleCount} kişi arasında eşit bölünmesi sonucunda düzenlenmiştir.</p>
                <div class="receipt-special-amount">
                    KİŞİ BAŞI: ${receiptData.perPersonAmount}
                </div>
            </div>
        `;
    } else if (receiptType === 'personal') {
        // Personal receipt
        html += `
            <div class="receipt-special-box">
                <div class="receipt-special-title receipt-personal-title">${receiptData.personName} İçin Fiş</div>
            </div>
            
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th align="center">Adet</th>
                        <th align="right">Fiyat</th>
                        <th align="right">Tutar</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        // Add items
        if (receiptData.items && receiptData.items.length > 0) {
            receiptData.items.forEach(function(item) {
                const price = parseFloat(item.price.replace("₺", ""));
                const quantity = parseInt(item.quantity);
                const total = price * quantity;
                
                html += `
                    <tr>
                        <td class="receipt-item-name">${item.name}</td>
                        <td align="center">${quantity}</td>
                        <td align="right" class="receipt-item-price">${price.toFixed(2)}₺</td>
                        <td align="right" class="receipt-item-total">${total.toFixed(2)}₺</td>
                    </tr>
                `;
            });
        }
        
        html += `
                </tbody>
            </table>
            
            <div class="receipt-totals">
                <div class="receipt-row receipt-total">
                    <div class="receipt-label">TOPLAM:</div>
                    <div class="receipt-value">${receiptData.totalAmount}</div>
                </div>
            </div>
        `;
    }
    
    // Footer
    html += `
        <div class="receipt-footer">
            <p>Teşekkür ederiz, yine bekleriz!</p>
            <p>Tel: 0532 548 31 35</p>
            
            <!-- QR Code - Sabit görsel olarak -->
            <div class="receipt-qr">
                <img src="qr1.svg" alt="Değerlendirme QR Kodu" style="width: 20mm; height: 20mm;">
                <div class="receipt-qr-text">Bizi değerlendirmek için QR kodu tarayınız</div>
            </div>
        </div>
    </div>
    </body>
    </html>
    `;
    
    return html;
}
        
        // Refresh table status
        function refreshTableStatus() {
            fetch('get_table_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateTableStatus(data.tables);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing table status:', error);
                });
        }
        
        // Update table status in the UI
        function updateTableStatus(tables) {
            tables.forEach(table => {
                const tableCard = document.querySelector(`.table-card[onclick*="${table.table_id}"]`);
                if (tableCard) {
                    const previousStatus = tableCard.getAttribute('data-status');
                    
                    // Update status attribute
                    tableCard.setAttribute('data-status', table.durum);
                    
                    // Update class and badge
                    if (table.durum == 1) {
                        tableCard.classList.add('busy');
                        
                        // Add badge if not exists
                        if (!tableCard.querySelector('.table-badge')) {
                            const badge = document.createElement('div');
                            badge.className = 'table-badge';
                            badge.textContent = tableCard.getAttribute('data-type') === 'takeaway' ? 'Hazırlanıyor' : 'Dolu';
                            tableCard.appendChild(badge);
                        }
                        
                        // Update icon
                        const icon = tableCard.querySelector('.table-icon');
                        icon.classList.add('busy');
                        
                        // Add or update timer
                        if (table.opened_at) {
                            const openedTime = new Date(table.opened_at);
                            const currentTime = new Date();
                            const interval = Math.floor((currentTime - openedTime) / 1000);
                            
                            let timeText = '';
                            if (interval >= 3600) {
                                const hours = Math.floor(interval / 3600);
                                const minutes = Math.floor((interval % 3600) / 60);
                                timeText = hours + ' saat ' + minutes + ' dk.';
                            } else if (interval >= 60) {
                                const minutes = Math.floor(interval / 60);
                                timeText = minutes + ' dakika';
                            } else {
                                timeText = 'Yeni açıldı';
                            }
                            
                            let timerElement = tableCard.querySelector('.table-timer');
                            if (!timerElement) {
                                timerElement = document.createElement('div');
                                timerElement.className = 'table-timer';
                                tableCard.appendChild(timerElement);
                            }
                            
                            timerElement.setAttribute('data-time', table.opened_at);
                            timerElement.textContent = timeText;
                        }
                    } else {
                        tableCard.classList.remove('busy');
                        
                        // Remove badge if exists
                        const badge = tableCard.querySelector('.table-badge');
                        if (badge) {
                            badge.remove();
                        }
                        
                        // Update icon
                        const icon = tableCard.querySelector('.table-icon');
                        icon.classList.remove('busy');
                        
                        // Remove timer if exists
                        const timerElement = tableCard.querySelector('.table-timer');
                        if (timerElement) {
                            timerElement.remove();
                        }
                    }
                    
                    // Notify if status changed
                    if (previousStatus !== table.durum.toString()) {
                        const statusText = table.durum == 1 ? 'dolu' : 'boş';
                        Toastify({
                            text: `Masa ${table.table_id} şu anda ${statusText}`,
                            duration: 3000,
                            gravity: "bottom",
                            position: "right",
                            backgroundColor: table.durum == 1 ? "#d90429" : "#38b000",
                        }).showToast();
                        
                        // Play sound
                        if (soundEnabled) {
                            if (table.durum == 1) {
                                sounds.notification.play();
                            } else {
                                sounds.success.play();
                            }
                        }
                    }
                }
            });
        }
        
        // Filter tables by category
        function filterTables(filter) {
            if (soundEnabled) sounds.buttonClick.play();
            
            const tables = document.querySelectorAll('.table-card');
            
            tables.forEach(table => {
                const status = table.getAttribute('data-status');
                const type = table.getAttribute('data-type');
                
                switch (filter) {
                    case 'all':
                        table.style.display = 'block';
                        break;
                    case 'busy':
                        table.style.display = status === '1' ? 'block' : 'none';
                        break;
                    case 'free':
                        table.style.display = status === '0' ? 'block' : 'none';
                        break;
                    case 'regular':
                        table.style.display = type === 'regular' ? 'block' : 'none';
                        break;
                    case 'takeaway':
                        table.style.display = type === 'takeaway' ? 'block' : 'none';
                        break;
                }
            });
            
            // Update group visibility
            updateGroupVisibility();
            
            // Save to localStorage
            localStorage.setItem('pos_default_category', filter);
        }
        
        // Update group visibility
        function updateGroupVisibility() {
            const regularGroup = document.getElementById('regular-tables');
            const takeawayGroup = document.getElementById('takeaway-tables');
            
            if (!regularGroup || !takeawayGroup) return;
            
            // Check if any tables are visible in each group
            const regularVisible = Array.from(regularGroup.querySelectorAll('.table-card')).some(table => {
                return table.style.display !== 'none';
            });
            
            const takeawayVisible = Array.from(takeawayGroup.querySelectorAll('.table-card')).some(table => {
                return table.style.display !== 'none';
            });
            
            // Show/hide groups
            regularGroup.style.display = regularVisible ? 'block' : 'none';
            takeawayGroup.style.display = takeawayVisible ? 'block' : 'none';
        }
        
        // Open table modal
        function openTable(tableId, tableName, status) {
            if (soundEnabled) sounds.buttonClick.play();
            
            // Set current table info
            currentTableId = tableId;
            currentTableName = tableName;
            currentTableStatus = status;
            
            // Update modal content
            const modalTitle = document.getElementById('tableModalLabel');
            const modalIcon = document.getElementById('modal-table-icon');
            const modalName = document.getElementById('modal-table-name');
            const modalStatus = document.getElementById('modal-table-status');
            const openBtn = document.getElementById('openTableBtn');
            const viewBtn = document.getElementById('viewOrderBtn');
            const closeBtn = document.getElementById('closeTableBtn');
            
            modalTitle.innerHTML = `<i class="fas fa-utensils me-2"></i>${tableName} İşlemleri`;
            modalName.textContent = tableName;
            
            // Set icon based on table type
            const isTakeaway = [11, 12, 13, 14, 15].includes(tableId);
            modalIcon.className = `fas fa-${isTakeaway ? 'shopping-bag' : 'utensils'} fa-3x mb-3`;
            
            // Set status badge and buttons visibility
            if (status == 1) {
                modalStatus.className = 'badge bg-danger mb-3';
                modalStatus.textContent = isTakeaway ? 'Hazırlanıyor' : 'Dolu';
                openBtn.style.display = 'none';
                viewBtn.style.display = 'block';
                closeBtn.style.display = 'block';
            } else {
                modalStatus.className = 'badge bg-success mb-3';
                modalStatus.textContent = 'Boş';
                openBtn.style.display = 'block';
                viewBtn.style.display = 'none';
                closeBtn.style.display = 'none';
            }
            
            // Show modal
            $('#tableModal').modal('show');
        }
        
        // Start adisyon
        function startAdisyon(tableId, tableName) {
            if (soundEnabled) sounds.buttonClick.play();
            
            // Hide modal
            $('#tableModal').modal('hide');
            
            // Update table status if not already open
            if (currentTableStatus == 0) {
                $.ajax({
                    url: "update_table_status.php",
                    method: "POST",
                    data: { masa_id: tableId },
                    success: function(response) {
                        console.log("Table status updated:", response);
                    },
                    error: function(xhr, status, error) {
                        console.error("Error updating table status:", error);
                        if (soundEnabled) sounds.error.play();
                    }
                });
            }
            
            // Redirect to POS menu
            setTimeout(function() {
                window.location.href = `posmenu.php?masa_id=${tableId}`;
            }, 300);
        }
        
        // Close table
        function closeTable(tableId) {
            if (soundEnabled) sounds.buttonClick.play();
            
            // Confirm before closing
            Swal.fire({
                title: 'Masayı Kapat',
                text: `${currentTableName} kapatılacak. Emin misiniz?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Evet, Kapat',
                cancelButtonText: 'İptal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Close modal
                    $('#tableModal').modal('hide');
                    
                    // Close table
                    $.ajax({
                        url: "masa_kapat.php",
                        method: "POST",
                        data: { masaId: tableId },
                        success: function(response) {
                            Toastify({
                                text: `${currentTableName} başarıyla kapatıldı`,
                                duration: 3000,
                                backgroundColor: "#38b000",
                            }).showToast();
                            
                            // Refresh after a short delay
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        },
                        error: function(xhr, status, error) {
                            console.error("Error closing table:", error);
                            if (soundEnabled) sounds.error.play();
                            
                            Toastify({
                                text: "Masa kapatılırken hata oluştu",
                                duration: 3000,
                                backgroundColor: "#d90429",
                            }).showToast();
                        }
                    });
                }
            });
        }
        
        // Load settings
        function loadSettings() {
            // Sound setting
            const savedSound = localStorage.getItem('pos_sound_enabled');
            if (savedSound !== null) {
                soundEnabled = savedSound === 'true';
                const soundToggle = document.getElementById('sound-toggle');
                
                if (!soundEnabled) {
                    soundToggle.innerHTML = '<i class="fas fa-volume-mute"></i>';
                    soundToggle.classList.add('off');
                }
            }
            
            // Refresh interval setting
            const savedRefreshInterval = localStorage.getItem('pos_refresh_interval');
            if (savedRefreshInterval !== null) {
                autoRefreshInterval = parseInt(savedRefreshInterval);
            }
            
            // Auto refresh setting
            const savedAutoRefresh = localStorage.getItem('pos_auto_refresh');
            if (savedAutoRefresh !== null) {
                autoRefreshEnabled = savedAutoRefresh === 'true';
            }
            
            // Update form controls
            document.getElementById('soundEffectsToggle').checked = soundEnabled;
            document.getElementById('autoRefreshToggle').checked = autoRefreshEnabled;
            document.getElementById('refreshIntervalSelect').value = autoRefreshInterval;
            
            // Default category setting
            const savedDefaultCategory = localStorage.getItem('pos_default_category');
            if (savedDefaultCategory) {
                document.getElementById('defaultCategory').value = savedDefaultCategory;
                
                // Apply default category filter
                const categoryBtn = document.querySelector(`.category-btn[data-filter="${savedDefaultCategory}"]`);
                if (categoryBtn) {
                    document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
                    categoryBtn.classList.add('active');
                    filterTables(savedDefaultCategory);
                }
            }
            
            // Update timers based on settings
            startAutoRefresh();
        }
        
        // Save settings
        function saveSettings() {
            if (soundEnabled) sounds.buttonClick.play();
            
            // Get form values
            const sound = document.getElementById('soundEffectsToggle').checked;
            const autoRefresh = document.getElementById('autoRefreshToggle').checked;
            const refreshInterval = parseInt(document.getElementById('refreshIntervalSelect').value);
            const autoPrint = document.getElementById('autoPrintToggle').checked;
            const defaultCategory = document.getElementById('defaultCategory').value;
            
            // Save to localStorage
            localStorage.setItem('pos_sound_enabled', sound);
            localStorage.setItem('pos_auto_refresh', autoRefresh);
            localStorage.setItem('pos_refresh_interval', refreshInterval);
            localStorage.setItem('pos_default_category', defaultCategory);
            
            // Update global variables
            soundEnabled = sound;
            autoRefreshEnabled = autoRefresh;
            autoRefreshInterval = refreshInterval;
            autoPrintEnabled = autoPrint;
            
            // Update sound toggle
            const soundToggle = document.getElementById('sound-toggle');
            if (soundEnabled) {
                soundToggle.innerHTML = '<i class="fas fa-volume-up"></i>';
                soundToggle.classList.remove('off');
            } else {
                soundToggle.innerHTML = '<i class="fas fa-volume-mute"></i>';
                soundToggle.classList.add('off');
            }
            
            // Update auto refresh
            startAutoRefresh();
            
            // Save settings to server
            $.ajax({
                url: 'save_setting.php',
                method: 'POST',
                data: {
                    setting_key: 'sound_enabled',
                    setting_value: soundEnabled ? '1' : '0'
                }
            });
            
            $.ajax({
                url: 'save_setting.php',
                method: 'POST',
                data: {
                    setting_key: 'refresh_interval',
                    setting_value: autoRefreshInterval.toString()
                }
            });
            
            $.ajax({
                url: 'save_setting.php',
                method: 'POST',
                data: {
                    setting_key: 'auto_print_enabled',
                    setting_value: autoPrintEnabled ? '1' : '0'
                }
            });
            
            // Update modal auto print toggle
            document.getElementById('modalAutoPrintToggle').checked = autoPrintEnabled;
            
            // Apply default category
            const categoryBtn = document.querySelector(`.category-btn[data-filter="${defaultCategory}"]`);
            if (categoryBtn) {
                document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
                categoryBtn.classList.add('active');
                filterTables(defaultCategory);
            }
            
            // Hide modal
            $('#settingsModal').modal('hide');
            
            // Show success message
            Toastify({
                text: "Ayarlar başarıyla kaydedildi",
                duration: 3000,
                backgroundColor: "#38b000",
            }).showToast();
        }
        
        // Show toast notification
        function showToast(message, type) {
            const colors = {
                success: '#38b000',
                error: '#d90429',
                warning: '#ffbe0b',
                info: '#3a86ff'
            };
            
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: colors[type] || colors.info,
            }).showToast();
        }
    </script>
</body>

</html>