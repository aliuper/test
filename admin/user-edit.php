<?php
/**
 * Admin Panel - Kullanıcı Düzenle
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

// Kullanıcı ID'si
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    setFlashMessage('error', 'Geçersiz kullanıcı ID\'si.');
    redirect(ADMIN_URL . '/users.php');
}

// Kullanıcı bilgilerini getir
$user = dbQuerySingle("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    setFlashMessage('error', 'Kullanıcı bulunamadı.');
    redirect(ADMIN_URL . '/users.php');
}

// Sayfa başlığı
$pageTitle = 'Kullanıcı Düzenle: ' . $user['username'];

// Kullanıcı düzenle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'], $_POST['csrf_token'])) {
    // CSRF kontrolü
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    } else {
        $email = sanitizeInput($_POST['email']);
        $role = sanitizeInput($_POST['role']);
        $fullName = sanitizeInput($_POST['full_name']);
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;
        $password = $_POST['password']; // Şifreyi sanitize etme
        
        // Zorunlu alanları kontrol et
        if (empty($email) || empty($role) || empty($fullName)) {
            setFlashMessage('error', 'Tüm zorunlu alanları doldurun.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('error', 'Geçerli bir e-posta adresi girin.');
        } else {
            // E-posta benzersiz olmalı (kendisi hariç)
            $existingUser = dbQuerySingle("SELECT * FROM users WHERE email = ? AND id != ?", [$email, $userId]);
            
            if ($existingUser) {
                setFlashMessage('error', 'Bu e-posta adresi zaten kullanılıyor.');
            } else {
                // Veritabanı güncelleme sorgusu
                $queryParams = [];
                
                if (empty($password)) {
                    // Şifre güncellenmeyecek
                    $sql = "UPDATE users SET email = ?, role = ?, full_name = ?, phone = ?, status = ?, updated_at = NOW() WHERE id = ?";
                    $queryParams = [$email, $role, $fullName, $phone, $status, $userId];
                } else {
                    // Şifre güçlülüğünü kontrol et
                    if (!isStrongPassword($password)) {
                        setFlashMessage('error', 'Şifre en az 8 karakter uzunluğunda olmalı ve büyük harf, küçük harf, rakam ve özel karakter içermelidir.');
                        // Hata oluştuğunda sayfayı yenilemeden formu göster
                        goto show_form;
                    }
                    
                    // Şifreyi hashle
                    $hashedPassword = hashPassword($password);
                    
                    // Şifre ile güncelle
                    $sql = "UPDATE users SET email = ?, role = ?, full_name = ?, phone = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?";
                    $queryParams = [$email, $role, $fullName, $phone, $status, $hashedPassword, $userId];
                }
                
                // Kullanıcıyı güncelle
                $result = dbExecute($sql, $queryParams);
                
                if ($result) {
                    setFlashMessage('success', 'Kullanıcı başarıyla güncellendi.');
                    
                    // Eğer kendi kullanıcını güncelliyorsa, session'ı da güncelle
                    if ($userId === (int)$_SESSION['user_id']) {
                        $_SESSION['full_name'] = $fullName;
                        
                        if ($role !== $_SESSION['role']) {
                            $_SESSION['role'] = $role;
                        }
                    }
                    
                    // Olay kaydı oluştur
                    $currentUserId = $_SESSION['user_id'];
                    dbInsert("
                        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ", [$currentUserId, 'update', 'user', $userId, "Kullanıcı güncellendi: {$user['username']}"]);
                    
                    // Yönlendir
                    redirect(ADMIN_URL . '/users.php');
                } else {
                    setFlashMessage('error', 'Kullanıcı güncellenirken bir hata oluştu.');
                }
            }
        }
    }
}

// Form etiketine atlamak için
show_form:

// Header'ı dahil et
include_once 'includes/header.php';

// Ekstra CSS
$extraCss = '
<style>
    .user-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background-color: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        font-weight: bold;
        color: #fff;
        margin: 0 auto 20px;
    }
    .card-auth-info {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .card-auth-info p {
        margin-bottom: 10px;
    }
    .card-auth-info strong {
        font-weight: 600;
    }
</style>
';

// Ekstra JS
$extraJs = '
<script>
    // Şifre göster/gizle
    $("#togglePassword").on("click", function() {
        const passwordInput = $("#password");
        const icon = $(this).find("i");
        
        if (passwordInput.attr("type") === "password") {
            passwordInput.attr("type", "text");
            icon.removeClass("fa-eye").addClass("fa-eye-slash");
        } else {
            passwordInput.attr("type", "password");
            icon.removeClass("fa-eye-slash").addClass("fa-eye");
        }
    });
    
    // Şifre oluşturma
    $("#generatePassword").on("click", function() {
        const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()";
        let password = "";
        
        // En az 8 karakter, 1 büyük harf, 1 küçük harf, 1 rakam, 1 özel karakter
        password += chars.charAt(Math.floor(Math.random() * 26)); // Büyük harf
        password += chars.charAt(Math.floor(Math.random() * 26) + 26); // Küçük harf
        password += chars.charAt(Math.floor(Math.random() * 10) + 52); // Rakam
        password += chars.charAt(Math.floor(Math.random() * 10) + 62); // Özel karakter
        
        // Geri kalan karakterler
        for (let i = 4; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        // Karakterleri karıştır
        password = password.split("").sort(() => 0.5 - Math.random()).join("");
        
        // Şifreyi input alanına yerleştir
        $("#password").val(password).attr("type", "text");
        $("#togglePassword").find("i").removeClass("fa-eye").addClass("fa-eye-slash");
    });
</script>
';

// Avatar arka plan rengi
$colors = ['#4b6584', '#a5b1c2', '#778ca3', '#0fb9b1', '#20bf6b', '#3867d6', '#8854d0', '#fed330', '#fc5c65', '#eb3b5a', '#2d98da', '#3dc1d3'];
$colorIndex = crc32($user['username']) % count($colors);
$avatarColor = $colors[$colorIndex];
$initials = strtoupper(substr($user['full_name'], 0, 1));
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <!-- Kullanıcı Bilgileri -->
            <div class="card">
                <div class="card-body text-center">
                    <div class="user-avatar" style="background-color: <?= $avatarColor ?>;">
                        <?= $initials ?>
                    </div>
                    
                    <h4 class="mb-1"><?= h($user['full_name']) ?></h4>
                    <p class="text-muted mb-3"><?= h($user['username']) ?></p>
                    
                    <?php
                    $roleName = '';
                    $roleClass = '';
                    
                    switch ($user['role']) {
                        case 'super_admin':
                            $roleName = 'Süper Admin';
                            $roleClass = 'bg-danger';
                            break;
                        case 'waiter':
                            $roleName = 'Garson';
                            $roleClass = 'bg-primary';
                            break;
                        case 'kitchen':
                            $roleName = 'Mutfak';
                            $roleClass = 'bg-success';
                            break;
                    }
                    ?>
                    <span class="badge <?= $roleClass ?> mb-3" style="font-size: 0.9rem;"><?= $roleName ?></span>
                    
                    <div class="card-auth-info text-start">
                        <p>
                            <strong>Durum:</strong>
                            <span class="badge <?= $user['status'] ? 'bg-success' : 'bg-danger' ?>">
                                <?= $user['status'] ? 'Aktif' : 'Pasif' ?>
                            </span>
                        </p>
                        <p><strong>Son Giriş:</strong> <?= $user['last_login'] ? formatDate($user['last_login']) : 'Henüz giriş yapılmadı' ?></p>
                        <p><strong>Oluşturulma:</strong> <?= formatDate($user['created_at']) ?></p>
                        <?php if ($user['updated_at']): ?>
                            <p><strong>Güncelleme:</strong> <?= formatDate($user['updated_at']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Kullanıcı Düzenleme Formu -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Kullanıcı Bilgilerini Düzenle</h5>
                    <a href="<?= ADMIN_URL ?>/users.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kullanıcılara Dön
                    </a>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>?id=<?= $userId ?>" id="edit-user-form">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Kullanıcı Adı</label>
                                <input type="text" class="form-control" id="username" value="<?= h($user['username']) ?>" readonly disabled>
                                <div class="form-text">Kullanıcı adı değiştirilemez.</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= h($user['email']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= h($user['full_name']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= h($user['phone']) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Rol <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="super_admin" <?= $user['role'] === 'super_admin' ? 'selected' : '' ?>>Süper Admin</option>
                                    <option value="waiter" <?= $user['role'] === 'waiter' ? 'selected' : '' ?>>Garson</option>
                                    <option value="kitchen" <?= $user['role'] === 'kitchen' ? 'selected' : '' ?>>Mutfak</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Durum</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="status" name="status" <?= $user['status'] ? 'checked' : '' ?> <?= $userId === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="status">Aktif</label>
                                </div>
                                <?php if ($userId === (int)$_SESSION['user_id']): ?>
                                    <div class="form-text text-danger">Kendi hesabınızı devre dışı bırakamazsınız.</div>
                                    <input type="hidden" name="status" value="1">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Şifre</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="generatePassword" title="Güçlü şifre oluştur">
                                    <i class="fas fa-key"></i>
                                </button>
                            </div>
                            <div class="form-text">Şifreyi değiştirmek istemiyorsanız boş bırakın. Yeni şifre en az 8 karakter uzunluğunda olmalı ve büyük harf, küçük harf, rakam ve özel karakter içermelidir.</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" onclick="window.location.href='<?= ADMIN_URL ?>/users.php'" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> İptal
                            </button>
                            
                            <button type="submit" name="update_user" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Kullanıcıyı Güncelle
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