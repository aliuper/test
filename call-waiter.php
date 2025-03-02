<?php
/**
 * Garson Çağır Sayfası
 */

// Gerekli dosyaları dahil et
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

// Sayfa bilgileri
$currentPage = 'call-waiter';
$pageTitle = 'Garson Çağır';

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

// Garson çağırma işlemi
$success = false;
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['call_waiter']) && $tableId > 0) {
    // Rate limit kontrolü (1 dakikada en fazla 3 çağrı)
    if (!checkRateLimit('call_waiter_' . $tableId, 3, 60)) {
        $error = "Çok fazla istek gönderdiniz. Lütfen bir süre bekleyin.";
    } else {
        // Çağrı nedeni
        $reason = isset($_POST['reason']) ? sanitizeInput($_POST['reason']) : 'Genel';
        $note = isset($_POST['note']) ? sanitizeInput($_POST['note']) : '';
        
        // Sunucuya bildirim gönder (burada veritabanına kaydediliyor, gerçek projede websocket veya mobil bildirim eklenebilir)
        $result = dbInsert("
            INSERT INTO waiter_calls (table_id, reason, note, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ", [$tableId, $reason, $note]);
        
        if ($result) {
            $success = "Garsona bildirim gönderildi. Lütfen bekleyin.";
        } else {
            $error = "Garson çağrısı gönderilirken bir hata oluştu. Lütfen tekrar deneyin.";
        }
    }
}

// İçerik oluştur
ob_start();
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <?php if ($success): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle me-2"></i> <?= h($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($tableId > 0): ?>
                <div class="card">
                    <div class="card-header text-center">
                        <h4 class="mb-0">
                            <i class="fas fa-bell me-2"></i> Garson Çağır
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="mb-3">
                                <span class="badge bg-info fs-6">
                                    <i class="fas fa-chair me-1"></i> <?= h($tableInfo['name']) ?>
                                </span>
                            </div>
                            
                            <p class="lead">
                                Garson çağırmak için lütfen aşağıdaki formu doldurun.
                            </p>
                        </div>
                        
                        <form method="post" action="<?= $_SERVER['PHP_SELF'] . '?table=' . $tableId ?>">
                            <div class="mb-3">
                                <label for="reason" class="form-label">Çağırma Nedeni</label>
                                <select class="form-select" id="reason" name="reason" required>
                                    <option value="Genel">Genel Yardım</option>
                                    <option value="Sipariş">Yeni Sipariş Vermek İstiyorum</option>
                                    <option value="Hesap">Hesap İstiyorum</option>
                                    <option value="Bardak">Bardak/Tabak İstiyorum</option>
                                    <option value="Temizlik">Masa Temizliği</option>
                                    <option value="Diğer">Diğer</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="note" class="form-label">Ek Not (İsteğe Bağlı)</label>
                                <textarea class="form-control" id="note" name="note" rows="3" placeholder="Eklemek istediğiniz notları buraya yazabilirsiniz..."></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="call_waiter" class="btn btn-danger btn-lg">
                                    <i class="fas fa-bell me-2"></i> Garson Çağır
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> 
                            Çağrınız anında personelimize iletilecektir. Lütfen kısa süre içinde bekleyin.
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Diğer Seçenekler</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <a href="<?= BASE_URL ?>/menu.php?table=<?= $tableId ?>" class="btn btn-outline-primary d-block mb-3">
                                    <i class="fas fa-utensils me-2"></i> Menüye Dön
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= BASE_URL ?>/order-status.php?table=<?= $tableId ?>" class="btn btn-outline-info d-block mb-3">
                                    <i class="fas fa-clipboard-list me-2"></i> Siparişlerim
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-qrcode fa-4x text-muted"></i>
                        </div>
                        <h4 class="mb-3">QR Kod Gerekli</h4>
                        <p class="text-muted mb-4">
                            Garson çağırmak için lütfen masanızdaki QR kodu tarayın.
                        </p>
                        <p class="mb-0">
                            Yardıma ihtiyacınız varsa lütfen personelimize başvurun.
                        </p>
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