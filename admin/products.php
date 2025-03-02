<?php
/**
 * Admin Panel - Ürün Yönetimi
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
$pageTitle = 'Ürün Yönetimi';

// Ürün durumunu güncelle (ajax)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['product_id'], $_POST['status'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $productId = (int)$_POST['product_id'];
        $status = (int)$_POST['status'] ? 1 : 0;
        
        // Ürünü güncelle
        $result = dbExecute("UPDATE products SET status = ? WHERE id = ?", [$status, $productId]);
        
        if ($result) {
            $statusText = $status ? 'aktifleştirildi' : 'devre dışı bırakıldı';
            setFlashMessage('success', 'Ürün durumu başarıyla güncellendi.');
            
            // Olay kaydı oluştur
            $userId = $_SESSION['user_id'];
            $productName = dbQuerySingle("SELECT name FROM products WHERE id = ?", [$productId])['name'] ?? 'Bilinmeyen Ürün';
            dbInsert("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$userId, 'update_status', 'product', $productId, "Ürün durumu $statusText: $productName"]);
        } else {
            setFlashMessage('error', 'Ürün durumu güncellenirken bir hata oluştu.');
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/products.php');
}

// Ürün sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'], $_POST['product_id'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $productId = (int)$_POST['product_id'];
        
        // Bu ürünün siparişlerde kullanılıp kullanılmadığını kontrol et
        $orderCount = dbQuerySingle("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?", [$productId])['count'] ?? 0;
        
        if ($orderCount > 0) {
            setFlashMessage('error', 'Bu ürün daha önce sipariş edilmiş. Silmek yerine devre dışı bırakabilirsiniz.');
        } else {
            // Ürünü getir (resim dosyasını silmek için)
            $product = dbQuerySingle("SELECT * FROM products WHERE id = ?", [$productId]);
            
            if (!$product) {
                setFlashMessage('error', 'Ürün bulunamadı.');
            } else {
                // Ürünü sil
                $result = dbExecute("DELETE FROM products WHERE id = ?", [$productId]);
                
                if ($result) {
                    // Resim dosyasını sil
                    if (!empty($product['image'])) {
                        $imagePath = ROOT_DIR . '/assets/uploads/products/' . $product['image'];
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                    }
                    
                    setFlashMessage('success', 'Ürün başarıyla silindi.');
                    
                    // Olay kaydı oluştur
                    $userId = $_SESSION['user_id'];
                    dbInsert("
                        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ", [$userId, 'delete', 'product', $productId, "Ürün silindi: {$product['name']}"]);
                } else {
                    setFlashMessage('error', 'Ürün silinirken bir hata oluştu.');
                }
            }
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/products.php');
}

// Kategorileri getir
$categories = dbQuery("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order, name");

// Ürünleri getir
$products = dbQuery("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.id DESC
");

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css">
<style>
    .product-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #eee;
    }
    .product-status-badge {
        width: 100px;
    }
    .discount-price {
        color: #28a745;
        font-weight: bold;
    }
    .original-price {
        text-decoration: line-through;
        color: #dc3545;
        font-size: 0.85rem;
    }
    .discount-percentage {
        background-color: #dc3545;
        color: white;
        font-size: 0.75rem;
        padding: 2px 5px;
        border-radius: 3px;
        margin-left: 5px;
    }
    .featured-star {
        color: #ffc107;
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
        $("#products-table").DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Turkish.json"
            },
            responsive: true,
            order: [[0, "desc"]],
            columnDefs: [
                { orderable: false, targets: [1, 7] }
            ]
        });
        
        // Silme onayı
        $(".delete-product").on("click", function(e) {
            e.preventDefault();
            
            const productName = $(this).data("product-name");
            
            if (confirm(productName + " ürününü silmek istediğinize emin misiniz? Bu işlem geri alınamaz!")) {
                $(this).closest("form").submit();
            }
        });
    });
</script>
';
?>

<div class="container-fluid">
    <!-- Üst Butonlar -->
    <div class="d-flex justify-content-end mb-3">
        <a href="<?= ADMIN_URL ?>/product-new.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Yeni Ürün Ekle
        </a>
    </div>
    
    <!-- Ürün Listesi -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Ürün Listesi</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="products-table" class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Görsel</th>
                            <th>Ürün Adı</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th>Durum</th>
                            <th>Eklenme Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td>
                                    <?php
                                    $imageUrl = !empty($product['image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $product['image']) 
                                        ? ASSETS_URL . '/uploads/products/' . $product['image'] 
                                        : ADMIN_ASSETS_URL . '/img/no-image.jpg';
                                    ?>
                                    <img src="<?= $imageUrl ?>" alt="<?= h($product['name']) ?>" class="product-image">
                                </td>
                                <td>
                                    <?= h($product['name']) ?>
                                    <?php if ($product['featured']): ?>
                                        <i class="fas fa-star featured-star ms-1" title="Öne Çıkan Ürün"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($product['category_name'] ?? 'Kategorisiz') ?></td>
                                <td>
                                    <?php if (!empty($product['discount_price']) && $product['discount_price'] < $product['price']): ?>
                                        <div class="discount-price">
                                            <?= formatCurrency($product['discount_price']) ?>
                                        </div>
                                        <div>
                                            <span class="original-price"><?= formatCurrency($product['price']) ?></span>
                                            <span class="discount-percentage">
                                                %<?= round((1 - $product['discount_price'] / $product['price']) * 100) ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <?= formatCurrency($product['price']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $product['status'] ? 0 : 1 ?>">
                                        
                                        <button type="submit" name="update_status" class="btn btn-sm product-status-badge <?= $product['status'] ? 'btn-success' : 'btn-danger' ?>">
                                            <i class="fas <?= $product['status'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                                            <?= $product['status'] ? 'Aktif' : 'Pasif' ?>
                                        </button>
                                    </form>
                                </td>
                                <td><?= formatDate($product['created_at']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?= ADMIN_URL ?>/product-edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                            
                                            <button type="button" class="btn btn-sm btn-danger delete-product" data-product-name="<?= h($product['name']) ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>