<?php
/**
 * Admin Panel - Kategori Düzenleme
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

// Kategori ID'si
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($categoryId <= 0) {
    setFlashMessage('error', 'Geçersiz kategori ID\'si.');
    redirect(ADMIN_URL . '/categories.php');
}

// Kategori bilgilerini getir
$category = dbQuerySingle("SELECT * FROM categories WHERE id = ?", [$categoryId]);

if (!$category) {
    setFlashMessage('error', 'Kategori bulunamadı.');
    redirect(ADMIN_URL . '/categories.php');
}

// Sayfa başlığı
$pageTitle = 'Kategori Düzenle: ' . $category['name'];

// Kategori güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'], $_POST['csrf_token'])) {
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
        } elseif ($parentId === $categoryId) {
            setFlashMessage('error', 'Bir kategori kendisinin üst kategorisi olamaz.');
        } else {
            // Döngüsel hiyerarşi kontrolü
            $isLoop = false;
            if ($parentId) {
                $currentParent = $parentId;
                $parents = [];
                
                while ($currentParent && !$isLoop) {
                    if (in_array($currentParent, $parents)) {
                        $isLoop = true;
                        break;
                    }
                    
                    $parents[] = $currentParent;
                    
                    $parent = dbQuerySingle("SELECT parent_id FROM categories WHERE id = ?", [$currentParent]);
                    $currentParent = $parent ? $parent['parent_id'] : null;
                    
                    // Eğer kendi ID'si bu döngüde çıkarsa hiyerarşide döngü var demektir
                    if ($currentParent === $categoryId) {
                        $isLoop = true;
                    }
                }
            }
            
            if ($isLoop) {
                setFlashMessage('error', 'Döngüsel kategori hiyerarşisi oluşturulmaya çalışılıyor.');
            } else {
                // Resim yükleme
                $imageName = $category['image']; // Varsayılan olarak mevcut resim
                
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = ROOT_DIR . '/assets/uploads/categories/';
                    
                    // Dizin yoksa oluştur
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Resim yükleme
                    $uploadResult = uploadFile($_FILES['image'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif'], 2 * 1024 * 1024);
                    
                    if ($uploadResult['success']) {
                        // Eski resmi sil
                        if (!empty($category['image'])) {
                            $oldImagePath = $uploadDir . $category['image'];
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }
                        
                        $imageName = $uploadResult['filename'];
                    } else {
                        setFlashMessage('error', 'Resim yüklenirken bir hata oluştu: ' . $uploadResult['message']);
                        // Mevcut resmi koruyoruz, bu yüzden hata gösteriyoruz ama işleme devam ediyoruz
                    }
                }
                
                // Kategoriyi güncelle
                $result = dbExecute("
                    UPDATE categories SET
                        name = ?,
                        description = ?,
                        parent_id = ?,
                        sort_order = ?,
                        image = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ", [$name, $description, $parentId, $sortOrder, $imageName, $status, $categoryId]);
                
                if ($result) {
                    setFlashMessage('success', 'Kategori başarıyla güncellendi.');
                    
                    // Olay kaydı oluştur
                    $userId = $_SESSION['user_id'];
                    dbInsert("
                        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ", [$userId, 'update', 'category', $categoryId, "Kategori güncellendi: $name"]);
                    
                    // Yönlendir
                    redirect(ADMIN_URL . '/categories.php');
                } else {
                    setFlashMessage('error', 'Kategori güncellenirken bir hata oluştu.');
                }
            }
        }
    }
}

// Kategori seçenekleri için diğer kategorileri getir (kendisi ve alt kategorileri hariç)
$categoryOptions = [];

// Önce tüm kategorileri getir
$allCategories = dbQuery("SELECT id, name, parent_id FROM categories WHERE status = 1 ORDER BY sort_order, name");

// Alt kategorileri bul
function findChildCategories($categories, $parentId) {
    $children = [];
    
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $children[] = $category['id'];
            $grandChildren = findChildCategories($categories, $category['id']);
            $children = array_merge($children, $grandChildren);
        }
    }
    
    return $children;
}

// Kategori ve alt kategorileri hariç seçenekleri hazırla
$excludeIds = array_merge([$categoryId], findChildCategories($allCategories, $categoryId));

foreach ($allCategories as $option) {
    if (!in_array($option['id'], $excludeIds)) {
        $categoryOptions[] = $option;
    }
}

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<style>
    .preview-image {
        max-width: 200px;
        max-height: 200px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        margin-top: 10px;
    }
    .required-star {
        color: red;
    }
    .category-info-card {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
</style>
';

// Ekstra JS
$extraJs = '
<script>
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
        }
    });
</script>
';

// Resim URL'si
$imageUrl = !empty($category['image']) && file_exists(ROOT_DIR . '/assets/uploads/categories/' . $category['image']) 
    ? ASSETS_URL . '/uploads/categories/' . $category['image'] 
    : ADMIN_ASSETS_URL . '/img/no-image.jpg';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <!-- Kategori Bilgileri -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Kategori Bilgileri</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="<?= $imageUrl ?>" alt="<?= h($category['name']) ?>" class="img-fluid rounded" style="max-height: 200px;">
                    </div>
                    
                    <h4 class="text-center mb-3"><?= h($category['name']) ?></h4>
                    
                    <div class="category-info-card">
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Durum:</strong>
                            <span class="badge <?= $category['status'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $category['status'] ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Sıralama:</strong>
                            <?= $category['sort_order'] ?>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Üst Kategori:</strong>
                            <?php 
                            if ($category['parent_id']) {
                                $parentName = dbQuerySingle("SELECT name FROM categories WHERE id = ?", [$category['parent_id']])['name'] ?? 'Bilinmeyen';
                                echo h($parentName);
                            } else {
                                echo '<span class="text-muted">Ana Kategori</span>';
                            }
                            ?>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <strong>Ürün Sayısı:</strong>
                            <?php 
                            $productCount = dbQuerySingle("SELECT COUNT(*) as count FROM products WHERE category_id = ?", [$categoryId])['count'] ?? 0;
                            echo '<span class="badge bg-info">' . $productCount . '</span>';
                            ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($category['description'])): ?>
                        <div class="mb-3">
                            <strong>Açıklama:</strong>
                            <p class="mt-1"><?= h($category['description']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= ADMIN_URL ?>/categories.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kategorilere Dön
                        </a>
                        
                        <a href="<?= ADMIN_URL ?>/products.php?filter_category=<?= $categoryId ?>" class="btn btn-outline-info">
                            <i class="fas fa-box me-1"></i> Ürünleri Görüntüle
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Kategori Düzenleme Formu -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Kategori Bilgilerini Düzenle</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $categoryId ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Kategori Adı <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= h($category['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($category['description']) ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Üst Kategori</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">Ana Kategori</option>
                                <?php foreach ($categoryOptions as $option): ?>
                                    <option value="<?= $option['id'] ?>" <?= $category['parent_id'] === $option['id'] ? 'selected' : '' ?>>
                                        <?= h($option['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Not: Kategori kendisinin veya alt kategorilerinden birinin üst kategorisi olamaz.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sort_order" class="form-label">Sıralama</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" min="0" value="<?= $category['sort_order'] ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Kategori Görseli</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <div class="form-text">Yeni bir görsel yüklemezseniz mevcut görsel korunacaktır.</div>
                            <img id="imagePreview" class="preview-image" src="<?= $imageUrl ?>" alt="Görsel Önizleme">
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="status" name="status" <?= $category['status'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="status">Aktif</label>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" onclick="window.location.href='<?= ADMIN_URL ?>/categories.php'" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> İptal
                            </button>
                            
                            <button type="submit" name="update_category" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Değişiklikleri Kaydet
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include_once 'includes/footer.php';
?>