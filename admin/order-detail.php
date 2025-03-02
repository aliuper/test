<?php
/**
 * Sipariş Detay Sayfası
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Giriş yapılmamışsa login sayfasına yönlendir
requireLogin();

// Sadece garson ve süper admin erişebilir
if (!isWaiter() && !isSuperAdmin()) {
    redirect(ADMIN_URL . '/unauthorized.php');
}

// Sipariş ID'si
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId <= 0) {
    setFlashMessage('error', 'Geçersiz sipariş ID\'si.');
    redirect(ADMIN_URL . '/orders.php');
}

// Sipariş bilgilerini getir
$order = dbQuerySingle("
    SELECT o.*, t.name as table_name 
    FROM orders o 
    JOIN tables t ON o.table_id = t.id 
    WHERE o.id = ?
", [$orderId]);

if (!$order) {
    setFlashMessage('error', 'Sipariş bulunamadı.');
    redirect(ADMIN_URL . '/orders.php');
}

// Sipariş ürünlerini getir
$orderItems = dbQuery("
    SELECT oi.*, p.name as product_name, p.price as product_price, p.image as product_image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
", [$orderId]);

// Sipariş durumunu güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['status'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $newStatus = sanitizeInput($_POST['status']);
        
        // Geçerli durumlar
        $validStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'completed', 'cancelled'];
        
        if (in_array($newStatus, $validStatuses)) {
            $result = dbExecute("UPDATE orders SET status = ? WHERE id = ?", [$newStatus, $orderId]);
            
            if ($result) {
                setFlashMessage('success', 'Sipariş durumu güncellendi.');
            } else {
                setFlashMessage('error', 'Sipariş durumu güncellenirken bir hata oluştu.');
            }
        } else {
            setFlashMessage('error', 'Geçersiz sipariş durumu.');
        }
        
        // Sayfayı yenile
        redirect(ADMIN_URL . '/order-detail.php?id=' . $orderId);
    }
}

// Sayfa başlığı
$pageTitle = 'Sipariş Detayı - ' . $order['order_code'];

// Header'ı dahil et
include_once 'includes/header.php';

// Statuslara göre durum sınıfları
$statusClasses = [
    'pending' => 'warning',
    'confirmed' => 'info',
    'preparing' => 'primary',
    'ready' => 'success',
    'delivered' => 'secondary',
    'completed' => 'dark',
    'cancelled' => 'danger'
];

// Statuslara göre durum metinleri
$statusTexts = [
    'pending' => 'Beklemede',
    'confirmed' => 'Onaylandı',
    'preparing' => 'Hazırlanıyor',
    'ready' => 'Hazır',
    'delivered' => 'Teslim Edildi',
    'completed' => 'Tamamlandı',
    'cancelled' => 'İptal Edildi'
];

// Ekstra CSS
$extraCss = '
<style>
    .status-timeline {
        display: flex;
        justify-content: space-between;
        margin: 30px 0;
        position: relative;
    }
    .status-timeline::before {
        content: "";
        position: absolute;
        height: 4px;
        background-color: #e9ecef;
        width: 100%;
        top: 25px;
        z-index: 1;
    }
    .status-step {
        position: relative;
        z-index: 2;
        text-align: center;
    }
    .status-step .step-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        color: #fff;
    }
    .status-step.active .step-icon {
        background-color: var(--primary-color);
    }
    .status-step.completed .step-icon {
        background-color: var(--success-color);
    }
    .status-step .step-label {
        font-size: 0.875rem;
        font-weight: 500;
    }
    .status-step.completed .step-label {
        color: var(--success-color);
    }
    .status-step.active .step-label {
        color: var(--primary-color);
        font-weight: 600;
    }
    .item-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }
    .order-info-card {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .order-info-card strong {
        display: inline-block;
        width: 120px;
    }
</style>
';
?>

<!-- Üst Araç Çubuğu -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Siparişlere Dön
    </a>
    
    <div class="btn-group">
        <a href="<?= ADMIN_URL ?>/order-edit.php?id=<?= $order['id'] ?>" class="btn btn-outline-primary">
            <i class="fas fa-edit me-1"></i> Düzenle
        </a>
        <a href="<?= ADMIN_URL ?>/order-print.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-outline-dark">
            <i class="fas fa-print me-1"></i> Yazdır
        </a>
    </div>
</div>

<!-- Sipariş Başlığı -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-receipt me-2"></i> Sipariş: <?= h($order['order_code']) ?>
        </h5>
        <span class="badge bg-<?= $statusClasses[$order['status']] ?> fs-6">
            <?= $statusTexts[$order['status']] ?>
        </span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="order-info-card">
                    <p><strong>Masa:</strong> <?= h($order['table_name']) ?></p>
                    <p><strong>Toplam Tutar:</strong> <?= formatCurrency($order['total_amount']) ?></p>
                    <p><strong>Sipariş Tarihi:</strong> <?= formatDate($order['created_at']) ?></p>
                    <?php if (!empty($order['note'])): ?>
                        <p><strong>Not:</strong> <?= h($order['note']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $order['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Sipariş Durumunu Güncelle</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <select name="status" class="form-select">
                                    <?php foreach ($statusTexts as $key => $value): ?>
                                        <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>>
                                            <?= $value ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Güncelle
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Durum Zaman Çizelgesi -->
        <div class="status-timeline">
            <?php
            $orderStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'completed'];
            $currentStatusIndex = array_search($order['status'], $orderStatuses);
            
            // İptal edildi ise özel durum
            if ($order['status'] === 'cancelled') {
                $currentStatusIndex = false;
            }
            
            foreach ($orderStatuses as $index => $status):
                $statusClass = '';
                
                if ($currentStatusIndex !== false) {
                    if ($index < $currentStatusIndex) {
                        $statusClass = 'completed';
                    } elseif ($index === $currentStatusIndex) {
                        $statusClass = 'active';
                    }
                }
                
                // İptal edildi ise tüm adımları devre dışı bırak
                if ($order['status'] === 'cancelled') {
                    $statusClass = '';
                }
                
                $icon = '';
                switch ($status) {
                    case 'pending':
                        $icon = 'hourglass-start';
                        break;
                    case 'confirmed':
                        $icon = 'check-circle';
                        break;
                    case 'preparing':
                        $icon = 'spinner';
                        break;
                    case 'ready':
                        $icon = 'utensils';
                        break;
                    case 'delivered':
                        $icon = 'truck';
                        break;
                    case 'completed':
                        $icon = 'flag-checkered';
                        break;
                }
            ?>
                <div class="status-step <?= $statusClass ?>">
                    <div class="step-icon">
                        <i class="fas fa-<?= $icon ?>"></i>
                    </div>
                    <div class="step-label"><?= $statusTexts[$status] ?></div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($order['status'] === 'cancelled'): ?>
                <div class="status-step active">
                    <div class="step-icon" style="background-color: var(--danger-color);">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="step-label" style="color: var(--danger-color);">İptal Edildi</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Sipariş Ürünleri -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Sipariş Ürünleri</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 80px;">Görsel</th>
                        <th>Ürün</th>
                        <th>Adet</th>
                        <th>Birim Fiyat</th>
                        <th>Toplam</th>
                        <th>Durum</th>
                        <th>Not</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orderItems)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Bu siparişte ürün bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orderItems as $item): ?>
                            <?php
                            $statusClass = '';
                            $statusText = '';
                            
                            switch ($item['status']) {
                                case 'pending':
                                    $statusClass = 'bg-warning text-dark';
                                    $statusText = 'Beklemede';
                                    break;
                                case 'preparing':
                                    $statusClass = 'bg-primary';
                                    $statusText = 'Hazırlanıyor';
                                    break;
                                case 'ready':
                                    $statusClass = 'bg-success';
                                    $statusText = 'Hazır';
                                    break;
                                case 'delivered':
                                    $statusClass = 'bg-secondary';
                                    $statusText = 'Teslim Edildi';
                                    break;
                                case 'cancelled':
                                    $statusClass = 'bg-danger';
                                    $statusText = 'İptal Edildi';
                                    break;
                            }
                            ?>
                            <tr>
                                <td>
                                    <?php if (!empty($item['product_image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $item['product_image'])): ?>
                                        <img src="<?= ASSETS_URL ?>/uploads/products/<?= h($item['product_image']) ?>" alt="<?= h($item['product_name']) ?>" class="item-image">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center item-image">
                                            <i class="fas fa-utensils text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= h($item['product_name']) ?></strong>
                                    <?php if (!empty($item['options'])): ?>
                                        <br>
                                        <small class="text-muted"><?= h($item['options']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= formatCurrency($item['unit_price']) ?></td>
                                <td><?= formatCurrency($item['unit_price'] * $item['quantity']) ?></td>
                                <td>
                                    <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($item['note'])): ?>
                                        <span data-bs-toggle="tooltip" title="<?= h($item['note']) ?>">
                                            <i class="fas fa-comment-dots"></i> <?= h(substr($item['note'], 0, 20)) ?><?= strlen($item['note']) > 20 ? '...' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
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
                        <td colspan="3">
                            <strong><?= formatCurrency($order['total_amount']) ?></strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>