<?php
/**
 * Masalar Yönetimi
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// PhpQRCode kütüphanesini dahil et
require_once ROOT_DIR . '/vendor/phpqrcode/qrcode.php';

// Giriş yapılmamışsa login sayfasına yönlendir
requireLogin();

// Sadece süper admin erişebilir
if (!isSuperAdmin()) {
    redirect(ADMIN_URL . '/unauthorized.php');
}

// Sayfa başlığı
$pageTitle = 'Masalar';

// QR kod oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'], $_POST['table_id'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $tableId = (int)$_POST['table_id'];
        
        // Masa bilgilerini al
        $table = dbQuerySingle("SELECT * FROM tables WHERE id = ?", [$tableId]);
        
        if ($table) {
            // QR kodun içeriği - masa için URL
            $qrContent = BASE_URL . '/menu.php?table=' . $tableId;
            
            // QR kod dizini kontrol et
            if (!file_exists(QR_CODES_DIR)) {
                mkdir(QR_CODES_DIR, 0755, true);
            }
            
            // QR kod dosya adı
            $qrFileName = 'table_' . $tableId . '_' . time() . '.png';
            $qrFilePath = QR_CODES_DIR . '/' . $qrFileName;
            
            // QR kodu oluştur
            QRcode::png($qrContent, $qrFilePath, QR_ERROR_CORRECTION, QR_SIZE, QR_MARGIN);
            
            // Veritabanını güncelle
            $result = dbExecute("UPDATE tables SET qr_code = ? WHERE id = ?", [$qrFileName, $tableId]);
            
            if ($result) {
                setFlashMessage('success', 'QR kodu başarıyla oluşturuldu.');
            } else {
                setFlashMessage('error', 'QR kodu oluşturuldu ancak veritabanı güncellenemedi.');
            }
        } else {
            setFlashMessage('error', 'Masa bulunamadı.');
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/tables.php');
}

// Masa ekle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_table'], $_POST['name'], $_POST['capacity'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $name = sanitizeInput($_POST['name']);
        $capacity = (int)$_POST['capacity'];
        $location = sanitizeInput($_POST['location'] ?? '');
        $qrTemplate = (int)$_POST['qr_template'];
        
        if (empty($name)) {
            setFlashMessage('error', 'Masa adı gerekli.');
        } elseif ($capacity <= 0) {
            setFlashMessage('error', 'Kapasite en az 1 olmalıdır.');
        } else {
            $result = dbInsert("
                INSERT INTO tables (name, capacity, location, qr_template, status) 
                VALUES (?, ?, ?, ?, 'available')
            ", [$name, $capacity, $location, $qrTemplate]);
            
            if ($result) {
                setFlashMessage('success', 'Masa başarıyla eklendi.');
                
                // Otomatik QR kod oluştur
                $tableId = $result;
                $qrContent = BASE_URL . '/menu.php?table=' . $tableId;
                
                if (!file_exists(QR_CODES_DIR)) {
                    mkdir(QR_CODES_DIR, 0755, true);
                }
                
                $qrFileName = 'table_' . $tableId . '_' . time() . '.png';
                $qrFilePath = QR_CODES_DIR . '/' . $qrFileName;
                
                QRcode::png($qrContent, $qrFilePath, QR_ERROR_CORRECTION, QR_SIZE, QR_MARGIN);
                
                dbExecute("UPDATE tables SET qr_code = ? WHERE id = ?", [$qrFileName, $tableId]);
            } else {
                setFlashMessage('error', 'Masa eklenirken bir hata oluştu.');
            }
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/tables.php');
}

// Masa sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table'], $_POST['table_id'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $tableId = (int)$_POST['table_id'];
        
        // Masaya ait siparişleri kontrol et
        $ordersCount = dbQuerySingle("SELECT COUNT(*) as count FROM orders WHERE table_id = ?", [$tableId])['count'] ?? 0;
        
        if ($ordersCount > 0) {
            setFlashMessage('error', 'Bu masaya ait siparişler var. Önce siparişleri silmelisiniz.');
        } else {
            // Masa bilgilerini al (QR kodu silmek için)
            $table = dbQuerySingle("SELECT * FROM tables WHERE id = ?", [$tableId]);
            
            // Masayı sil
            $result = dbExecute("DELETE FROM tables WHERE id = ?", [$tableId]);
            
            if ($result) {
                // QR kodu dosyasını sil
                if ($table && !empty($table['qr_code'])) {
                    $qrFilePath = QR_CODES_DIR . '/' . $table['qr_code'];
                    if (file_exists($qrFilePath)) {
                        unlink($qrFilePath);
                    }
                }
                
                setFlashMessage('success', 'Masa başarıyla silindi.');
            } else {
                setFlashMessage('error', 'Masa silinirken bir hata oluştu.');
            }
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/tables.php');
}

// Masa durumunu güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['table_id'], $_POST['status'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $tableId = (int)$_POST['table_id'];
        $status = sanitizeInput($_POST['status']);
        
        // Geçerli durumlar
        $validStatuses = ['available', 'occupied', 'reserved', 'maintenance'];
        
        if (in_array($status, $validStatuses)) {
            $result = dbExecute("UPDATE tables SET status = ? WHERE id = ?", [$status, $tableId]);
            
            if ($result) {
                setFlashMessage('success', 'Masa durumu güncellendi.');
            } else {
                setFlashMessage('error', 'Masa durumu güncellenirken bir hata oluştu.');
            }
        } else {
            setFlashMessage('error', 'Geçersiz masa durumu.');
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/tables.php');
}

// Masaları getir
$tables = dbQuery("SELECT * FROM tables ORDER BY name");

// QR Menü şablonlarını getir
$qrTemplates = dbQuery("SELECT * FROM qr_templates WHERE status = 1 ORDER BY is_default DESC, name");

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css">
<style>
    .qr-code-image {
        max-width: 100px;
        max-height: 100px;
    }
    .status-indicator {
        width: 15px;
        height: 15px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .status-available {
        background-color: #28a745;
    }
    .status-occupied {
        background-color: #dc3545;
    }
    .status-reserved {
        background-color: #ffc107;
    }
    .status-maintenance {
        background-color: #6c757d;
    }
</style>
';

// Ekstra JS
$extraJs = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        // DataTable
        $("#tables-table").DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Turkish.json"
            },
            responsive: true
        });
        
        // QR kod önizleme
        $(".view-qr").on("click", function() {
            const qrUrl = $(this).data("qr-url");
            const tableName = $(this).data("table-name");
            
            $("#qrCodeModalLabel").text(tableName + " QR Kodu");
            $("#qrCodeImage").attr("src", qrUrl);
            $("#qrCodeDownload").attr("href", qrUrl);
            
            $("#qrCodeModal").modal("show");
        });
    });
</script>
';
?>

<div class="row">
    <div class="col-md-4">
        <!-- Masa Ekle -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Yeni Masa Ekle</h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Masa Adı</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="capacity" class="form-label">Kapasite</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" min="1" value="4" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Konum</label>
                        <input type="text" class="form-control" id="location" name="location" placeholder="Örn: Giriş Kat, Bahçe, Teras vb.">
                    </div>
                    
                    <div class="mb-3">
                        <label for="qr_template" class="form-label">QR Menü Şablonu</label>
                        <select class="form-select" id="qr_template" name="qr_template">
                            <?php foreach ($qrTemplates as $template): ?>
                                <option value="<?= $template['id'] ?>" <?= $template['is_default'] ? 'selected' : '' ?>>
                                    <?= h($template['name']) ?> <?= $template['is_default'] ? '(Varsayılan)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="add_table" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Masa Ekle
                    </button>
                </form>
            </div>
        </div>
        
        <!-- QR Kod Bilgileri -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">QR Kod Bilgileri</h5>
            </div>
            <div class="card-body">
                <p>
                    Her masa için otomatik QR kod oluşturulur. QR kodları yazdırarak 
                    masalara yerleştirebilirsiniz. Müşteriler bu kodları tarayarak 
                    menüye ulaşabilir ve sipariş verebilir.
                </p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> QR kodları indirmek için 
                    tablodaki "QR Kod" sütunundaki görüntüleme simgesine tıklayın.
                </div>
                
                <p>
                    <strong>QR Kodları Nasıl Kullanılır?</strong>
                </p>
                <ol>
                    <li>QR kodları yazdırın</li>
                    <li>Masa standlarına yerleştirin veya masaya yapıştırın</li>
                    <li>Müşteriler QR kodu telefonla tarayarak menüye erişir</li>
                    <li>Siparişler doğrudan mutfağa ve garsona iletilir</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Masalar Tablosu -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Masa Listesi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tables-table" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Masa Adı</th>
                                <th>Kapasite</th>
                                <th>Konum</th>
                                <th>Durum</th>
                                <th>QR Kod</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tables)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Henüz masa bulunmuyor.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tables as $table): ?>
                                    <tr>
                                        <td><?= h($table['name']) ?></td>
                                        <td><?= $table['capacity'] ?> Kişilik</td>
                                        <td><?= empty($table['location']) ? '-' : h($table['location']) ?></td>
                                        <td>
                                            <?php
                                            $statusText = '';
                                            $statusClass = '';
                                            
                                            switch ($table['status']) {
                                                case 'available':
                                                    $statusText = 'Boş';
                                                    $statusClass = 'status-available';
                                                    break;
                                                case 'occupied':
                                                    $statusText = 'Dolu';
                                                    $statusClass = 'status-occupied';
                                                    break;
                                                case 'reserved':
                                                    $statusText = 'Rezerve';
                                                    $statusClass = 'status-reserved';
                                                    break;
                                                case 'maintenance':
                                                    $statusText = 'Bakımda';
                                                    $statusClass = 'status-maintenance';
                                                    break;
                                            }
                                            ?>
                                            <span class="status-indicator <?= $statusClass ?>"></span>
                                            <?= $statusText ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($table['qr_code']) && file_exists(QR_CODES_DIR . '/' . $table['qr_code'])): ?>
                                                <button type="button" class="btn btn-sm btn-info view-qr" 
                                                    data-qr-url="<?= ASSETS_URL ?>/uploads/qrcodes/<?= h($table['qr_code']) ?>"
                                                    data-table-name="<?= h($table['name']) ?>">
                                                    <i class="fas fa-qrcode me-1"></i> Görüntüle
                                                </button>
                                            <?php else: ?>
                                                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="table_id" value="<?= $table['id'] ?>">
                                                    <button type="submit" name="generate_qr" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-qrcode me-1"></i> Oluştur
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $table['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                    İşlemler
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $table['id'] ?>">
                                                    <li>
                                                        <a class="dropdown-item" href="<?= ADMIN_URL ?>/table-edit.php?id=<?= $table['id'] ?>">
                                                            <i class="fas fa-edit me-1"></i> Düzenle
                                                        </a>
                                                    </li>
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    
                                                    <!-- Durum güncelleme -->
                                                    <li class="dropdown-header">Durum Değiştir</li>
                                                    
                                                    <li>
                                                        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="table_id" value="<?= $table['id'] ?>">
                                                            <input type="hidden" name="status" value="available">
                                                            <button type="submit" name="update_status" class="dropdown-item <?= $table['status'] === 'available' ? 'active' : '' ?>">
                                                                <span class="status-indicator status-available"></span> Boş
                                                            </button>
                                                        </form>
                                                    </li>
                                                    
                                                    <li>
                                                        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="table_id" value="<?= $table['id'] ?>">
                                                            <input type="hidden" name="status" value="occupied">
                                                            <button type="submit" name="update_status" class="dropdown-item <?= $table['status'] === 'occupied' ? 'active' : '' ?>">
                                                                <span class="status-indicator status-occupied"></span> Dolu
                                                            </button>
                                                        </form>
                                                    </li>
                                                    
                                                    <li>
                                                        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="table_id" value="<?= $table['id'] ?>">
                                                            <input type="hidden" name="status" value="reserved">
                                                            <button type="submit" name="update_status" class="dropdown-item <?= $table['status'] === 'reserved' ? 'active' : '' ?>">
                                                                <span class="status-indicator status-reserved"></span> Rezerve
                                                            </button>
                                                        </form>
                                                    </li>
                                                    
                                                    <li>
                                                        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="table_id" value="<?= $table['id'] ?>">
                                                            <input type="hidden" name="status" value="maintenance">
                                                            <button type="submit" name="update_status" class="dropdown-item <?= $table['status'] === 'maintenance' ? 'active' : '' ?>">
                                                                <span class="status-indicator status-maintenance"></span> Bakımda
                                                            </button>
                                                        </form>
                                                    </li>
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    
                                                    <li>
                                                        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" onsubmit="return confirm('Bu masayı silmek istediğinize emin misiniz?');">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="table_id" value="<?= $table['id'] ?>">
                                                            <button type="submit" name="delete_table" class="dropdown-item text-danger">
                                                                <i class="fas fa-trash-alt me-1"></i> Sil
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Kod Görüntüleme Modalı -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrCodeModalLabel">QR Kod</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body text-center">
                <img id="qrCodeImage" src="" alt="QR Kod" class="img-fluid mb-3" style="max-width: 250px;">
                <p class="mb-0">QR kodu yazdırmak veya kaydetmek için indirebilirsiniz.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <a id="qrCodeDownload" href="" download class="btn btn-primary">
                    <i class="fas fa-download me-1"></i> İndir
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>