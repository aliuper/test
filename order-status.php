<?php
/**
 * Sipariş Takip Sayfası
 */

// Gerekli dosyaları dahil et
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Sayfa bilgileri
$currentPage = 'order-status';
$pageTitle = 'Siparişlerim';

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

// Siparişleri getir
$orders = [];

if ($tableId > 0) {
    // Masaya ait siparişleri getir
    $orders = dbQuery("
        SELECT * FROM orders 
        WHERE table_id = ? 
        ORDER BY created_at DESC
    ", [$tableId]);
}

// Sipariş ID'si varsa detaylarını getir
$orderId = isset($_GET['order']) ? (int)$_GET['order'] : 0;
$orderDetails = null;
$orderItems = [];

if ($orderId > 0) {
    // Sipariş detaylarını getir
    $orderDetails = dbQuerySingle("
        SELECT o.*, t.name as table_name 
        FROM orders o 
        JOIN tables t ON o.table_id = t.id 
        WHERE o.id = ?
    ", [$orderId]);
    
    if ($orderDetails) {
        // Sipariş ürünlerini getir
        $orderItems = dbQuery("
            SELECT oi.*, p.name as product_name, p.image as product_image
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ", [$orderId]);
    }
}

// İçerik oluştur
ob_start();

// Sipariş detayları gösteriliyor mu kontrol et
if ($orderDetails) {
    // Durum renkleri
    $statusColors = [
        'pending' => 'warning',
        'confirmed' => 'info',
        'preparing' => 'primary',
        'ready' => 'success',
        'delivered' => 'secondary',
        'completed' => 'dark',
        'cancelled' => 'danger'
    ];
    
    // Durum metinleri
    $statusTexts = [
        'pending' => 'Beklemede',
        'confirmed' => 'Onaylandı',
        'preparing' => 'Hazırlanıyor',
        'ready' => 'Hazır',
        'delivered' => 'Teslim Edildi',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal Edildi'
    ];
?>
    <div class="container my-4">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= BASE_URL ?>/order-status.php<?= isset($tableId) ? '?table=' . $tableId : '' ?>" class="btn btn-outline-secondary me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h4 class="mb-0">Sipariş Detayı: <?= h($orderDetails['order_code']) ?></h4>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-<?= $statusColors[$orderDetails['status']] ?> text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-info-circle me-2"></i> Sipariş Durumu: <?= $statusTexts[$orderDetails['status']] ?>
                    </div>
                    <div>
                        <i class="far fa-clock me-1"></i> <?= formatDate($orderDetails['created_at']) ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Sipariş Durumu İlerleme Çubuğu -->
                <?php 
                // Sipariş durum adımları
                $statusSteps = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'completed'];
                $currentStepIndex = array_search($orderDetails['status'], $statusSteps);
                
                // İptal ise özel durum
                if ($orderDetails['status'] === 'cancelled') {
                    $currentStepIndex = -1;
                }
                ?>
                
                <?php if ($orderDetails['status'] !== 'cancelled'): ?>
                    <div class="progress mb-4" style="height: 30px;">
                        <?php foreach ($statusSteps as $index => $status): ?>
                            <?php
                            $width = 100 / count($statusSteps);
                            $stepClass = '';
                            
                            if ($index < $currentStepIndex) {
                                $stepClass = 'bg-success';
                            } elseif ($index === $currentStepIndex) {
                                $stepClass = 'bg-' . $statusColors[$status] . ' progress-bar-striped progress-bar-animated';
                            } else {
                                $stepClass = 'bg-light text-dark';
                            }
                            ?>
                            <div class="progress-bar <?= $stepClass ?>" style="width: <?= $width ?>%">
                                <?= $statusTexts[$status] ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-ban me-2"></i> Bu sipariş iptal edilmiştir.
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Sipariş Bilgileri</h5>
                        <table class="table table-striped">
                            <tr>
                                <th>Sipariş Kodu:</th>
                                <td><?= h($orderDetails['order_code']) ?></td>
                            </tr>
                            <tr>
                                <th>Masa:</th>
                                <td><?= h($orderDetails['table_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Tarih:</th>
                                <td><?= formatDate($orderDetails['created_at']) ?></td>
                            </tr>
                            <tr>
                                <th>Toplam Tutar:</th>
                                <td><?= formatCurrency($orderDetails['total_amount']) ?></td>
                            </tr>
                            <?php if (!empty($orderDetails['note'])): ?>
                                <tr>
                                    <th>Not:</th>
                                    <td><?= h($orderDetails['note']) ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h5 class="mb-3">Sipariş Durumu</h5>
                        <div class="alert alert-<?= $statusColors[$orderDetails['status']] ?> mb-4">
                            <h5 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i> 
                                <?= $statusTexts[$orderDetails['status']] ?>
                            </h5>
                            <hr>
                            <p class="mb-0">
                                <?php if ($orderDetails['status'] === 'pending'): ?>
                                    Siparişiniz alındı ve onay bekliyor. Lütfen bekleyin.
                                <?php elseif ($orderDetails['status'] === 'confirmed'): ?>
                                    Siparişiniz onaylandı ve yakında hazırlanmaya başlanacak.
                                <?php elseif ($orderDetails['status'] === 'preparing'): ?>
                                    Siparişiniz şu anda mutfakta hazırlanıyor.
                                <?php elseif ($orderDetails['status'] === 'ready'): ?>
                                    Siparişiniz hazır ve masanıza getiriliyor.
                                <?php elseif ($orderDetails['status'] === 'delivered'): ?>
                                    Siparişiniz masanıza teslim edildi. Afiyet olsun!
                                <?php elseif ($orderDetails['status'] === 'completed'): ?>
                                    Siparişiniz tamamlandı. Teşekkür ederiz!
                                <?php elseif ($orderDetails['status'] === 'cancelled'): ?>
                                    Siparişiniz iptal edildi. Daha fazla bilgi için lütfen personele danışın.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Sipariş Ürünleri</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Görsel</th>
                                <th>Ürün</th>
                                <th>Miktar</th>
                                <th>Birim Fiyat</th>
                                <th>Toplam</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orderItems)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Bu siparişe ait ürün bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $imageUrl = !empty($item['product_image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $item['product_image']) 
                                                ? ASSETS_URL . '/uploads/products/' . $item['product_image'] 
                                                : ASSETS_URL . '/img/no-image.jpg';
                                            ?>
                                            <img src="<?= $imageUrl ?>" class="img-fluid rounded" style="width: 60px; height: 60px; object-fit: cover;" alt="<?= h($item['product_name']) ?>">
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= h($item['product_name']) ?></div>
                                            
                                            <?php if (!empty($item['options'])): ?>
                                                <div class="small text-muted mb-1"><?= h($item['options']) ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['note'])): ?>
                                                <div class="small text-muted">
                                                    <i class="fas fa-comment-alt me-1"></i> <?= h($item['note']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td><?= formatCurrency($item['unit_price']) ?></td>
                                        <td><?= formatCurrency($item['unit_price'] * $item['quantity']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $statusColors[$item['status']] ?>">
                                                <?= $statusTexts[$item['status']] ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end">
                                    <strong>Toplam:</strong>
                                </td>
                                <td colspan="2">
                                    <strong><?= formatCurrency($orderDetails['total_amount']) ?></strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php
} else {
    // Siparişler listesi
?>
    <div class="container my-4">
        <h4 class="mb-4">Siparişlerim</h4>
        
        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="card-body py-5 text-center">
                    <div class="mb-4">
                        <i class="fas fa-clipboard-list fa-4x text-muted"></i>
                    </div>
                    
                    <?php if ($tableId > 0): ?>
                        <h5 class="mb-3">Henüz Siparişiniz Yok</h5>
                        <p class="text-muted mb-4">Bu masa için henüz sipariş verilmemiş.</p>
                        <a href="<?= BASE_URL ?>/menu.php?table=<?= $tableId ?>" class="btn btn-primary">
                            <i class="fas fa-utensils me-2"></i> Menüye Gözat
                        </a>
                    <?php else: ?>
                        <h5 class="mb-3">Masa Bilgisi Gerekli</h5>
                        <p class="text-muted mb-4">Siparişlerinizi görmek için lütfen masanızdaki QR kodu tarayın.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <?php
                // Durum renkleri
                $statusColor = '';
                $statusText = '';
                
                switch ($order['status']) {
                    case 'pending':
                        $statusColor = 'warning';
                        $statusText = 'Beklemede';
                        break;
                    case 'confirmed':
                        $statusColor = 'info';
                        $statusText = 'Onaylandı';
                        break;
                    case 'preparing':
                        $statusColor = 'primary';
                        $statusText = 'Hazırlanıyor';
                        break;
                    case 'ready':
                        $statusColor = 'success';
                        $statusText = 'Hazır';
                        break;
                    case 'delivered':
                        $statusColor = 'secondary';
                        $statusText = 'Teslim Edildi';
                        break;
                    case 'completed':
                        $statusColor = 'dark';
                        $statusText = 'Tamamlandı';
                        break;
                    case 'cancelled':
                        $statusColor = 'danger';
                        $statusText = 'İptal Edildi';
                        break;
                }
                ?>
                <div class="card mb-3">
                    <div class="card-header bg-<?= $statusColor ?> text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-receipt me-2"></i> <?= h($order['order_code']) ?>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark">
                                    <?= formatDate($order['created_at']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-<?= $statusColor ?> p-3 rounded-circle me-3 text-white">
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <i class="fas fa-hourglass-start"></i>
                                        <?php elseif ($order['status'] === 'confirmed'): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php elseif ($order['status'] === 'preparing'): ?>
                                            <i class="fas fa-spinner fa-spin"></i>
                                        <?php elseif ($order['status'] === 'ready'): ?>
                                            <i class="fas fa-utensils"></i>
                                        <?php elseif ($order['status'] === 'delivered'): ?>
                                            <i class="fas fa-truck"></i>
                                        <?php elseif ($order['status'] === 'completed'): ?>
                                            <i class="fas fa-flag-checkered"></i>
                                        <?php elseif ($order['status'] === 'cancelled'): ?>
                                            <i class="fas fa-ban"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h5 class="mb-1"><?= $statusText ?></h5>
                                        <p class="mb-0 text-muted">Sipariş Durumu</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 text-center">
                                <h5 class="mb-1"><?= formatCurrency($order['total_amount']) ?></h5>
                                <p class="mb-0 text-muted">Toplam Tutar</p>
                            </div>
                            
                            <div class="col-md-4 text-end">
                                <a href="<?= BASE_URL ?>/order-status.php?table=<?= $tableId ?>&order=<?= $order['id'] ?>" class="btn btn-outline-<?= $statusColor ?>">
                                    <i class="fas fa-eye me-1"></i> Detayları Görüntüle
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php
}

$content = ob_get_clean();

// Şablonu dahil et
include 'templates/template' . $template['id'] . '/index.php';