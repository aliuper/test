<?php
/**
 * Sepet Sayfası
 */

// Gerekli dosyaları dahil et
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Sayfa bilgileri
$currentPage = 'cart';
$pageTitle = 'Sepetim';

// Masa ID'si kontrol
$tableId = isset($_GET['table']) ? (int)$_GET['table'] : 0;
$tableInfo = null;

if ($tableId > 0) {
    // Masa bilgilerini getir
    $tableInfo = dbQuerySingle("SELECT * FROM tables WHERE id = ?", [$tableId]);
    
    if (!$tableInfo) {
        // Geçersiz masa, hata sayfasına yönlendir
        redirect(BASE_URL . '/error.php?type=table');
    }
    
    // Eğer masa bakımda ise bilgi ver
    if ($tableInfo['status'] === 'maintenance') {
        redirect(BASE_URL . '/maintenance.php?table=' . $tableId);
    }
    
    // Masa şablonunu getir
    $qrTemplate = $tableInfo['qr_template'];
} else {
    // Varsayılan şablonu kullan
    $qrTemplate = dbQuerySingle("SELECT id FROM qr_templates WHERE is_default = 1")['id'] ?? 1;
}

// Şablon bilgilerini getir
$template = dbQuerySingle("SELECT * FROM qr_templates WHERE id = ?", [$qrTemplate]);

if (!$template) {
    // Varsayılan şablonu kullan
    $template = dbQuerySingle("SELECT * FROM qr_templates WHERE is_default = 1");
    
    if (!$template) {
        // Şablon bulunamadı, hata sayfasına yönlendir
        redirect(BASE_URL . '/error.php?type=template');
    }
}

// Site ayarlarını getir
$siteTitle = getSetting('site_title', 'Restoran Menü Sistemi');
$siteDescription = getSetting('site_description', 'Modern Restoran QR Menü Sistemi');
$restaurantLogo = getSetting('restaurant_logo', '');
$restaurantSlogan = getSetting('restaurant_slogan', '');

// Şu anki URL'yi al
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Session'da sepet var mı kontrol et
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Sepeti getir
$cart = $_SESSION['cart'];

// Ürün miktarını güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'], $_POST['quantity'])) {
    foreach ($_POST['quantity'] as $key => $quantity) {
        $quantity = (int)$quantity;
        
        if ($quantity <= 0) {
            // Ürünü sepetten çıkar
            unset($_SESSION['cart'][$key]);
        } else {
            // Miktarı güncelle
            $_SESSION['cart'][$key]['quantity'] = $quantity;
        }
    }
    
    // Sepeti yeniden oluştur
    $cart = $_SESSION['cart'];
}

// Ürün sepetten çıkar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'], $_POST['item_key'])) {
    $itemKey = sanitizeInput($_POST['item_key']);
    
    if (isset($_SESSION['cart'][$itemKey])) {
        unset($_SESSION['cart'][$itemKey]);
        
        // Sepeti yeniden oluştur
        $cart = $_SESSION['cart'];
    }
}

// Sepeti boşalt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cart'])) {
    $_SESSION['cart'] = [];
    $cart = [];
}

// Siparişi tamamla
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.';
    } elseif (empty($cart)) {
        $error = 'Sepetiniz boş. Lütfen sepete ürün ekleyin.';
    } elseif ($tableId <= 0) {
        $error = 'Masa bilgisi gerekli. Lütfen QR kodu tekrar tarayın.';
    } else {
        // Sipariş notu
        $orderNote = isset($_POST['order_note']) ? sanitizeInput($_POST['order_note']) : '';
        
        // Toplam tutarı hesapla
        $totalAmount = 0;
        
        foreach ($cart as $item) {
            $totalAmount += $item['price'] * $item['quantity'];
        }
        
        // Sipariş kodu oluştur
        $orderCode = 'ORD-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        
        // Sipariş oluştur
        $orderId = dbInsert("
            INSERT INTO orders (table_id, order_code, status, total_amount, note, created_at)
            VALUES (?, ?, 'pending', ?, ?, NOW())
        ", [$tableId, $orderCode, $totalAmount, $orderNote]);
        
        if ($orderId) {
            // Sipariş ürünlerini ekle
            foreach ($cart as $item) {
                dbInsert("
                    INSERT INTO order_items (order_id, product_id, quantity, unit_price, options, note, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ", [$orderId, $item['product_id'], $item['quantity'], $item['price'], $item['options'] ?? '', $item['note'] ?? '']);
            }
            
            // Masa durumunu güncelle
            dbExecute("UPDATE tables SET status = 'occupied' WHERE id = ?", [$tableId]);
            
            // Sepeti temizle
            $_SESSION['cart'] = [];
            
            // Sipariş takip sayfasına yönlendir
            redirect(BASE_URL . '/order-status.php?table=' . $tableId . '&order=' . $orderId);
        } else {
            $error = 'Sipariş oluşturulurken bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}

// Sepetteki ürünlerin detaylarını al
$cartItems = [];
$cartTotal = 0;

foreach ($cart as $key => $item) {
    // Ürün bilgilerini getir
    $product = dbQuerySingle("SELECT * FROM products WHERE id = ?", [$item['product_id']]);
    
    if ($product) {
        $cartItems[$key] = [
            'product_id' => $item['product_id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'image' => $product['image'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'note' => $item['note'] ?? '',
            'options' => $item['options'] ?? '',
            'subtotal' => $item['price'] * $item['quantity']
        ];
        
        $cartTotal += $cartItems[$key]['subtotal'];
    }
}

// İçerik oluştur
ob_start();
?>

<div class="container my-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i> Sepetim
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($cartItems)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-shopping-cart fa-4x text-muted"></i>
                            </div>
                            <h4 class="mb-3">Sepetiniz Boş</h4>
                            <p class="text-muted mb-4">Sepetinizde henüz ürün bulunmuyor.</p>
                            <a href="<?= BASE_URL ?>/menu.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="btn btn-primary">
                                <i class="fas fa-utensils me-2"></i> Menüye Dön
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="<?= $_SERVER['PHP_SELF'] . (isset($tableId) ? '?table=' . $tableId : '') ?>">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 100px;">Görsel</th>
                                            <th>Ürün</th>
                                            <th>Fiyat</th>
                                            <th style="width: 120px;">Miktar</th>
                                            <th>Toplam</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartItems as $key => $item): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $imageUrl = !empty($item['image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $item['image']) 
                                                        ? ASSETS_URL . '/uploads/products/' . $item['image'] 
                                                        : ASSETS_URL . '/img/no-image.jpg';
                                                    ?>
                                                    <img src="<?= $imageUrl ?>" class="img-fluid rounded" style="width: 80px; height: 80px; object-fit: cover;" alt="<?= h($item['name']) ?>">
                                                </td>
                                                <td>
                                                    <div class="fw-bold mb-1"><?= h($item['name']) ?></div>
                                                    
                                                    <?php if (!empty($item['options'])): ?>
                                                        <div class="small text-muted mb-1"><?= h($item['options']) ?></div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($item['note'])): ?>
                                                        <div class="small text-muted">
                                                            <i class="fas fa-comment-alt me-1"></i> <?= h($item['note']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= formatCurrency($item['price']) ?></td>
                                                <td>
                                                    <input type="number" name="quantity[<?= $key ?>]" class="form-control form-control-sm" value="<?= $item['quantity'] ?>" min="1" max="10">
                                                </td>
                                                <td><?= formatCurrency($item['subtotal']) ?></td>
                                                <td>
                                                    <form method="post" action="<?= $_SERVER['PHP_SELF'] . (isset($tableId) ? '?table=' . $tableId : '') ?>" class="d-inline">
                                                        <input type="hidden" name="item_key" value="<?= $key ?>">
                                                        <button type="submit" name="remove_item" class="btn btn-sm btn-link text-danger">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" name="update_cart" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-sync-alt me-1"></i> Güncelle
                                </button>
                                
                                <button type="submit" name="clear_cart" class="btn btn-outline-danger">
                                    <i class="fas fa-trash-alt me-1"></i> Sepeti Boşalt
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($cartItems)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Sipariş Notu</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?= $_SERVER['PHP_SELF'] . (isset($tableId) ? '?table=' . $tableId : '') ?>">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <textarea name="order_note" class="form-control" rows="3" placeholder="Sipariş için genel notunuzu buraya yazabilirsiniz..."></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Sipariş verdikten sonra siparişiniz mutfağa gönderilecek ve hazırlanmaya başlanacaktır.
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="place_order" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle me-2"></i> Siparişi Tamamla
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Sipariş Özeti</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>Ara Toplam:</span>
                        <span><?= formatCurrency($cartTotal) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>KDV (8%):</span>
                        <span><?= formatCurrency($cartTotal * 0.08) ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold">Toplam:</span>
                        <span class="fw-bold"><?= formatCurrency($cartTotal * 1.08) ?></span>
                    </div>
                    
                    <?php if (empty($cartItems)): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-circle me-2"></i> Siparişi tamamlamak için sepetinize ürün ekleyin.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($tableInfo)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Masa Bilgisi</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-light rounded-circle p-3 me-3">
                                <i class="fas fa-chair fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h5 class="mb-1"><?= h($tableInfo['name']) ?></h5>
                                <?php if (!empty($tableInfo['location'])): ?>
                                    <p class="text-muted mb-0"><?= h($tableInfo['location']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> Siparişiniz bu masa için işlenecektir.
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Bilgi</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-circle me-2"></i> Sipariş vermek için lütfen masanızdaki QR kodu tarayın.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Şablonu dahil et
include 'templates/template' . $template['id'] . '/index.php';