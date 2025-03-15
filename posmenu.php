<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURITY', true);
if(!defined('SECURITY')) die('İzin Yok..!');
if (count($_POST) === 0) {
    $_SESSION['_csrf_token_admin'] = md5(time() . rand(0, 999999));
}
require_once '../../config.php';
require_once '../../function.php';
require_once '../includes/settings.php';
if(!isAuth())header('Location: ../login.php');
$defaultLang = $setting['setting_default_lang'];

// Database connection
$servername = "localhost";
$username = "ahmetak_qr";
$password = "kqVHnZ2Trm";
$dbname = "ahmetak_qr";

// Create MySQL connection
$db = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);

// Enable error mode
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get system settings
try {
    $settingsStmt = $db->prepare("SELECT * FROM system_settings");
    $settingsStmt->execute();
    $systemSettings = [];
    
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        $systemSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Set default values if settings don't exist
    $soundEnabled = isset($systemSettings['sound_enabled']) ? (bool)$systemSettings['sound_enabled'] : true;
    $companyName = isset($systemSettings['company_name']) ? $systemSettings['company_name'] : 'Ateşli Piliçler';
    $companyPhone = isset($systemSettings['company_phone']) ? $systemSettings['company_phone'] : '0532 548 31 35';
    $displayPopularProducts = isset($systemSettings['display_popular_products']) ? (bool)$systemSettings['display_popular_products'] : false;
    $supportWhatsapp = isset($systemSettings['support_whatsapp']) ? $systemSettings['support_whatsapp'] : '905325483135';
    
    // Check user role
    $userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'garson'; // Default to garson if not set
} catch (PDOException $e) {
    // Default values if error
    $soundEnabled = true;
    $companyName = 'Ateşli Piliçler';
    $companyPhone = '0532 548 31 35';
    $displayPopularProducts = false;
    $supportWhatsapp = '905325483135';
    $userRole = 'garson';
}

$category_sef = @$_GET['category_sef'];
if (isset($category_sef) && $category_sef != '' && $category_sef != 'all') {
    // Find selected category
    $selectedCategoryQ = $db->prepare('SELECT * FROM categories WHERE (JSON_EXTRACT(category_slug,"$.tr") LIKE ? || JSON_EXTRACT(category_slug,"$.en") LIKE ?)');
    $selectedCategoryQ->execute(["%" . $category_sef . "%", "%" . $category_sef . "%"]);
    $selectedCategory = $selectedCategoryQ->fetch(PDO::FETCH_ASSOC);

    if ($selectedCategory) {
        // List products in selected category
        $productsQ = $db->prepare('SELECT * FROM products LEFT JOIN categories ON products.product_category = categories.category_id WHERE products.product_is_active=? AND categories.category_id=? ORDER BY products.product_order ASC');
        $productsQ->execute([1, $selectedCategory['category_id']]);
        $products = $productsQ->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // List all products if no matching category
        $productsQ = $db->prepare('SELECT * FROM products LEFT JOIN categories ON products.product_category = categories.category_id WHERE products.product_is_active=? ORDER BY products.product_order ASC');
        $productsQ->execute([1]);
        $products = $productsQ->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // List all products
    $productsQ = $db->prepare('SELECT * FROM products LEFT JOIN categories ON products.product_category = categories.category_id WHERE products.product_is_active=? ORDER BY products.product_order ASC');
    $productsQ->execute([1]);
    $products = $productsQ->fetchAll(PDO::FETCH_ASSOC);
}

// List active and passive categories
$categoriesQ = $db->prepare('SELECT * FROM categories WHERE category_is_active=? OR category_is_active=? ORDER BY category_order ASC');
$categoriesQ->execute([1, 2]);
$categories = $categoriesQ->fetchAll(PDO::FETCH_ASSOC);

// Define site_upload_url function
function sites_upload_url() {
    return 'https://ateslipilicler.com/uploads';
}

// Get table ID
$masaId = isset($_GET['masa_id']) ? $_GET['masa_id'] : '';

try {
    // Get orders by table ID
    $stmt = $db->prepare("SELECT * FROM adisyon WHERE masa_id = ?");
    $stmt->execute([$masaId]);
    $adisyonlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Show error message
    echo "Hata: " . $e->getMessage();
}

// Calculate total amount
$totalAmount = 0;
foreach ($adisyonlar as $adisyon) {
    $totalAmount += $adisyon['product_price'] * $adisyon['quantity'];
}

// Get most popular products if setting is enabled
$popularProductsInfo = [];
if ($displayPopularProducts) {
    $popularProductsQ = $db->prepare('SELECT product_id, COUNT(*) as order_count FROM adisyon GROUP BY product_id ORDER BY order_count DESC LIMIT 5');
    $popularProductsQ->execute();
    $popularProducts = $popularProductsQ->fetchAll(PDO::FETCH_ASSOC);

    $popularProductIds = array_column($popularProducts, 'product_id');
    
    if (!empty($popularProductIds)) {
        $placeholders = implode(',', array_fill(0, count($popularProductIds), '?'));
        $popularProductsInfoQ = $db->prepare("SELECT * FROM products WHERE product_id IN ($placeholders)");
        $popularProductsInfoQ->execute($popularProductIds);
        $popularProductsInfo = $popularProductsInfoQ->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get table info and opened time
try {
    $tableInfoStmt = $db->prepare("SELECT * FROM masa WHERE table_id = ?");
    $tableInfoStmt->execute([$masaId]);
    $tableInfo = $tableInfoStmt->fetch(PDO::FETCH_ASSOC);
    
    $tableOpenedTime = '';
    if ($tableInfo && $tableInfo['opened_at']) {
        $openedTime = new DateTime($tableInfo['opened_at']);
        $currentTime = new DateTime();
        $interval = $currentTime->diff($openedTime);
        
        if ($interval->h > 0) {
            $tableOpenedTime = $interval->h . ' saat ' . $interval->i . ' dk.';
        } elseif ($interval->i > 0) {
            $tableOpenedTime = $interval->i . ' dakika';
        } else {
            $tableOpenedTime = 'Yeni açıldı';
        }
    }
} catch (PDOException $e) {
    $tableOpenedTime = '';
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pro POS Menu - <?= $companyName ?></title>
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
    <!-- SweetAlert2 for improved alerts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="pos.css">
    <style>
        /* Adisyon fişi ve hesap bölme ekranı için ek stil düzenlemeleri */
        .sidebar {
            width: 350px; /* Genişletilmiş adisyon paneli */
            min-width: 350px;
        }
        
        .order-summary {
            max-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .summary-body {
            flex-grow: 1;
            overflow-y: auto;
            max-height: calc(100vh - 250px);
        }
        
        .guest-tracking {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .guest-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .guest-card.active {
            border-color: #3a86ff;
            background: #e9effd;
        }
        
        .guest-card.paid {
            border-color: #2ecc71;
            background: #e8f8f5;
        }
        
        .guest-icon {
            font-size: 24px;
            margin-bottom: 5px;
            color: #3a86ff;
        }
        
        .guest-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .guest-total {
            font-weight: 600;
            color: #2ecc71;
        }
        
        .split-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .split-option {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .split-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .split-option.selected {
            border-color: #3a86ff;
            background: #e9effd;
        }
        
        .split-icon {
            font-size: 24px;
            margin-bottom: 10px;
            color: #3a86ff;
        }
        
        .split-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .split-desc {
            font-size: 14px;
            color: #6c757d;
        }
        
        .split-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .person-select {
            padding: 5px;
            border-radius: 5px;
            border: 1px solid #ced4da;
        }
        
        .person-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .person-dropdown-toggle {
            background: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 5px;
            padding: 5px 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .person-dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border: 1px solid #ced4da;
            border-radius: 5px;
            width: 150px;
            z-index: 1000;
            display: none;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .person-dropdown-menu.show {
            display: block;
        }
        
        .person-dropdown-item {
            padding: 8px 15px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .person-dropdown-item:hover {
            background: #f1f1f1;
        }
        
        .payment-buttons button {
            margin: 0 2px;
        }
        
        .split-item .person-label {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            background-color: #e9effd;
            color: #3a86ff;
            font-weight: 500;
            margin-left: 5px;
            font-size: 0.8rem;
        }
        
        /* Cihaz uyumluluk için responsive düzenlemeler */
        @media (max-width: 992px) {
            .wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                min-width: auto;
                margin-bottom: 20px;
            }
            
            .summary-body {
                max-height: 300px;
            }
            
            .header .actions {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .payment-actions {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 5px;
            }
            
            .payment-btn {
                padding: 12px 5px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 576px) {
            .guest-tracking {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            }
            
            .guest-card {
                padding: 10px;
            }
            
            .split-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Sol Kenar Çubuğu - Sipariş Özeti -->
        <div class="sidebar">
            <div class="order-summary">
                <div class="summary-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-receipt me-2"></i>Sipariş Özeti</span>
                    <button class="btn btn-sm btn-danger" onclick="POS.orders.clearOrder(<?= $masaId ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="summary-body" id="order-list-container">
                    <!-- Sipariş öğeleri dinamik olarak burada oluşturulacak -->
                    <?php foreach ($adisyonlar as $adisyon) : ?>
                        <?php
                        // Ürün bilgilerini veritabanından al
                        $productId = $adisyon['product_id'];
                        $productInfoQ = $db->prepare('SELECT * FROM products WHERE product_id = ?');
                        $productInfoQ->execute([$productId]);
                        $productInfo = $productInfoQ->fetch(PDO::FETCH_ASSOC);
                        $productName = isset($productInfo['product_name']) ? json_decode($productInfo['product_name'], true)['tr'] : '';
                        ?>
                        <div class="summary-item" data-product-id="<?php echo $productId; ?>" data-person="">
                            <div class="item-details">
                                <div class="item-name"><?php echo $productName; ?></div>
                                <div class="item-price"><?php echo number_format($adisyon['product_price'], 2); ?>₺</div>
                            </div>
                            <div class="item-quantity">
                                <button class="quantity-btn" onclick="POS.orders.updateQuantity(<?php echo $productId; ?>, 'decrease', <?php echo $masaId; ?>)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="quantity-value"><?php echo $adisyon['quantity']; ?></span>
                                <button class="quantity-btn" onclick="POS.orders.updateQuantity(<?php echo $productId; ?>, 'increase', <?php echo $masaId; ?>)">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <div class="person-dropdown ms-2">
                                    <button class="person-dropdown-toggle" onclick="togglePersonDropdown(this)">
                                        <i class="fas fa-user"></i>
                                        <i class="fas fa-caret-down"></i>
                                    </button>
                                    <div class="person-dropdown-menu">
                                        <div class="person-dropdown-item" onclick="assignPerson(<?php echo $productId; ?>, '')">Ortak</div>
                                        <div class="person-dropdown-item" onclick="assignPerson(<?php echo $productId; ?>, 'person1')">Kişi 1</div>
                                        <div class="person-dropdown-item" onclick="assignPerson(<?php echo $productId; ?>, 'person2')">Kişi 2</div>
                                        <div class="person-dropdown-item" onclick="assignPerson(<?php echo $productId; ?>, 'person3')">Kişi 3</div>
                                        <div class="person-dropdown-item" onclick="assignPerson(<?php echo $productId; ?>, 'person4')">Kişi 4</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="summary-footer">
                    <div class="total-row">
                        <span class="total-label">Ara Toplam:</span>
                        <span class="total-value" id="subtotal">0.00₺</span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">KDV (8%):</span>
                        <span class="total-value" id="tax">0.00₺</span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Toplam:</span>
                        <span class="total-value" id="total-price">0.00₺</span>
                    </div>
                    <div class="payment-actions">
                        <button class="payment-btn cash" onclick="POS.payment.odemeYap(<?php echo $masaId; ?>, 'Nakit')">
                            <i class="fas fa-money-bill-wave payment-icon"></i>
                            <span class="payment-text">NAKİT</span>
                        </button>
                        <button class="payment-btn card" onclick="POS.payment.odemeYap(<?php echo $masaId; ?>, 'Kredi')">
                            <i class="fas fa-credit-card payment-icon"></i>
                            <span class="payment-text">KART</span>
                        </button>
                        <?php if ($userRole == 'kasa'): ?>
                        <button class="payment-btn print" onclick="POS.payment.fisYazdir(<?php echo $masaId; ?>, '<?php echo date('Y-m-d H:i:s'); ?>')">
                            <i class="fas fa-print payment-icon"></i>
                            <span class="payment-text">YAZDIR</span>
                        </button>
                        <?php else: ?>
                        <button class="payment-btn print" onclick="POS.payment.fisGonder(<?php echo $masaId; ?>, '<?php echo date('Y-m-d H:i:s'); ?>')">
                            <i class="fas fa-paper-plane payment-icon"></i>
                            <span class="payment-text">KASAYA GÖNDER</span>
                        </button>
                        <?php endif; ?>
                        <button class="payment-btn split" onclick="POS.payment.showSplitBillModal()">
                            <i class="fas fa-users payment-icon"></i>
                            <span class="payment-text">HESAP BÖL</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ana İçerik -->
        <div class="content">
            <!-- Başlık -->
            <div class="header">
                <div class="table-info">
                    <i class="fas fa-utensils fa-lg"></i>
                    <h2>Masa <?php echo $masaId; ?></h2>
                    <span class="table-badge">Aktif Sipariş</span>
                    <?php if ($tableOpenedTime): ?>
                    <span class="badge bg-info ms-2"><?= $tableOpenedTime ?></span>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <div class="form-check form-switch me-3 d-flex align-items-center">
                        <input class="form-check-input me-2" type="checkbox" id="soundToggle" <?= $soundEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="soundToggle">Ses</label>
                    </div>
                    <button class="btn btn-primary" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-1"></i> Yenile
                    </button>
                    <button class="btn btn-danger" onclick="returnToMasaSecimi()">
                        <i class="fas fa-arrow-left me-1"></i> Masa Seçimi
                    </button>
                </div>
            </div>

            <!-- Arama Çubuğu -->
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Ürün ara..." id="product-search">
            </div>

            <!-- Popüler Ürünler -->
            <?php if ($displayPopularProducts && !empty($popularProductsInfo)): ?>
            <div class="popular-products">
                <h3 class="section-title mb-3"><i class="fas fa-star section-icon"></i> Popüler Ürünler</h3>
                <div class="popular-products-grid">
                    <?php foreach ($popularProductsInfo as $product): ?>
                        <?php
                        $productName = json_decode($product['product_name'], true)['tr'] ?? '';
                        ?>
                        <div class="popular-product" onclick="POS.orders.addOrder(<?= $product['product_id'] ?>, '<?= $productName ?>', <?= $product['product_price'] ?>, <?= $masaId ?>)">
                            <img src="<?= sites_upload_url() . '/products/' . $product['product_image'] ?>" alt="<?= $productName ?>">
                            <div class="popular-product-name"><?= $productName ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Kategoriler -->
            <div class="category-list">
                <a href="javascript:void(0)" onclick="selectCategory('all')" class="category-item <?= (!isset($category_sef) || $category_sef == 'all') ? 'active' : '' ?>">
                    <i class="fas fa-border-all me-2"></i>Tümü
                </a>
                <?php foreach ($categories as $category) : ?>
                    <?php
                    $categoryNames = json_decode($category['category_name'], true);
                    $categoryName = $categoryNames['tr'];
                    $categorySlug = json_decode($category['category_slug'], true)['tr'];
                    ?>
                    <a href="javascript:void(0)" onclick="selectCategory('<?= $categorySlug ?>')" class="category-item <?= (isset($category_sef) && $category_sef == $categorySlug) ? 'active' : '' ?>">
                        <?= $categoryName ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Ürünler Izgara -->
            <div class="products-grid" id="products-container">
                <?php foreach ($products as $product) : ?>
                    <?php
                    $productName = json_decode($product['product_name'], true)['tr'] ?? '';
                    ?>
                    <div class="product-card" onclick="POS.orders.addOrder(<?= $product['product_id'] ?>, '<?= $productName ?>', <?= $product['product_price'] ?>, <?= $masaId ?>)">
                        <img src="<?= sites_upload_url() . '/products/' . $product['product_image'] ?>" alt="<?= $productName ?>" class="product-image">
                        <div class="product-details">
                            <div class="product-name"><?= strtoupper(trim($productName)) ?></div>
                            <div class="product-price"><?= number_format($product['product_price'], 2) ?>₺</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- WhatsApp Destek Butonu -->
    <a href="https://wa.me/<?= $supportWhatsapp ?>" target="_blank" class="whatsapp-support">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Hesap Bölme Modalı - Genişletilmiş ve İyileştirilmiş -->
    <div class="modal fade" id="splitBillModal" tabindex="-1" aria-labelledby="splitBillModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="splitBillModalLabel"><i class="fas fa-users me-2"></i>Hesap Bölme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="split-options">
                        <div class="split-option" data-split-type="even" onclick="POS.payment.selectSplitOption('even')">
                            <div class="split-icon"><i class="fas fa-equals"></i></div>
                            <div class="split-title">Eşit Bölüşüm</div>
                            <div class="split-desc">Toplam hesabı eşit olarak böl</div>
                        </div>
                        <div class="split-option" data-split-type="individual" onclick="POS.payment.selectSplitOption('individual')">
                            <div class="split-icon"><i class="fas fa-user-check"></i></div>
                            <div class="split-title">Kişisel Ödeme</div>
                            <div class="split-desc">Herkes kendi yediğini ödesin</div>
                        </div>
                    </div>

                    <!-- Eşit Bölüşüm Formu -->
                    <div id="even-split-form" style="display: none;">
                        <div class="card">
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label for="split-people-count" class="form-label">Kişi Sayısı</label>
                                    <input type="number" class="form-control" id="split-people-count" min="2" value="2">
                                </div>
                                <button class="btn btn-primary" id="calculate-split-btn" onclick="POS.payment.calculateEvenSplit()">Hesapla</button>
                                <div id="even-split-result" class="mt-3"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Kişisel Bölüşüm Formu - Genişletilmiş ve İyileştirilmiş -->
                    <div id="individual-split-form" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Ürünleri kişilere atamak için önce kişiyi sonra ürünü seçin.
                        </div>
                        
                        <div class="guest-tracking" id="guest-tracking">
                            <div class="guest-card active" data-person="" onclick="selectGuestForAssignment('')">
                                <i class="fas fa-users guest-icon"></i>
                                <div class="guest-name">Ortak</div>
                                <div class="guest-total" id="total-shared">0.00₺</div>
                                <div class="payment-buttons mt-2" style="display: none;">
                                    <button class="btn btn-sm btn-success" onclick="payForPerson('', 'Nakit')">Nakit</button>
                                    <button class="btn btn-sm btn-primary" onclick="payForPerson('', 'Kredi')">Kart</button>
                                </div>
                            </div>
                            <div class="guest-card" data-person="person1" onclick="selectGuestForAssignment('person1')">
                                <i class="fas fa-user guest-icon"></i>
                                <div class="guest-name">Kişi 1</div>
                                <div class="guest-total" id="total-person1">0.00₺</div>
                                <div class="payment-buttons mt-2" style="display: none;">
                                    <button class="btn btn-sm btn-success" onclick="payForPerson('person1', 'Nakit')">Nakit</button>
                                    <button class="btn btn-sm btn-primary" onclick="payForPerson('person1', 'Kredi')">Kart</button>
                                </div>
                            </div>
                            <div class="guest-card" data-person="person2" onclick="selectGuestForAssignment('person2')">
                                <i class="fas fa-user guest-icon"></i>
                                <div class="guest-name">Kişi 2</div>
                                <div class="guest-total" id="total-person2">0.00₺</div>
                                <div class="payment-buttons mt-2" style="display: none;">
                                    <button class="btn btn-sm btn-success" onclick="payForPerson('person2', 'Nakit')">Nakit</button>
                                    <button class="btn btn-sm btn-primary" onclick="payForPerson('person2', 'Kredi')">Kart</button>
                                </div>
                            </div>
                            <div class="guest-card" data-person="person3" onclick="selectGuestForAssignment('person3')">
                                <i class="fas fa-user guest-icon"></i>
                                <div class="guest-name">Kişi 3</div>
                                <div class="guest-total" id="total-person3">0.00₺</div>
                                <div class="payment-buttons mt-2" style="display: none;">
                                    <button class="btn btn-sm btn-success" onclick="payForPerson('person3', 'Nakit')">Nakit</button>
                                    <button class="btn btn-sm btn-primary" onclick="payForPerson('person3', 'Kredi')">Kart</button>
                                </div>
                            </div>
                            <div class="guest-card" data-person="person4" onclick="selectGuestForAssignment('person4')">
                                <i class="fas fa-user guest-icon"></i>
                                <div class="guest-name">Kişi 4</div>
                                <div class="guest-total" id="total-person4">0.00₺</div>
                                <div class="payment-buttons mt-2" style="display: none;">
                                    <button class="btn btn-sm btn-success" onclick="payForPerson('person4', 'Nakit')">Nakit</button>
                                    <button class="btn btn-sm btn-primary" onclick="payForPerson('person4', 'Kredi')">Kart</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="individual-split-container mt-4">
                            <h6 class="mb-3">Siparişler</h6>
                            <!-- Kişilere atanacak ürünler burada listelenecek -->
                            <?php foreach ($adisyonlar as $adisyon) : ?>
                                <?php
                                $productId = $adisyon['product_id'];
                                $productInfoQ = $db->prepare('SELECT * FROM products WHERE product_id = ?');
                                $productInfoQ->execute([$productId]);
                                $productInfo = $productInfoQ->fetch(PDO::FETCH_ASSOC);
                                $productName = isset($productInfo['product_name']) ? json_decode($productInfo['product_name'], true)['tr'] : '';
                                ?>
                                <div class="split-item" data-product-id="<?php echo $productId; ?>">
                                    <div class="split-item-details">
                                        <div class="split-item-name"><?php echo $productName; ?> x<?php echo $adisyon['quantity']; ?></div>
                                        <div class="split-item-price"><?php echo number_format($adisyon['product_price'] * $adisyon['quantity'], 2); ?>₺</div>
                                    </div>
                                    <select class="person-select" onchange="assignPersonFromSelect(<?php echo $productId; ?>, this.value)">
                                        <option value="">Ortak</option>
                                        <option value="person1">Kişi 1</option>
                                        <option value="person2">Kişi 2</option>
                                        <option value="person3">Kişi 3</option>
                                        <option value="person4">Kişi 4</option>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="individual-split-result" class="mt-3"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary" id="split-print-receipts" onclick="printSplitReceipts()">Fişleri Yazdır</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ödeme Onay Modalı -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="confirmModalLabel"><i class="fas fa-check-circle me-2"></i>Ödeme Alındı</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="lead text-center mb-3">Ödeme başarıyla alındı.</p>
                    <p class="text-center">Masa kapatılsın mı?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hayır, Açık Kalsın</button>
                    <button type="button" class="btn btn-success" id="confirmPaymentBtn">Evet, Masayı Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Gizli Input Alanları -->
    <input type="hidden" id="masa_id" value="<?= $masaId ?>">
    <input type="hidden" id="user_role" value="<?= $userRole ?>">
    <input type="hidden" id="selected_guest" value="">

    <!-- Bootstrap ve jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- Toastify bildirimler için -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- Ses efektleri için Howler -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js"></script>
    <!-- Özel JS -->
    <script src="pos.js"></script>

    <script>
        // Sayfa yüklendiğinde ses ayarını başlat
        document.addEventListener('DOMContentLoaded', function() {


// Ödeme onay modalı
const confirmPaymentBtn = document.getElementById('confirmPaymentBtn');
if (confirmPaymentBtn) {
    confirmPaymentBtn.addEventListener('click', function() {
        const masaId = document.getElementById('masa_id').value;
        console.log("Masa kapatma butonu tıklandı, Masa ID:", masaId);
        
        // Modalı kapat
        $('#confirmModal').modal('hide');
        
        // Masayı kapat - doğrudan AJAX çağrısı ile
        $.ajax({
            url: 'masa_kapat.php',
            method: 'POST',
            data: { masaId: masaId },
            dataType: 'json',
            success: function(response) {
                console.log("Masa kapatma yanıtı:", response);
                
                Toastify({
                    text: "Masa başarıyla kapatıldı",
                    duration: 3000,
                    style: { background: "#2ecc71" }
                }).showToast();
                
                // Kısa bir gecikmeden sonra ana sayfaya yönlendir
                setTimeout(function() {
                    window.location.href = "index.php";
                }, 1000);
            },
            error: function(xhr, status, error) {
                console.error("Masa kapatma hatası:", error);
                console.error("XHR:", xhr);
                
                Toastify({
                    text: "Masa kapatılırken bir hata oluştu",
                    duration: 3000,
                    style: { background: "#e74c3c" }
                }).showToast();
            }
        });
    });
}


            const soundToggle = document.getElementById('soundToggle');
            if (soundToggle) {
                soundToggle.addEventListener('change', function() {
                    POS.config.soundEnabled = this.checked;
                    localStorage.setItem('pos_sound_enabled', this.checked);
                    
                    // Sunucuya kaydet
                    $.ajax({
                        url: 'save_setting.php',
                        method: 'POST',
                        data: {
                            setting_key: 'sound_enabled',
                            setting_value: this.checked ? '1' : '0'
                        }
                    });
                });
            }

            // Kişi bazlı toplam hesaplamaları başlat
            updatePersonTotals();
            
            // Sipariş toplamını güncelle
            POS.orders.updateTotalPrice();
        });

        // Kategori seçimi
        function selectCategory(categorySlug) {
            POS.sounds.play('buttonClick');
            
            // Aktif sınıfı güncelle
            document.querySelectorAll('.category-item').forEach(item => {
                if (item.getAttribute('onclick').includes(categorySlug)) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
            
            // Yükleniyor durumunu göster
            const productsContainer = document.getElementById('products-container');
            productsContainer.innerHTML = '';
            
            // İskelet ürün kartları oluştur
            for (let i = 0; i < 8; i++) {
                productsContainer.innerHTML += `
                    <div class="skeleton-product">
                        <div class="skeleton skeleton-image"></div>
                        <div class="skeleton-details">
                            <div class="skeleton skeleton-name"></div>
                            <div class="skeleton skeleton-price"></div>
                        </div>
                    </div>
                `;
            }
            
            // Kategoriye göre ürünleri yükle
            $.ajax({
                url: 'get_products.php',
                method: 'GET',
                data: { category_sef: categorySlug, masa_id: <?= $masaId ?> },
                success: function(response) {
                    // Yükleme animasyonu için kısa bir gecikme
                    setTimeout(() => {
                        productsContainer.innerHTML = response;
                    }, 300);
                },
                error: function(xhr, status, error) {
                    console.error("Ürünler yüklenirken hata:", error);
                    POS.sounds.play('error');
                    
                    Toastify({
                        text: "Ürünler yüklenirken hata oluştu",
                        duration: 3000,
                        backgroundColor: "#d90429",
                    }).showToast();
                    
                    productsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Ürünler yüklenirken bir hata oluştu. Lütfen tekrar deneyin.
                        </div>
                    `;
                }
            });
        }

        // Kişi açılır menüsünü aç/kapat
        function togglePersonDropdown(button) {
            POS.sounds.play('buttonClick');
            
            const dropdown = button.nextElementSibling;
            const allDropdowns = document.querySelectorAll('.person-dropdown-menu');
            
            // Diğer tüm açılır menüleri kapat
            allDropdowns.forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            
            // Bu açılır menüyü aç/kapat
            dropdown.classList.toggle('show');
            
            // Başka bir yere tıklandığında açılır menüyü kapat
            document.addEventListener('click', function closeDropdowns(e) {
                if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                    document.removeEventListener('click', closeDropdowns);
                }
            });
        }

        // Bir ürünü kişiye ata
        function assignPerson(productId, person) {
            POS.sounds.play('buttonClick');
            
            const item = document.querySelector(`.summary-item[data-product-id="${productId}"]`);
            if (item) {
                // Kişiyi ata
                item.setAttribute('data-person', person);
                
                // Kişi etiketi ekle veya güncelle
                let personLabel = item.querySelector('.person-label');
                if (!personLabel) {
                    personLabel = document.createElement('span');
                    personLabel.className = 'person-label';
                    item.querySelector('.item-name').appendChild(personLabel);
                }
                
                const personName = person === '' ? 'Ortak' : 'Kişi ' + person.charAt(person.length - 1);
                personLabel.textContent = ' - ' + personName;
                
                // Bölme sekmesinde de varsa güncelle
                const splitItem = document.querySelector(`.split-item[data-product-id="${productId}"] .person-select`);
                if (splitItem) {
                    splitItem.value = person;
                }
                
                updatePersonTotals();
                
                // Açılır menüyü kapat
                const dropdown = item.querySelector('.person-dropdown-menu');
                if (dropdown) {
                    dropdown.classList.remove('show');
                }
                
                // Bildirim göster
                Toastify({
                    text: "Ürün " + personName + " olarak atandı",
                    duration: 3000,
                    backgroundColor: "#3a86ff",
                }).showToast();
            }
        }

        // Seçim menüsünden kişi ata (bölme sekmesinde)
        function assignPersonFromSelect(productId, person) {
            POS.sounds.play('buttonClick');
            
            // Her iki yerde de güncelle
            const item = document.querySelector(`.summary-item[data-product-id="${productId}"]`);
            if (item) {
                item.setAttribute('data-person', person);
                
                // Kişi etiketi ekle veya güncelle
                let personLabel = item.querySelector('.person-label');
                if (!personLabel) {
                    personLabel = document.createElement('span');
                    personLabel.className = 'person-label';
                    item.querySelector('.item-name').appendChild(personLabel);
                }
                
                const personName = person === '' ? 'Ortak' : 'Kişi ' + person.charAt(person.length - 1);
                personLabel.textContent = ' - ' + personName;
            }
            
            updatePersonTotals();
        }
        
        // Atama için bir misafir seç
        function selectGuestForAssignment(person) {
            POS.sounds.play('buttonClick');
            
            // Tüm kartlardan aktif sınıfı kaldır
            document.querySelectorAll('.guest-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Seçilen karta aktif sınıfı ekle
            const selectedCard = document.querySelector(`.guest-card[data-person="${person}"]`);
            if (selectedCard) {
                selectedCard.classList.add('active');
            }
            
            // Seçilen kişiyi kaydet
            document.getElementById('selected_guest').value = person;
            
            // Tüm kartların ödeme butonlarını gizle
            document.querySelectorAll('.payment-buttons').forEach(buttons => {
                buttons.style.display = 'none';
            });
            
            // Seçilen kartın ödeme butonlarını göster
            const paymentButtons = selectedCard.querySelector('.payment-buttons');
            if (paymentButtons) {
                paymentButtons.style.display = 'block';
            }
            
            // Seçilen kişiyi vurgula
            const personName = person === '' ? 'Ortak' : 'Kişi ' + person.charAt(person.length - 1);
            Toastify({
                text: personName + " seçildi - ürünleri atamak için tıklayın",
                duration: 3000,
                backgroundColor: "#3a86ff",
            }).showToast();
        }
        
        // Kişi için ödeme al
        function payForPerson(person, paymentMethod) {
            POS.sounds.play('payment');
            
            const masaId = document.getElementById('masa_id').value;
            const personName = person === '' ? 'Ortak' : 'Kişi ' + person.charAt(person.length - 1);
            
            // Kişinin toplam tutarını al
            let personTotal = 0;
            const orderItems = document.querySelectorAll(`.summary-item[data-person="${person}"]`);
            orderItems.forEach(function(item) {
                const price = parseFloat(item.querySelector('.item-price').innerText.replace('₺', ''));
                const quantity = parseInt(item.querySelector('.quantity-value').innerText);
                personTotal += price * quantity;
            });
            
            if (personTotal <= 0) {
                Toastify({
                    text: personName + " için atanmış ürün bulunamadı",
                    duration: 3000,
                    backgroundColor: "#e74c3c",
                }).showToast();
                return;
            }
            
            // Ödeme kaydet
            $.ajax({
                url: 'kaydet_kisisel.php',
                method: 'POST',
                data: {
                    masaId: masaId,
                    personName: personName,
                    amount: personTotal.toFixed(2),
                    paymentMethod: paymentMethod
                },
                success: function(response) {
                    if (response.success) {
                        // Kişi kartını ödenmiş olarak işaretle
                        const card = document.querySelector(`.guest-card[data-person="${person}"]`);
                        if (card) {
                            card.classList.add('paid');
                            
                            // Ödeme butonlarını gizle
                            const paymentButtons = card.querySelector('.payment-buttons');
                            if (paymentButtons) {
                                paymentButtons.style.display = 'none';
                            }
                        }
                        
                        Toastify({
                            text: personName + " için " + paymentMethod + " ödeme alındı: " + personTotal.toFixed(2) + "₺",
                            duration: 3000,
                            backgroundColor: "#2ecc71",
                        }).showToast();
                        
                        // Kişisel fiş yazdır
                        printPersonalReceipt(person, paymentMethod);
                    } else {
                        Toastify({
                            text: "Ödeme kaydedilirken hata oluştu",
                            duration: 3000,
                            backgroundColor: "#e74c3c",
                        }).showToast();
                    }
                },
                error: function() {
                    Toastify({
                        text: "Sunucu ile iletişim hatası",
                        duration: 3000,
                        backgroundColor: "#e74c3c",
                    }).showToast();
                }
            });
        }
        
        // Kişisel fiş yazdır
        function printPersonalReceipt(person, paymentMethod) {
            const masaId = document.getElementById('masa_id').value;
            const personName = person === '' ? 'Ortak' : 'Kişi ' + person.charAt(person.length - 1);
            
            // Kişiye ait ürünleri topla
            let personItems = [];
            let personTotal = 0;
            
            const orderItems = document.querySelectorAll(`.summary-item[data-person="${person}"]`);
            orderItems.forEach(function(item) {
                const productName = item.querySelector('.item-name').innerText.split(' - ')[0];
                const price = item.querySelector('.item-price').innerText;
                const quantity = item.querySelector('.quantity-value').innerText;
                const productId = item.getAttribute('data-product-id');
                
                personItems.push({
                    product_id: productId,
                    name: productName,
                    price: price,
                    quantity: quantity
                });
                
                personTotal += parseFloat(price.replace('₺', '')) * parseInt(quantity);
            });
            
            // Fiş verilerini hazırla
            const receiptData = {
                receiptNumber: POS.utils.getRandomReceiptNumber(),
                tableId: masaId,
                tableName: "Masa " + masaId,
                dateTime: new Date().toISOString().slice(0, 19).replace('T', ' '),
                items: personItems,
                totalAmount: personTotal.toFixed(2) + "₺",
                taxRate: 8,
                taxAmount: (personTotal * 0.08).toFixed(2) + "₺",
                subtotal: (personTotal * 0.92).toFixed(2) + "₺",
                paymentMethod: paymentMethod,
                personName: personName,
                splitType: 'individual'
            };
            
            // Fiş yazdırma isteğini veritabanına ekle
            POS.utils.ajax('add_print_queue.php', 'POST', {
                masa_id: masaId,
                masa_adi: "Masa " + masaId + " (" + personName + ")",
                receipt_type: 'personal',
                receipt_data: JSON.stringify(receiptData)
            }, function(response) {
                if (response.success) {
                    Toastify({
                        text: personName + " için fiş yazdırma isteği gönderildi",
                        duration: 3000,
                        backgroundColor: "#f1c40f",
                    }).showToast();
                }
            });
        }

        // Kişi bazlı toplam hesaplamalarını güncelle
        function updatePersonTotals() {
            // Toplamları sıfırla
            let personTotals = {
                '': 0, // Ortak
                'person1': 0,
                'person2': 0,
                'person3': 0,
                'person4': 0
            };
            
            // Kişi bazlı toplamları hesapla
            var orderItems = document.querySelectorAll(".summary-item");
            orderItems.forEach(function(item) {
                var priceElement = item.querySelector(".item-price");
                var price = parseFloat(priceElement.innerText.replace("₺", "").trim());
                var quantityElement = item.querySelector(".quantity-value");
                var quantity = parseInt(quantityElement.innerText.trim());
                var itemTotal = price * quantity;
                var person = item.getAttribute('data-person');
                
                // Kişinin toplamına ekle
                personTotals[person] += itemTotal;
            });
            
            // Görüntülenen kişi toplamlarını güncelle
            const totalSharedElem = document.getElementById("total-shared");
            const totalPerson1Elem = document.getElementById("total-person1");
            const totalPerson2Elem = document.getElementById("total-person2");
            const totalPerson3Elem = document.getElementById("total-person3");
            const totalPerson4Elem = document.getElementById("total-person4");
            
            if (totalSharedElem) totalSharedElem.innerText = personTotals[''].toFixed(2) + "₺";
            if (totalPerson1Elem) totalPerson1Elem.innerText = personTotals['person1'].toFixed(2) + "₺";
            if (totalPerson2Elem) totalPerson2Elem.innerText = personTotals['person2'].toFixed(2) + "₺";
            if (totalPerson3Elem) totalPerson3Elem.innerText = personTotals['person3'].toFixed(2) + "₺";
            if (totalPerson4Elem) totalPerson4Elem.innerText = personTotals['person4'].toFixed(2) + "₺";
            
            // Bireysel bölüşüm sonucunu güncelle (görünür durumdaysa)
            const individualSplitResult = document.getElementById('individual-split-result');
            if (individualSplitResult && document.getElementById('individual-split-form').style.display !== 'none') {
                let resultHTML = `
                    <div class="card mt-3">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-users me-2"></i>Kişi Bazlı Toplam
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Ortak
                                    <span class="badge bg-primary rounded-pill">${personTotals[''].toFixed(2)}₺</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Kişi 1
                                    <span class="badge bg-primary rounded-pill">${personTotals['person1'].toFixed(2)}₺</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Kişi 2
                                    <span class="badge bg-primary rounded-pill">${personTotals['person2'].toFixed(2)}₺</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Kişi 3
                                    <span class="badge bg-primary rounded-pill">${personTotals['person3'].toFixed(2)}₺</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Kişi 4
                                    <span class="badge bg-primary rounded-pill">${personTotals['person4'].toFixed(2)}₺</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                `;
                individualSplitResult.innerHTML = resultHTML;
            }
        }
        
        // Bölünmüş fişleri yazdır
        function printSplitReceipts() {
            const masaId = document.getElementById('masa_id').value;
            const splitType = document.querySelector('.split-option.selected')?.getAttribute('data-split-type');
            
            if (!splitType) {
                Toastify({
                    text: "Lütfen bir hesap bölme yöntemi seçin",
                    duration: 3000,
                    backgroundColor: "#e74c3c",
                }).showToast();
                return;
            }
            
            if (splitType === 'even') {
                // Eşit bölünmüş fişleri yazdır
                POS.payment.printSplitReceipts(masaId);
            } else if (splitType === 'individual') {
                // Kişi bazlı fişleri yazdır
                POS.payment.printIndividualReceipts(masaId);
            }
        }

        // Ana sayfaya dön
        function returnToMasaSecimi() {
            POS.sounds.play('buttonClick');
            window.location.href = "index.php";
        }
        
        // POS nesnesine yeni kişisel fiş yazdırma fonksiyonu ekle
        if (POS && POS.payment) {
            POS.payment.fisGonder = function(masaId, dateTime) {
    POS.sounds.play('print');

    // Sipariş verilerini al
    var totalAmount = POS.orders.updateTotalPrice().toFixed(2) + "₺";
    var orderItems = Array.from(document.querySelectorAll(".summary-item")).map(function (item) {
        var productName = item.querySelector(".item-name").innerText.split(' - ')[0]; // Kişi etiketini kaldır
        var price = item.querySelector(".item-price").innerText;
        var quantity = item.querySelector(".quantity-value").innerText;
        var productId = item.getAttribute('data-product-id');

        return {
            product_id: productId,
            name: productName,
            price: price,
            quantity: quantity
        };
    });

    // Fiş verilerini hazırla
    var receiptData = {
        receiptNumber: POS.utils.getRandomReceiptNumber(),
        tableId: masaId,
        tableName: "Masa " + masaId,
        dateTime: dateTime,
        items: orderItems,
        totalAmount: totalAmount,
        taxRate: 8,
        taxAmount: (POS.orders.updateTotalPrice() * 0.08).toFixed(2) + "₺",
        subtotal: (POS.orders.updateTotalPrice() * 0.92).toFixed(2) + "₺",
        paymentMethod: "Belirsiz"
    };

    // Fiş yazdırma isteğini veritabanına ekle
    POS.utils.ajax('add_print_queue.php', 'POST', {
        masa_id: masaId,
        masa_adi: "Masa " + masaId,
        receipt_type: 'full',
        receipt_data: JSON.stringify(receiptData)
    }, function (response) {
        if (response.success) {
            Toastify({
                text: "Fiş yazdırma isteği kasaya gönderildi",
                duration: 3000,
                style: { background: "#f1c40f" }
            }).showToast();
            
            // Onay modalını gösterme - sadece bildirim yeterli
        }
    });
};
            // Hesap bölme seçenekleri
            POS.payment.selectSplitOption = function(type) {
                // Önce tüm seçeneklerin seçimini kaldır
                document.querySelectorAll('.split-option').forEach(option => {
                    option.classList.remove('selected');
                });
                
                // Seçilen seçeneği işaretle
                document.querySelector(`.split-option[data-split-type="${type}"]`).classList.add('selected');
                
                // İlgili formu göster, diğerlerini gizle
                if (type === 'even') {
                    document.getElementById('even-split-form').style.display = 'block';
                    document.getElementById('individual-split-form').style.display = 'none';
                } else if (type === 'individual') {
                    document.getElementById('even-split-form').style.display = 'none';
                    document.getElementById('individual-split-form').style.display = 'block';
                }
            };
        }
    </script>
</body>
</html>