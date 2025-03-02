<?php
/**
 * Admin Panel Dashboard
 */

// Gerekli dosyaları dahil et
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

// Giriş yapılmamışsa login sayfasına yönlendir
requireLogin();

// Sayfa başlığı
$pageTitle = 'Gösterge Paneli';

// İstatistikleri getir
$totalOrders = dbQuerySingle("SELECT COUNT(*) as total FROM orders", [])['total'] ?? 0;
$dailyOrders = dbQuerySingle("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()", [])['total'] ?? 0;
$pendingOrders = dbQuerySingle("SELECT COUNT(*) as total FROM orders WHERE status IN ('pending', 'confirmed', 'preparing')", [])['total'] ?? 0;
$totalProducts = dbQuerySingle("SELECT COUNT(*) as total FROM products WHERE status = 1", [])['total'] ?? 0;
$totalTables = dbQuerySingle("SELECT COUNT(*) as total FROM tables", [])['total'] ?? 0;
$occupiedTables = dbQuerySingle("SELECT COUNT(*) as total FROM tables WHERE status = 'occupied'", [])['total'] ?? 0;

// Son siparişleri getir
$recentOrders = dbQuery("
    SELECT o.*, t.name as table_name 
    FROM orders o 
    JOIN tables t ON o.table_id = t.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");

// Popüler ürünleri getir
$popularProducts = dbQuery("
    SELECT p.id, p.name, p.price, p.image, 
           COUNT(oi.id) as order_count
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    GROUP BY p.id
    ORDER BY order_count DESC
    LIMIT 5
");

// Header'ı dahil et
include_once 'includes/header.php';

// Grafik çizimleri için ekstra JS
$extraJs = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
<script src="' . ADMIN_ASSETS_URL . '/js/dashboard.js"></script>
';
?>

<!-- İstatistik Kartları -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-title">Bugünkü Siparişler</div>
            <div class="stat-value"><?= $dailyOrders ?></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-title">Bekleyen Siparişler</div>
            <div class="stat-value"><?= $pendingOrders ?></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-utensils"></i>
            </div>
            <div class="stat-title">Aktif Ürünler</div>
            <div class="stat-value"><?= $totalProducts ?></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-chair"></i>
            </div>
            <div class="stat-title">Dolu Masalar</div>
            <div class="stat-value"><?= $occupiedTables ?> / <?= $totalTables ?></div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Satış Grafiği -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Günlük Satış Grafiği</h5>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        Son 7 Gün
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                        <li><a class="dropdown-item" href="#" data-range="7">Son 7 Gün</a></li>
                        <li><a class="dropdown-item" href="#" data-range="30">Son 30 Gün</a></li>
                        <li><a class="dropdown-item" href="#" data-range="90">Son 3 Ay</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <canvas id="salesChart" width="400" height="200"></canvas>
                <div id="salesChartLoading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Masa Durumları -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Masa Durumları</h5>
            </div>
            <div class="card-body">
                <canvas id="tableStatusChart" width="400" height="200"></canvas>
                <div id="tableChartLoading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Son Siparişler -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Son Siparişler</h5>
                <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-sm btn-outline-primary">
                    Tümünü Gör
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Sipariş Kodu</th>
                                <th>Masa</th>
                                <th>Toplam</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Henüz sipariş bulunmuyor.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOrders as $order): ?>
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
                                                    $statusClass = 'bg-warning';
                                                    $statusText = 'Beklemede';
                                                    break;
                                                case 'confirmed':
                                                    $statusClass = 'bg-info';
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
                                            <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                        </td>
                                        <td><?= formatDate($order['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Popüler Ürünler -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Popüler Ürünler</h5>
                <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-sm btn-outline-primary">
                    Tümünü Gör
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($popularProducts)): ?>
                    <p class="text-center">Henüz ürün satışı bulunmuyor.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($popularProducts as $product): ?>
                            <a href="<?= ADMIN_URL ?>/product-edit.php?id=<?= $product['id'] ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                                <div class="me-3">
                                    <?php if (!empty($product['image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $product['image'])): ?>
                                        <img src="<?= ASSETS_URL ?>/uploads/products/<?= h($product['image']) ?>" alt="<?= h($product['name']) ?>" class="img-fluid rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                            <i class="fas fa-utensils text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?= h($product['name']) ?></h6>
                                    <small class="text-muted"><?= formatCurrency($product['price']) ?></small>
                                </div>
                                <div>
                                    <span class="badge bg-primary rounded-pill"><?= $product['order_count'] ?> sipariş</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- AJAX için CSRF token -->
<input type="hidden" id="csrf_token" value="<?= generateCSRFToken() ?>">

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>