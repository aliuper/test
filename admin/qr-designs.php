<?php
/**
 * QR Menü Tasarımları Yönetimi
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Giriş yapılmamışsa login sayfasına yönlendir
requireLogin();

// Sadece süper admin erişebilir
if (!isSuperAdmin()) {
    redirect(ADMIN_URL . '/unauthorized.php');
}

// Sayfa başlığı
$pageTitle = 'QR Menü Tasarımları';

// QR Menü tasarımını varsayılan yap
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default'], $_POST['template_id'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $templateId = (int)$_POST['template_id'];
        
        // Önce tüm şablonları varsayılan olmayan yap
        dbExecute("UPDATE qr_templates SET is_default = 0");
        
        // Seçilen şablonu varsayılan yap
        $result = dbExecute("UPDATE qr_templates SET is_default = 1 WHERE id = ?", [$templateId]);
        
        if ($result) {
            setFlashMessage('success', 'Varsayılan QR menü tasarımı güncellendi.');
        } else {
            setFlashMessage('error', 'QR menü tasarımı güncellenirken bir hata oluştu.');
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/qr-designs.php');
}

// Tasarımları getir
$templates = dbQuery("SELECT * FROM qr_templates ORDER BY is_default DESC, name");

// Header'ı dahil et
include_once 'includes/header.php';
?>

<!-- Üst Araç Çubuğu -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">QR Menü Tasarımları</h5>
    <a href="<?= ADMIN_URL ?>/qr-design-new.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Yeni Tasarım Ekle
    </a>
</div>

<!-- QR Tasarımları -->
<div class="row">
    <?php if (empty($templates)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> Henüz QR menü tasarımı bulunmuyor.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($templates as $template): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <?php
                    $thumbnailPath = ROOT_DIR . '/' . $template['thumbnail'];
                    $defaultThumbnail = ADMIN_ASSETS_URL . '/img/qr-template-default.jpg';
                    $thumbnailUrl = file_exists($thumbnailPath) ? BASE_URL . '/' . $template['thumbnail'] : $defaultThumbnail;
                    ?>
                    <img src="<?= $thumbnailUrl ?>" class="card-img-top" alt="<?= h($template['name']) ?>" style="height: 200px; object-fit: cover;">
                    
                    <div class="card-body">
                        <h5 class="card-title">
                            <?= h($template['name']) ?>
                            <?php if ($template['is_default']): ?>
                                <span class="badge bg-success ms-2">Varsayılan</span>
                            <?php endif; ?>
                        </h5>
                        
                        <div class="mt-3">
                            <a href="<?= BASE_URL ?>/menu.php?template=<?= $template['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info me-2">
                                <i class="fas fa-eye me-1"></i> Önizle
                            </a>
                            
                            <a href="<?= ADMIN_URL ?>/qr-design-edit.php?id=<?= $template['id'] ?>" class="btn btn-sm btn-outline-primary me-2">
                                <i class="fas fa-edit me-1"></i> Düzenle
                            </a>
                            
                            <?php if (!$template['is_default']): ?>
                                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                    <button type="submit" name="set_default" class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-check-circle me-1"></i> Varsayılan Yap
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- QR Kodu Oluşturma Bilgi Kartı -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">QR Kodlarını Yönet</h5>
    </div>
    <div class="card-body">
        <p class="card-text">
            Masalar için QR kodlarını oluşturmak ve indirmek için <a href="<?= ADMIN_URL ?>/tables.php">Masalar</a> sayfasına gidin.
            Her masa için ayrı QR kod oluşturabilir ve bu kodları yazdırabilirsiniz.
        </p>
        
        <div class="alert alert-info mt-3">
            <h6><i class="fas fa-info-circle me-2"></i> QR Kodlarını Kullanma</h6>
            <ol class="mb-0">
                <li>QR kodları masalara yerleştirin (masa standları, yapıştırma, laminasyon vs. ile).</li>
                <li>Müşteriler QR kodları telefonlarıyla tarayarak menüye erişebilir.</li>
                <li>Her masa için benzersiz QR kodu, masaya özel sipariş takibi sağlar.</li>
                <li>QR kodları sayesinde müşterileriniz sipariş verebilir, ödeme yapabilir veya garson çağırabilir.</li>
            </ol>
        </div>
    </div>
</div>

<!-- QR Menü Şablonu Açıklamaları -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Şablon Özelleştirme</h5>
    </div>
    <div class="card-body">
        <p class="card-text">
            QR menü şablonları, menünüzün tasarımını ve görünümünü belirler. 
            Şablonları düzenleyerek renkleri, fontları, başlıkları ve diğer görsel öğeleri 
            markanıza uygun şekilde değiştirebilirsiniz.
        </p>
        
        <div class="alert alert-warning mt-3">
            <h6><i class="fas fa-exclamation-triangle me-2"></i> Önemli Hatırlatma</h6>
            <p class="mb-0">
                Şablonlarda yapılan değişiklikler tüm masalarda anında etkili olur. Müşterilerinizin 
                daha iyi bir deneyim yaşaması için şablon değişikliklerini mümkünse sakin saatlerde yapmanızı öneririz.
            </p>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>