<?php
/**
 * Siparişler Sayfası
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

// Sayfa başlığı
$pageTitle = 'Siparişler';

// Sipariş filtresi
$filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$filterTable = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;
$filterDateStart = isset($_GET['date_start']) ? sanitizeInput($_GET['date_start']) : '';
$filterDateEnd = isset($_GET['date_end']) ? sanitizeInput($_GET['date_end']) : '';

// Siparişleri getir
$query = "
    SELECT o.*, t.name as table_name 
    FROM orders o 
    JOIN tables t ON o.table_id = t.id 
    WHERE 1=1
";
$params = [];

// Filtreleri ekle
if (!empty($filterStatus)) {
    $query .= " AND o.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterTable)) {
    $query .= " AND o.table_id = ?";
    $params[] = $filterTable;
}

if (!empty($filterDateStart)) {
    $query .= " AND DATE(o.created_at) >= ?";
    $params[] = $filterDateStart;
}

if (!empty($filterDateEnd)) {
    $query .= " AND DATE(o.created_at) <= ?";
    $params[] = $filterDateEnd;
}

// Sıralama
$query .= " ORDER BY o.created_at DESC";

// Siparişleri çek
$orders = dbQuery($query, $params);

// Tüm masaları getir (filtre için)
$tables = dbQuery("SELECT id, name FROM tables ORDER BY name");

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css">
<style>
    .order-filters {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .actions-column {
        width: 140px;
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
        $("#orders-table").DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Turkish.json"
            },
            order: [[5, "desc"]],
            responsive: true,
            columnDefs: [
                { orderable: false, targets: [6] }
            ]
        });
        
        // Reset filtre butonu
        $("#resetFilters").on("click", function() {
            window.location.href = "' . ADMIN_URL . '/orders.php";
        });
        
        // Sipariş durumu güncelleme
        $(".update-status").on("click", function() {
            const orderId = $(this).data("order-id");
            const statusValue = $(this).data("status");
            
            if (confirm("Sipariş durumu güncellenecek. Onaylıyor musunuz?")) {
                $.ajax({
                    url: "ajax/update-order-status.php",
                    type: "POST",
                    data: JSON.stringify({
                        order_id: orderId,
                        status: statusValue,
                        csrf_token: $("#csrf_token").val()
                    }),
                    contentType: "application/json",
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            showToast("Sipariş durumu güncellendi.", "success");
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast("Hata: " + response.message, "error");
                        }
                    },
                    error: function() {
                        showToast("Bir hata oluştu.", "error");
                    }
                });
            }
        });
    });
</script>
';
?>

<!-- Filtreler -->
<div class="order-filters">
    <form method="get" action="<?= $_SERVER['PHP_SELF'] ?>">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Durum</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Beklemede</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Onaylandı</option>
                    <option value="preparing" <?= $filterStatus === 'preparing' ? 'selected' : '' ?>>Hazırlanıyor</option>
                    <option value="ready" <?= $filterStatus === 'ready' ? 'selected' : '' ?>>Hazır</option>
                    <option value="delivered" <?= $filterStatus === 'delivered' ? 'selected' : '' ?>>Teslim Edildi</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Tamamlandı</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>İptal Edildi</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="table_id" class="form-label">Masa</label>
                <select class="form-select" id="table_id" name="table_id">
                    <option value="">Tümü</option>
                    <?php foreach ($tables as $table): ?>
                        <option value="<?= $table['id'] ?>" <?= $filterTable === $table['id'] ? 'selected' : '' ?>>
                            <?= h($table['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="date_start" class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" id="date_start" name="date_start" value="<?= $filterDateStart ?>">
            </div>
            
            <div class="col-md-3">
                <label for="date_end" class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" id="date_end" name="date_end" value="<?= $filterDateEnd ?>">
            </div>
            
            <div class="col-12 text-end">
                <button type="button" id="resetFilters" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-undo me-1"></i> Sıfırla
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-1"></i> Filtrele
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Siparişler Tablosu -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Sipariş Listesi</h5>
        <a href="<?= ADMIN_URL ?>/order-new.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Yeni Sipariş
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="orders-table" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Sipariş Kodu</th>
                        <th>Masa</th>
                        <th>Toplam</th>
                        <th>Durum</th>
                        <th>Not</th>
                        <th>Tarih</th>
                        <th class="actions-column">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Henüz sipariş bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <a href="<?= ADMIN_URL ?>/order-detail.php?id=<?= $order['id'] ?>">
                                        <?= h($order['order_code']) ?>
                                    </a>
                                </td>
                                <td><?= h($order['table_name']) ?></td>
                                <td><?= formatCurrency($order['total_amount']) ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    
                                    switch ($order['status']) {
                                        case 'pending':
                                            $statusClass = 'bg-warning text-dark';
                                            $statusText = 'Beklemede';
                                            break;
                                        case 'confirmed':
                                            $statusClass = 'bg-info text-dark';
                                            $statusText = 'Onaylandı';
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
                                        case 'completed':
                                            $statusClass = 'bg-dark';
                                            $statusText = 'Tamamlandı';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'bg-danger';
                                            $statusText = 'İptal Edildi';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($order['note'])): ?>
                                        <span data-bs-toggle="tooltip" title="<?= h($order['note']) ?>">
                                            <i class="fas fa-comment-dots"></i> Not var
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($order['created_at']) ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $order['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            İşlemler
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $order['id'] ?>">
                                            <li>
                                                <a class="dropdown-item" href="<?= ADMIN_URL ?>/order-detail.php?id=<?= $order['id'] ?>">
                                                    <i class="fas fa-eye me-1"></i> Detay
                                                </a>
                                            </li>
                                            
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <li>
                                                    <a class="dropdown-item update-status" href="#" data-order-id="<?= $order['id'] ?>" data-status="confirmed">
                                                        <i class="fas fa-check-circle me-1"></i> Onayla
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['status'] === 'ready'): ?>
                                                <li>
                                                    <a class="dropdown-item update-status" href="#" data-order-id="<?= $order['id'] ?>" data-status="delivered">
                                                        <i class="fas fa-truck me-1"></i> Teslim Edildi
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['status'] === 'delivered'): ?>
                                                <li>
                                                    <a class="dropdown-item update-status" href="#" data-order-id="<?= $order['id'] ?>" data-status="completed">
                                                        <i class="fas fa-flag-checkered me-1"></i> Tamamla
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                                                <li>
                                                    <a class="dropdown-item update-status" href="#" data-order-id="<?= $order['id'] ?>" data-status="cancelled">
                                                        <i class="fas fa-ban me-1"></i> İptal Et
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <li>
                                                <a class="dropdown-item" href="<?= ADMIN_URL ?>/order-edit.php?id=<?= $order['id'] ?>">
                                                    <i class="fas fa-edit me-1"></i> Düzenle
                                                </a>
                                            </li>
                                            
                                            <li>
                                                <a class="dropdown-item" href="<?= ADMIN_URL ?>/order-print.php?id=<?= $order['id'] ?>" target="_blank">
                                                    <i class="fas fa-print me-1"></i> Yazdır
                                                </a>
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

<!-- AJAX için CSRF token -->
<input type="hidden" id="csrf_token" value="<?= generateCSRFToken() ?>">

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>