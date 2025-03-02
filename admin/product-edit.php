<?php
/**
 * Admin Panel - Ürün Düzenleme
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

// Ürün ID'si
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($productId <= 0) {
    setFlashMessage('error', 'Geçersiz ürün ID\'si.');
    redirect(ADMIN_URL . '/products.php');
}

// Ürün bilgilerini getir
$product = dbQuerySingle("SELECT * FROM products WHERE id = ?", [$productId]);

if (!$product) {
    setFlashMessage('error', 'Ürün bulunamadı.');
    redirect(ADMIN_URL . '/products.php');
}

// Sayfa başlığı
$pageTitle = 'Ürün Düzenle: ' . $product['name'];

// Kategorileri getir
$categories = dbQuery("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order, name");

// Ürün güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        // Form verilerini al
        $productName = sanitizeInput($_POST['name']);
        $categoryId = (int)$_POST['category_id'];
        $price = (float)$_POST['price'];
        $discountPrice = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $description = sanitizeInput($_POST['description'] ?? '');
        $preparationTime = !empty($_POST['preparation_time']) ? (int)$_POST['preparation_time'] : null;
        $allergens = sanitizeInput($_POST['allergens'] ?? '');
        $ingredients = sanitizeInput($_POST['ingredients'] ?? '');
        $calories = !empty($_POST['calories']) ? (int)$_POST['calories'] : null;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Zorunlu alanları kontrol et
        if (empty($productName) || $price <= 0) {
            setFlashMessage('error', 'Ürün adı ve fiyat zorunludur.');
        } else {
            // Ürün resmi yükleme
            $imageName = $product['image']; // Varsayılan olarak mevcut resim
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = ROOT_DIR . '/assets/uploads/products/';
                
                // Dizin yoksa oluştur
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Resim yükleme
                $uploadResult = uploadFile($_FILES['image'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024);
                
                if ($uploadResult['success']) {
                    // Eski resmi sil
                    if (!empty($product['image'])) {
                        $oldImagePath = $uploadDir . $product['image'];
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
            
            // Ürünü veritabanında güncelle
            $result = dbExecute("
                UPDATE products SET
                    category_id = ?,
                    name = ?,
                    description = ?,
                    price = ?,
                    discount_price = ?,
                    image = ?,
                    preparation_time = ?,
                    allergens = ?,
                    ingredients = ?,
                    calories = ?,
                    featured = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ", [
                $categoryId, $productName, $description, $price, $discountPrice,
                $imageName, $preparationTime, $allergens, $ingredients,
                $calories, $featured, $status, $productId
            ]);
            
            if ($result) {
                setFlashMessage('success', 'Ürün başarıyla güncellendi.');
                
                // Olay kaydı oluştur
                $userId = $_SESSION['user_id'];
                dbInsert("
                    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ", [$userId, 'update', 'product', $productId, "Ürün güncellendi: $productName"]);
                
                // Yönlendir
                redirect(ADMIN_URL . '/products.php');
            } else {
                setFlashMessage('error', 'Ürün güncellenirken bir hata oluştu.');
            }
        }
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
    .product-info-card {
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
    
    // İndirimli fiyat kontrolü
    document.getElementById("discountPrice").addEventListener("input", function() {
        const price = parseFloat(document.getElementById("price").value);
        const discountPrice = parseFloat(this.value);
        
        if (discountPrice >= price) {
            document.getElementById("discountPriceError").style.display = "block";
        } else {
            document.getElementById("discountPriceError").style.display = "none";
        }
    });
    
    // Fiyat değiştiğinde indirimli fiyatı kontrol et
    document.getElementById("price").addEventListener("input", function() {
        const discountPriceInput = document.getElementById("discountPrice");
        if (discountPriceInput.value) {
            const price = parseFloat(this.value);
            const discountPrice = parseFloat(discountPriceInput.value);
            
            if (discountPrice >= price) {
                document.getElementById("discountPriceError").style.display = "block";
            } else {
                document.getElementById("discountPriceError").style.display = "none";
            }
        }
    });
    
    // Form gönderilmeden önce kontrol
    document.getElementById("productForm").addEventListener("submit", function(e) {
        const price = parseFloat(document.getElementById("price").value);
        const discountPriceInput = document.getElementById("discountPrice");
        
        if (discountPriceInput.value) {
            const discountPrice = parseFloat(discountPriceInput.value);
            
            if (discountPrice >= price) {
                e.preventDefault();
                alert("İndirimli fiyat, normal fiyattan küçük olmalıdır.");
                return false;
            }
        }
        
        return true;
    });
</script>
';

// Ürün resmi URL'si
$imageUrl = !empty($product['image']) && file_exists(ROOT_DIR . '/assets/uploads/products/' . $product['image']) 
    ? ASSETS_URL . '/uploads/products/' . $product['image'] 
    : ADMIN_ASSETS_URL . '/img/no-image.jpg';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <!-- Ürün Bilgileri -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Ürün Bilgileri</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="<?= $imageUrl ?>" alt="<?= h($product['name']) ?>" class="img-fluid rounded" style="max-height: 200px;">
                    </div>
                    
                    <h4 class="text-center mb-3"><?= h($product['name']) ?></h4>
                    
                    <div class="product-info-card">
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Kategori:</strong>
                            <?php 
                            $categoryName = dbQuerySingle("SELECT name FROM categories WHERE id = ?", [$product['category_id']])['name'] ?? 'Kategorisiz';
                            echo h($categoryName);
                            ?>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Fiyat:</strong>
                            <?= formatCurrency($product['price']) ?>
                        </div>
                        
                        <?php if (!empty($product['discount_price'])): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>İndirimli Fiyat:</strong>
                                <?= formatCurrency($product['discount_price']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <strong>Durum:</strong>
                            <span class="badge <?= $product['status'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $product['status'] ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <strong>Öne Çıkan:</strong>
                            <span class="badge <?= $product['featured'] ? 'bg-warning' : 'bg-secondary' ?>">
                                <?= $product['featured'] ? 'Evet' : 'Hayır' ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($product['description'])): ?>
                        <div class="mb-3">
                            <strong>Açıklama:</strong>
                            <p class="mt-1"><?= h($product['description']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Ürünlere Dön
                        </a>
                        
                        <a href="<?= BASE_URL ?>/api/menu.php?id=<?= $productId ?>" target="_blank" class="btn btn-outline-info">
                            <i class="fas fa-eye me-1"></i> Önizleme
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Ürün Düzenleme Formu -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ürün Bilgilerini Düzenle</h5>
                </div>
                <div class="card-body">
                    <form id="productForm" method="post" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $productId ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Ürün Adı <span class="required-star">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= h($product['name']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Kategori <span class="required-star">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Kategori Seçin</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= $product['category_id'] === $category['id'] ? 'selected' : '' ?>>
                                            <?= h($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Fiyat (₺) <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?= $product['price'] ?>" required>
                                    <span class="input-group-text">₺</span>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="discountPrice" class="form-label">İndirimli Fiyat (₺)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="discountPrice" name="discount_price" step="0.01" min="0" value="<?= $product['discount_price'] ?>">
                                    <span class="input-group-text">₺</span>
                                </div>
                                <div id="discountPriceError" class="text-danger" style="display: none;">
                                    İndirimli fiyat, normal fiyattan küçük olmalıdır.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= h($product['description']) ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="preparation_time" class="form-label">Hazırlama Süresi (dk)</label>
                                <input type="number" class="form-control" id="preparation_time" name="preparation_time" min="1" value="<?= $product['preparation_time'] ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="calories" class="form-label">Kalori (kcal)</label>
                                <input type="number" class="form-control" id="calories" name="calories" min="0" value="<?= $product['calories'] ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="allergens" class="form-label">Alerjenler</label>
                                <input type="text" class="form-control" id="allergens" name="allergens" placeholder="Gluten, Süt, Fındık, vb." value="<?= h($product['allergens']) ?>">
                                <div class="form-text">Alerjenleri virgülle ayırarak yazın.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="ingredients" class="form-label">İçindekiler</label>
                                <input type="text" class="form-control" id="ingredients" name="ingredients" value="<?= h($product['ingredients']) ?>">
                                <div class="form-text">Ana malzemeleri virgülle ayırarak yazın.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Ürün Görseli</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <div class="form-text">Yeni bir görsel yüklemezseniz mevcut görsel korunacaktır.</div>
                            <img id="imagePreview" class="preview-image" src="<?= $imageUrl ?>" alt="Görsel Önizleme">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="featured" name="featured" <?= $product['featured'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="featured">Öne Çıkan Ürün</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="status" name="status" <?= $product['status'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="status">Aktif</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" onclick="window.location.href='<?= ADMIN_URL ?>/products.php'" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> İptal
                            </button>
                            
                            <button type="submit" name="update_product" class="btn btn-primary">
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