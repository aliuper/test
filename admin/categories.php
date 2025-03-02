<?php
/**
 * Admin Panel - Kategori Yönetimi
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
$pageTitle = 'Kategori Yönetimi';

// Kategori ekle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $sortOrder = !empty($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Zorunlu alanları kontrol et
        if (empty($name)) {
            setFlashMessage('error', 'Kategori adı zorunludur.');
        } else {
            // Resim yükleme
            $imageName = '';
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = ROOT_DIR . '/assets/uploads/categories/';
                
                // Dizin yoksa oluştur
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Resim yükleme
                $uploadResult = uploadFile($_FILES['image'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif'], 2 * 1024 * 1024);
                
                if ($uploadResult['success']) {
                    $imageName = $uploadResult['filename'];
                } else {
                    setFlashMessage('error', 'Resim yüklenirken bir hata oluştu: ' . $uploadResult['message']);
                    $imageName = '';
                }
            }
            
            // Kategoriyi ekle
            $result = dbInsert("
                INSERT INTO categories (name, description, parent_id, sort_order, image, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [$name, $description, $parentId, $sortOrder, $imageName, $status]);
            
            if ($result) {
                setFlashMessage('success', 'Kategori başarıyla eklendi.');
                
                // Olay kaydı oluştur
                $userId = $_SESSION['user_id'];
                dbInsert("
                    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ", [$userId, 'create', 'category', $result, "Yeni kategori eklendi: $name"]);
                
                // Sayfayı yenile
                redirect(ADMIN_URL . '/categories.php');
            } else {
                setFlashMessage('error', 'Kategori eklenirken bir hata oluştu.');
            }
        }
    }
}

// Kategori durumunu güncelle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['category_id'], $_POST['status'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $categoryId = (int)$_POST['category_id'];
        $status = (int)$_POST['status'] ? 1 : 0;
        
        // Kategori güncelle
        $result = dbExecute("UPDATE categories SET status = ? WHERE id = ?", [$status, $categoryId]);
        
        if ($result) {
            $statusText = $status ? 'aktifleştirildi' : 'devre dışı bırakıldı';
            setFlashMessage('success', 'Kategori durumu başarıyla güncellendi.');
            
            // Olay kaydı oluştur
            $userId = $_SESSION['user_id'];
            $categoryName = dbQuerySingle("SELECT name FROM categories WHERE id = ?", [$categoryId])['name'] ?? 'Bilinmeyen Kategori';
            dbInsert("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$userId, 'update_status', 'category', $categoryId, "Kategori durumu $statusText: $categoryName"]);
        } else {
            setFlashMessage('error', 'Kategori durumu güncellenirken bir hata oluştu.');
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/categories.php');
}

// Kategori sil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'], $_POST['category_id'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $categoryId = (int)$_POST['category_id'];
        
        // Bu kategoride ürün var mı kontrol et
        $productCount = dbQuerySingle("SELECT COUNT(*) as count FROM products WHERE category_id = ?", [$categoryId])['count'] ?? 0;
        
        // Alt kategorileri kontrol et
        $subCategoryCount = dbQuerySingle("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?", [$categoryId])['count'] ?? 0;
        
        if ($productCount > 0) {
            setFlashMessage('error', 'Bu kategoride ürünler var. Önce ürünleri silmeli veya başka bir kategoriye taşımalısınız.');
        } elseif ($subCategoryCount > 0) {
            setFlashMessage('error', 'Bu kategorinin alt kategorileri var. Önce alt kategorileri silmelisiniz.');
        } else {
            // Kategoriyi getir (resim dosyasını silmek için)
            $category = dbQuerySingle("SELECT * FROM categories WHERE id = ?", [$categoryId]);
            
            if (!$category) {
                setFlashMessage('error', 'Kategori bulunamadı.');
            } else {
                // Kategoriyi sil
                $result = dbExecute("DELETE FROM categories WHERE id = ?", [$categoryId]);
                
                if ($result) {
                    // Resim dosyasını sil
                    if (!empty($category['image'])) {
                        $imagePath = ROOT_DIR . '/assets/uploads/categories/' . $category['image'];
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                    }
                    
                    setFlashMessage('success', 'Kategori başarıyla silindi.');
                    
                    // Olay kaydı oluştur
                    $userId = $_SESSION['user_id'];
                    dbInsert("
                        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ", [$userId, 'delete', 'category', $categoryId, "Kategori silindi: {$category['name']}"]);
                } else {
                    setFlashMessage('error', 'Kategori silinirken bir hata oluştu.');
                }
            }
        }
    }
    
    // Sayfayı yenile
    redirect(ADMIN_URL . '/categories.php');
}

// Kategorileri getir
$categories = dbQuery("
    SELECT c.*, 
           parent.name as parent_name,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c
    LEFT JOIN categories parent ON c.parent_id = parent.id
    ORDER BY c.sort_order, c.name
");

// Kategori seçenekleri için kategorileri getir
$categoryOptions = dbQuery("SELECT id, name FROM categories WHERE status = 1 ORDER BY sort_order, name");

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap5.min.css">
<style>
    .category-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #eee;
    }
    .preview-image {
        max-width: 100px;
        max-height: 100px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        margin-top: 10px;
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
        $("#categories-table").DataTable({
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Turkish.json"
            },
            responsive: true,
            order: [[3, "asc"]],
            columnDefs: [
                { orderable: false, targets: [1, 6] }
            ]
        });
        
        // Resim önizleme
        document.getElementById("image").addEventListener("change", function() {
            const preview = document.getElementById("imagePreview");
            const file = this.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = "block";
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.src = "#";
                preview.style.display = "none";
            }
        });
        
        // Silme onayı
        $(".delete-category").on("click", function(e) {
            e.preventDefault();
            
            const categoryName = $(this).data("category-name");
            
            if (confirm(categoryName + " kategorisini silmek istediğinize emin misiniz? Bu işlem geri alınamaz!")) {
                $(this).closest("form").submit();
            }
        });
    });
</script>
';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <!-- Kategori Ekle -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Yeni Kategori Ekle</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Kategori Adı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Üst Kategori</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">Ana Kategori</option>
                                <?php foreach ($categoryOptions as $option): ?>
                                    <option value="<?= $option['id'] ?>"><?= h($option['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Sıralama</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" min="0" value="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Kategori Görseli</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <img id="imagePreview" class="preview-image" style="display: none;" src="#" alt="Görsel Önizleme">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="status" name="status" checked>
                            <label class="form-check-label" for="status">Aktif</label>
                        </div>
                        
                        <button type="submit" name="add_category" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Kategori Ekle
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Kategori Listesi -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Kategori Listesi</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="categories-table" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Görsel</th>
                                    <th>Kategori Adı</th>
                                    <th>Sıralama</th>
                                    <th>Üst Kategori</th>
                                    <th>Ürün Sayısı</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?= $category['id'] ?></td>
                                        <td>
                                            <?php
                                            $imageUrl = !empty($category['image']) && file_exists(ROOT_DIR . '/assets/uploads/categories/' . $category['image']) 
                                                ? ASSETS_URL . '/uploads/categories/' . $category['image'] 
                                                : ADMIN_ASSETS_URL . '/img/no-image.jpg';
                                            ?>
                                            <img src="<?= $imageUrl ?>" alt="<?= h($category['name']) ?>" class="category-image">
                                        </td>
                                        <td>
                                            <?= h($category['name']) ?>
                                            <?php if (!empty($category['description'])): ?>
                                                <i class="fas fa-info-circle ms-1" title="<?= h($category['description']) ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $category['sort_order'] ?></td>
                                        <td><?= !empty($category['parent_name']) ? h($category['parent_name']) : '<span class="text-muted">-</span>' ?></td>
                                        <td>
                                            <span class="badge bg-info"><?= $category['product_count'] ?></span>
                                        </td>
                                        <td>
                                            <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $category['status'] ? 0 : 1 ?>">
                                                
                                                <button type="submit" name="update_status" class="btn btn-sm <?= $category['status'] ? 'btn-success' : 'btn-danger' ?>">
                                                    <i class="fas <?= $category['status'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                                                    <?= $category['status'] ? 'Aktif' : 'Pasif' ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?= ADMIN_URL ?>/category-edit.php?id=<?= $category['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                    
                                                    <button type="button" class="btn btn-sm btn-danger delete-category" data-category-name="<?= h($category['name']) ?>">
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
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>