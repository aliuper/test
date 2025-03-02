<?php
/**
 * Admin Panel - Yeni Ürün Ekleme
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
$pageTitle = 'Yeni Ürün Ekle';

// Kategorileri getir
$categories = dbQuery("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order, name");

// Ürün ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'], $_POST['csrf_token'])) {
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
            $imageName = '';
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = ROOT_DIR . '/assets/uploads/products/';
                
                // Dizin yoksa oluştur
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Resim yükleme
                $uploadResult = uploadFile($_FILES['image'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024);
                
                if ($uploadResult['success']) {
                    $imageName = $uploadResult['filename'];
                } else {
                    setFlashMessage('error', 'Resim yüklenirken bir hata oluştu: ' . $uploadResult['message']);
                    $imageName = '';
                }
            }
            
            // Ürünü veritabanına ekle
            $result = dbInsert("
                INSERT INTO products (
                    category_id, name, description, price, discount_price, 
                    image, preparation_time, allergens, ingredients, 
                    calories, featured, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, 
                    ?, ?, ?, NOW()
                )
            ", [
                $categoryId, $productName, $description, $price, $discountPrice,
                $imageName, $preparationTime, $allergens, $ingredients,
                $calories, $featured, $status
            ]);
            
            if ($result) {
                setFlashMessage('success', 'Ürün başarıyla eklendi.');
                
                // Olay kaydı oluştur
                $userId = $_SESSION['user_id'];
                dbInsert("
                    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ", [$userId, 'create', 'product', $result, "Yeni ürün eklendi: $productName"]);
                
                // Yönlendir
                redirect(ADMIN_URL . '/products.php');
            } else {
                setFlashMessage('error', 'Ürün eklenirken bir hata oluştu.');
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
        } else {
            preview.src = "#";
            preview.style.display = "none";
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
        const discountPrice = parseFloat(document.getElementById("discountPrice").value);
        
        if (discountPrice && discountPrice >= price) {
            e.preventDefault();
            alert("İndirimli fiyat, normal fiyattan küçük olmalıdır.");
            return false;
        }
        
        return true;
    });
</script>
';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <!-- Ürün Ekleme Formu -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Yeni Ürün Bilgileri</h5>
                    <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Ürünlere Dön
                    </a>
                </div>
                <div class="card-body">
                    <form id="productForm" method="post" action="<?= $_SERVER['PHP_SELF'] ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Ürün Adı <span class="required-star">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Kategori <span class="required-star">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Kategori Seçin</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>"><?= h($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Fiyat (₺) <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                                    <span class="input-group-text">₺</span>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="discountPrice" class="form-label">İndirimli Fiyat (₺)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="discountPrice" name="discount_price" step="0.01" min="0">
                                    <span class="input-group-text">₺</span>
                                </div>
                                <div id="discountPriceError" class="text-danger" style="display: none;">
                                    İndirimli fiyat, normal fiyattan küçük olmalıdır.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="preparation_time" class="form-label">Hazırlama Süresi (dk)</label>
                                <input type="number" class="form-control" id="preparation_time" name="preparation_time" min="1">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="calories" class="form-label">Kalori (kcal)</label>
                                <input type="number" class="form-control" id="calories" name="calories" min="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="allergens" class="form-label">Alerjenler</label>
                                <input type="text" class="form-control" id="allergens" name="allergens" placeholder="Gluten, Süt, Fındık, vb.">
                                <div class="form-text">Alerjenleri virgülle ayırarak yazın.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="ingredients" class="form-label">İçindekiler</label>
                                <input type="text" class="form-control" id="ingredients" name="ingredients">
                                <div class="form-text">Ana malzemeleri virgülle ayırarak yazın.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Ürün Görseli</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <img id="imagePreview" class="preview-image" style="display: none;" src="#" alt="Görsel Önizleme">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="featured" name="featured">
                                    <label class="form-check-label" for="featured">Öne Çıkan Ürün</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="status" name="status" checked>
                                    <label class="form-check-label" for="status">Aktif</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" onclick="window.location.href='<?= ADMIN_URL ?>/products.php'" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> İptal
                            </button>
                            
                            <button type="submit" name="add_product" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Ürünü Kaydet
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