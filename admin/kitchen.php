<?php
/**
 * Mutfak Paneli - Sipariş hazırlama ekranı
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Giriş yapılmamışsa login sayfasına yönlendir
requireLogin();

// Sadece mutfak personeli ve süper admin erişebilir
if (!isKitchen() && !isSuperAdmin()) {
    redirect(ADMIN_URL . '/unauthorized.php');
}

// Sayfa başlığı
$pageTitle = 'Mutfak Paneli';

// Sipariş durumunu güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['item_id'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $itemId = (int)$_POST['item_id'];
        $action = $_POST['action'];
        
        if ($action === 'preparing') {
            $result = dbExecute("UPDATE order_items SET status = 'preparing' WHERE id = ?", [$itemId]);
            if ($result) {
                setFlashMessage('success', 'Sipariş hazırlanmaya başlandı.');
            } else {
                setFlashMessage('error', 'Sipariş güncellenirken bir hata oluştu.');
            }
        } elseif ($action === 'ready') {
            $result = dbExecute("UPDATE order_items SET status = 'ready' WHERE id = ?", [$itemId]);
            if ($result) {
                setFlashMessage('success', 'Sipariş hazır olarak işaretlendi.');
            } else {
                setFlashMessage('error', 'Sipariş güncellenirken bir hata oluştu.');
            }
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/kitchen.php');
}

// Bekleyen ve hazırlanmakta olan siparişleri getir
$pendingOrders = dbQuery("
    SELECT oi.id, oi.order_id, oi.product_id, oi.quantity, oi.note, oi.status,
           oi.options, p.name as product_name, p.preparation_time,
           o.order_code, o.created_at as order_time, t.name as table_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN tables t ON o.table_id = t.id
    WHERE oi.status IN ('pending', 'preparing')
    ORDER BY 
        CASE 
            WHEN oi.status = 'pending' THEN 1
            WHEN oi.status = 'preparing' THEN 2
            ELSE 3
        END,
        o.created_at ASC
");

// Hazır olan siparişleri getir
$readyOrders = dbQuery("
    SELECT oi.id, oi.order_id, oi.product_id, oi.quantity, oi.note, oi.status,
           oi.options, p.name as product_name, p.preparation_time,
           o.order_code, o.created_at as order_time, t.name as table_name
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    JOIN tables t ON o.table_id = t.id
    WHERE oi.status = 'ready'
    ORDER BY o.created_at DESC
    LIMIT 10
");

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<style>
    .kitchen-order {
        transition: all 0.3s ease;
    }
    .kitchen-order:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .blink {
        animation: blinker 1s linear infinite;
    }
    @keyframes blinker {
        50% { opacity: 0.5; }
    }
    .kitchen-toolbar {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #fff;
        padding: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .order-time {
        font-size: 0.8rem;
    }
    .time-critical {
        color: #dc3545;
    }
    .order-status {
        position: absolute;
        top: 10px;
        right: 10px;
    }
    .kitchen-tabs .nav-link {
        font-weight: 600;
        padding: 10px 20px;
    }
    .kitchen-tabs .nav-link.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
</style>
';

// Ekstra JS
$extraJs = '
<script>
    // Sayfayı otomatik yenile
    setInterval(function() {
        location.reload();
    }, 60000); // 1 dakika
    
    // Sipariş notu popover
    var popoverTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="popover"]\'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            html: true,
            trigger: "hover focus"
        });
    });
    
    // Bekleyen siparişlerin sayısını kontrol et ve başlıkta göster
    function updateTitle() {
        const pendingCount = ' . count($pendingOrders) . ';
        if (pendingCount > 0) {
            document.title = "(" + pendingCount + ") ' . $pageTitle . ' - Restoran Menü Sistemi";
        } else {
            document.title = "' . $pageTitle . ' - Restoran Menü Sistemi";
        }
    }
    
    updateTitle();
</script>
';
?>

<!-- Mutfak Arayüzü -->
<div class="kitchen-toolbar">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h4><i class="fas fa-utensils me-2"></i> Mutfak Sipariş Ekranı</h4>
        </div>
        <div class="col-md-6 text-end">
            <div class="d-flex justify-content-end align-items-center">
                <span class="me-3">
                    <strong>Bekleyen:</strong> <span class="badge bg-danger"><?= count($pendingOrders) ?></span>
                </span>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                    <i class="fas fa-sync-alt me-1"></i> Yenile
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mutfak Tablar -->
<ul class="nav nav-tabs kitchen-tabs mb-4" id="kitchenTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
            <i class="fas fa-hourglass-half me-1"></i> Hazırlanacak Siparişler <span class="badge bg-danger"><?= count($pendingOrders) ?></span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="ready-tab" data-bs-toggle="tab" data-bs-target="#ready" type="button" role="tab" aria-controls="ready" aria-selected="false">
            <i class="fas fa-check-circle me-1"></i> Hazır Siparişler <span class="badge bg-success"><?= count($readyOrders) ?></span>
        </button>
    </li>
</ul>

<div class="tab-content" id="kitchenTabsContent">
    <!-- Bekleyen Siparişler -->
    <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
        <div class="row">
            <?php if (empty($pendingOrders)): ?>
                <div class="col-12 text-center py-5">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i> Tüm siparişler hazır! Yeni sipariş bekleniyor...
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($pendingOrders as $order): ?>
                    <?php
                    // Sipariş zamanını hesapla
                    $orderTime = strtotime($order['order_time']);
                    $currentTime = time();
                    $diffMinutes = round(($currentTime - $orderTime) / 60);
                    
                    // Sipariş durumuna göre kart rengini belirle
                    $cardClass = $order['status'] === 'pending' ? 'border-danger' : 'border-warning';
                    $bgClass = $order['status'] === 'pending' ? 'bg-danger-subtle' : 'bg-warning-subtle';
                    
                    // Hazırlama süresi aşıldı mı kontrol et
                    $isTimeCritical = false;
                    if ($order['preparation_time']) {
                        $isTimeCritical = $diffMinutes > $order['preparation_time'];
                    } elseif ($diffMinutes > 15) { // Varsayılan süre
                        $isTimeCritical = true;
                    }
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card kitchen-order <?= $cardClass ?> <?= $bgClass ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <strong>Masa:</strong> <?= h($order['table_name']) ?>
                                </h5>
                                <span class="order-status">
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <span class="badge bg-danger blink">Bekliyor</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Hazırlanıyor</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= h($order['product_name']) ?> x<?= $order['quantity'] ?></h5>
                                
                                <?php if (!empty($order['options'])): ?>
                                    <p class="card-text small mb-2">
                                        <strong>Seçenekler:</strong> <?= h($order['options']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($order['note'])): ?>
                                    <p class="card-text small mb-2">
                                        <strong>Not:</strong> 
                                        <span class="text-danger"><?= h($order['note']) ?></span>
                                    </p>
                                <?php endif; ?>
                                
                                <p class="order-time mb-3">
                                    <strong>Sipariş Zamanı:</strong> 
                                    <span class="<?= $isTimeCritical ? 'time-critical' : '' ?>">
                                        <?= formatDate($order['order_time']) ?> 
                                        (<?= $diffMinutes ?> dk önce)
                                    </span>
                                </p>
                                
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">
                                        <strong>Sipariş Kodu:</strong> <?= h($order['order_code']) ?>
                                    </small>
                                    
                                    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="item_id" value="<?= $order['id'] ?>">
                                        
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <input type="hidden" name="action" value="preparing">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <i class="fas fa-fire me-1"></i> Hazırlamaya Başla
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="ready">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check me-1"></i> Hazır
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Hazır Siparişler -->
    <div class="tab-pane fade" id="ready" role="tabpanel" aria-labelledby="ready-tab">
        <div class="row">
            <?php if (empty($readyOrders)): ?>
                <div class="col-12 text-center py-5">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Henüz hazır sipariş bulunmuyor.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($readyOrders as $order): ?>
                    <?php
                    // Sipariş zamanını hesapla
                    $orderTime = strtotime($order['order_time']);
                    $currentTime = time();
                    $diffMinutes = round(($currentTime - $orderTime) / 60);
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card kitchen-order border-success bg-success-subtle">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <strong>Masa:</strong> <?= h($order['table_name']) ?>
                                </h5>
                                <span class="order-status">
                                    <span class="badge bg-success">Hazır</span>
                                </span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?= h($order['product_name']) ?> x<?= $order['quantity'] ?></h5>
                                
                                <?php if (!empty($order['options'])): ?>
                                    <p class="card-text small mb-2">
                                        <strong>Seçenekler:</strong> <?= h($order['options']) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if (!empty($order['note'])): ?>
                                    <p class="card-text small mb-2">
                                        <strong>Not:</strong> 
                                        <span class="text-danger"><?= h($order['note']) ?></span>
                                    </p>
                                <?php endif; ?>
                                
                                <p class="order-time mb-3">
                                    <strong>Sipariş Zamanı:</strong> 
                                    <?= formatDate($order['order_time']) ?> 
                                    (<?= $diffMinutes ?> dk önce)
                                </p>
                                
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">
                                        <strong>Sipariş Kodu:</strong> <?= h($order['order_code']) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>